# Performance

The full write-ups behind PR 16 and PR 17 in the [backlog](../../backlog.md). The scripts
behind the numbers here live in [`../benchmarks/`](../benchmarks/README.md).

### PR 16 — the mode performance comparison nobody has run

The README claimed hybrid was "faster" than classic. Nothing measured that, and a
head-to-head on identical sources has it 1.46x *slower* — expected, since hybrid runs the
classic scanner and then the spec pipeline on top. The claim was dropped rather than
reversed, because one fixture shape is not grounds for asserting either direction.

The two `*PerformanceTest` classes look like they compare pipelines, but do not:

- `Augmenter\CleanupPerformanceTest` — 3300 schemas, built in memory, augmenters only
- `Processors\CleanUnusedComponentsPerformanceTest` — 300 schemas, written to disk, full
  scan and generate

Each measures the overhead ratio of enabling cleanup *within its own pipeline*, against a
different baseline at a different size. Neither compares modes, and the two numbers they
print cannot be read against each other.

Timing the two cleanup implementations in isolation, on the same wide-and-shallow fixture
(flat schemas, two or three properties, single-level refs, 40% unused):

| schemas | spec `Augmenter\Cleanup` | classic `CleanUnusedComponents` |
| ------- | ------------------------ | ------------------------------- |
| 200     | 10.3 us/schema           | 20.5 us/schema                  |
| 800     | 9.9 us/schema            | 22.6 us/schema                  |
| 3200    | 10.4 us/schema           | 23.1 us/schema                  |

Both are linear — 2.0x per doubling across the range. The difference is a constant factor
of roughly 2.2x, not a scaling one.

That is the part worth following up. The recursive cleanup in classic is known
anecdotally to have caused real problems for users, and a constant factor does not explain
that. This fixture is wide but shallow, so it never exercises the recursive annotation
traversal in `Concerns\AnnotationTrait` — the thing most likely to degrade badly. A fixture
with **nesting depth** (nested `allOf`, schemas within schemas, deep `JsonContent` trees) is
the missing measurement, and the one that would confirm or kill the hypothesis.

Worth doing:

- a benchmark that varies depth as well as breadth, for both implementations
- a genuine mode comparison on identical sources, if any performance claim is to be made
  in the docs at all
- check whether hybrid scans twice — `doHybridAssemble()` runs a second `Generator` pass
  over the same sources the assembler already walked, which is the first place to look for
  the 1.46x
- PR 16 is about making the pipeline cheaper; PR 17 is about not running it over irrelevant
  code in the first place, which measures as the larger effect by an order of magnitude

Only worth stating in the README once measured across both dimensions; until then the docs
should stay silent on relative performance.

### PR 17 — reflector sources make scanning optional, and that is where the time is

Prompted by PR 16. The interesting performance question is not classic-vs-spec cleanup, it
is whether the source scan needs to happen at all.

`Builder::doBuildSpec()` tokenizes `$sourceScanner->getFiles()` and separately collects
`$sourceScanner->getReflectors()`. Passing only reflectors leaves the file list empty, so
the tokenizing loop does nothing. With the resolver discovering referenced classes
transitively, a build seeded with just the controllers produces the same document as
scanning the tree.

Measured on a fixed API surface (100 schemas, 10 controllers, 100 operations) with a growing
amount of ordinary non-API application code in the same tree:

| non-API files | scan directory | controller reflectors | speedup |
| ------------- | -------------- | --------------------- | ------- |
| 0             | 23.9 ms        | 24.1 ms               | 0.99x   |
| 200           | 44.4 ms        | 24.0 ms               | 1.85x   |
| 800           | 107.6 ms       | 24.6 ms               | 4.37x   |
| 3200          | 362.2 ms       | 24.5 ms               | 14.76x  |

Output was byte-identical in every row.

The shape is the point. Scanning costs what the *codebase* costs; resolver-driven reflection
costs what the *API surface* costs, and stays flat as the application grows around it.

Reading and tokenizing is strictly additive per file — work the reflector path never does at
all. Timed on its own with a fresh `TokenScanner` (it memoises per file, so a warmed scanner
measures zero):

| files | tokenize | per file |
| ----- | -------- | -------- |
| 111   | 12.9 ms  | 117 us   |
| 441   | 50.1 ms  | 114 us   |
| 2041  | 183.7 ms | 90 us    |

What is *not* free is the other side. Rough phase split at 400 schemas with no non-API code,
where the two approaches come out level overall:

| mode      | scan   | tokenize + collect | resolve | augment |
| --------- | ------ | ------------------- | ------- | ------- |
| directory | 2.6 ms | 57.6 ms             | 1.8 ms  | 63.2 ms |
| reflector | 0 ms   | 18.4 ms             | 48.6 ms | 63.4 ms |

Both must reflect and collect the same classes; the reflector path simply does it inside
`Resolver\Reflection` instead of the tokenize loop, and adds `findUnresolved()` on top —
12.3 ms at 400 schemas, a fixed two full sweeps of the ref walker, the schema reflectors and
a rebuilt `ComponentIndex`. It converges in 2 iterations, so the loop is not the problem;
the sweeps are just not free.

That is why the 0-noise row is a wash rather than a win, and it is worth confirming before
acting on any of this — the phase attribution above is approximate, since collect cost is
very uneven between controllers and models and autoloading lands in whichever phase touches
a class first.

The large win therefore comes specifically from never touching code that is not API, not
from reflection being cheaper than tokenizing. On the earlier fixture where 40% of models
were merely unreferenced the gain was only 1.24-1.37x, because those models still had to be
reflected once discovered.

Open before any of this can be recommended:

- **No CLI path.** `openapi <paths>` takes directories. A reflector-seeded build is
  programmatic-only today, so the fast path is unreachable for CLI users.
- **Where does the controller list come from?** The benchmark assumed the application
  already knows it, which is true given a router, a DI container or a route cache, and false
  for someone pointing the tool at a directory. That assumption should be stated rather than
  buried.
- **`#[OA\Info]` needs a source too** — it is not reachable from any controller, so it has
  to be passed in alongside them.
- Whether `guide/` should show the reflector-seeded form at all, or whether it stays a
  documented capability of `Builder` until there is a CLI story.

`reference/builder.md` already says the controllers are generally enough, which
these numbers support. It does not claim anything about speed, and should not until the CLI
question is settled.
