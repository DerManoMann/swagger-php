# Backlog

Decisions taken, questions still open, and follow-up work — across the docs, the tooling,
the test suite and the spec pipeline.

This started as notes for the documentation restructure. The facts it collected then were
written up in #2136 and now live in `docs/dev/` (`pipeline.md`, `testing.md`,
`docs-toolchain.md`, `writing-docs.md`), with terminology in `CONTEXT.md` and the pre-PR
checklist in `CONTRIBUTING.md`. Those are the source; add to them, not here.

What stays here is the reasoning behind decisions — including the ones not to do something,
which the code and the docs do not record. Entries are numbered in the order they were
written, not by priority; the order to work through them is below.

## Where this stands

Nothing is open. Merged so far: **#2134** (spec docs cleanup), **#2135** (rector rule
changes), **#2136** (developer docs, and the writing rules), **#2137** (`ComponentIndex`,
slot-target validation, and the attributes nothing was compiling), **#2138** (compiler
diagnostics reaching the configured logger, PR 13), **#2139** (README corrections),
**#2140** (the `ScratchTest` failure #2137 and #2138 produced only once merged, see PR 15),
**#2141** (the doc generator merge, PR 4), **#2142** (`.dist` convention for the phpstan
config), **#2130** (resolver step), **#2143** (rector 2.6.5, and pinned tooling).

phpstan now covers `tools/` as of #2141, so the doc generators have static analysis for the
first time. pcov is installed locally, so coverage numbers are real rather than inferred:
spec pipeline 94.2%, classic 92.9%, project 90.6%.

Suggested order for what is left:

1. **PR 9** — scratch fixtures. Cheap, and it widens what Redocly validates rather than what
   we assert about ourselves. Probably supersedes part of #2137.
2. **PR 1** — `#[Config]`, so `-D` output and the reference pages derive from one
   declaration rather than two rules aligned by hand.
3. **PR 3** — `composer docs:check`. The review checklist in `docs/dev/writing-docs.md`
   already specifies the runnable half; this is implementing it.
4. **PR 8** — the remaining test gaps. Run coverage in CI first; the survey behind it used a
   structural proxy and cannot see weakly-exercised code.
5. **PR 6** — generated code fragments, and only if PR 3's verification turns out not to be
   enough on its own. Verifying is much cheaper than generating.
6. **PR 7** — augmenter config on two pages. Small, independent, do it whenever.
7. **PR 10** — free the last two spec tests from `OpenApiTestCase`. Worth doing before v8
   rather than as part of removing classic: it turns that removal into a deletion.
8. **PR 11** — audit the thirteen providers that construct objects. Cheap, and until it is
   done any coverage figure understates reality by an unknown amount.

Q3 revisits when spec stops being beta (v7); Q4 when classic is removed (v8).

The scripts behind every number in PR 16 and PR 17 are in
[`.claude/benchmarks/`](benchmarks/README.md), so the measurements can be re-run rather than
trusted.

---

## Open questions

Q1 and Q2 are settled and implemented, kept for the reasoning. Q3 and Q4 are still live,
with the trigger for revisiting each.

**Q1. What replaces the DTO tree in `architecture.md`?** — RESOLVED (2026-08-28)
Delete it. The generated `reference/spec-attributes.md` already lists every attribute with
its "Allowed in" containment relationships and parameters, and cannot rot. `architecture.md`
should link there instead of maintaining a parallel tree by hand.

**Q2. Should object-valued augmenter settings be documented as config?** — RESOLVED (2026-08-28)
No. Config is **a constructor parameter that is not object typed** — ctor params are the
public API by convention; factories and resolvers are collaborators. Implemented as
`DocGenerator::configurableParameters()`, used by both reference generators. This dropped
`inheritance.attributeFactory`, `types.typeResolver`, `docblocks.parser` and four classic
`*.generator` entries, and `reference/augmenters.md` now matches `-D` exactly.

Interim solution — see follow-up PR 1.

**Q3. Are the classic-vs-spec output differences real?** — DEFERRED (2026-08-28)
Leave the hedged claims for now; spec mode is still optional/beta. Revisit before spec
becomes the default.

**Q4. How much of `docs/adr/` is actually an ADR?** — DEFERRED (2026-08-28)
Written as a reference/experiment to see if the format was useful. Both files describe the
*classic* pipeline only, so they stay as-is and serve as reference until classic is removed.
Condensing them into LLM context is acceptable in principle, just not now.

---

## Follow-up PRs

Agreed direction, deliberately **not** done in the doc-cleanup work.

### PR 1 — declare config with a `#[Config]` attribute

The ctor-param heuristic works but the rule is implemented twice: `Pipeline::getConfig()`
at runtime (drives `-D` and `-c`) and `DocGenerator::configurableParameters()` at build
time (drives the reference pages). They agree only because they were aligned by hand.

Proposed:

```php
public function __construct(
    #[Config('If set to true generate ids (md5) instead of clear text operation ids.')]
    protected bool $hash = true,
) {
}
```

Why an attribute rather than an `@api` docblock tag: reflectable with no docblock parsing,
typos fail as class-not-found instead of silently, static analysis sees it, and it is
idiomatic for a library whose premise is that attributes beat docblocks.

What it buys beyond the heuristic:
- one declaration feeding both `getConfig()` and the docs, so they cannot drift
- the description lives on the parameter, instead of being scraped from the *setter*
  docblock — the reason `inheritance.attributeFactory` rendered "No details available."
- explicit opt-in: adding a ctor param no longer silently widens the public config surface
- room for `deprecated` / `since` / allowed values without touching extraction code

Scope: `src/Augmenter/` only (8 settings). Leave `src/Processors/` on the inherited
heuristic until classic is removed. Cheaper after PR 4, which collapses the two extraction
paths into one — having the generators consume `Pipeline::getConfig()` instead of
re-deriving the rule belongs to that PR, not this one.

### PR 3 — keep derivable documentation in sync automatically

The audit's worst finding was a `openapi -h` block in
`guide/generating-openapi-documents.md` that had been *written* rather than captured: wrong
format, wrong `--mode` description, a `--version` default that does not exist, four options
missing. Nothing would ever have caught it.

The runnable half of this is now specified: `docs/dev/writing-docs.md` lists the mechanical
checks under "Reviewing documentation changes", each tied to a defect it has actually
caught. This PR is implementing those as `composer docs:check`, not deciding what to check.

Per item the choice is **generate** or merely **verify**. Verification is usually the better
trade: the prose stays human-written and readable, but drift fails the build. Generating
prose tends to produce the flat, listy tone this cleanup was removing in the first place.

Already covered, no action needed:
- the augmenter phase/order list — removed from `reference/architecture.md`, which now links
  to the generated `reference/augmenters.md`
- `docs/snippets/*.php` → `*-3.1.0.yaml` — `DocSnippetsTest`, across all modes and
  implementations
- `docs/examples/specs/*` → `*-3.x.y.yaml` — `ExamplesTest`
- the generated reference pages — `composer docs:gen`

Candidates, roughly in order of how badly they rot:

| Restated fact | Where | Suggested |
|---|---|---|
| `openapi -h` output | `guide/generating-openapi-documents.md` | generate or snapshot-test |
| `-D` default config output | same | snapshot-test |
| root / conditionally-root / never-root lists | `docs/dev/pipeline.md` | verify against `isRoot()` |
| compiler ↔ version table | `reference/architecture.md` | verify against `CompilerInterface::version()`/`supports()` |
| `Result` method listing | `reference/builder.md` | verify against reflection |
| inline signature claims, e.g. "`Operation\Get::__construct()` has no `$requestBody`" | `guide/spec-attributes.md` | verify by reflection |

Not derivable, needs human review on a schedule: the classic-processor → spec-augmenter
mapping table in `reference/architecture.md`, and the classic-vs-spec behaviour claims in
`guide/spec-attributes.md` (see Q3).

A single `DocsAccuracyTest` covering the verifiable rows would likely be shorter than one
generator, and would have caught most of what this cleanup fixed by hand.

### PR 4 — deduplicate `AugmenterGenerator` and `ProcessorGenerator` — **done, #2141**

408 lines across the two classes, with substantial copy-paste between them. Two fixes in
this cleanup had to be applied *twice, identically*: the ctor-param config rule
(`collectOptions()`) and the CLI prose block in `renderConfigSection()`. That is the tell.

Duplicated near-verbatim:
- `collectOptions()` — same setter scan, same type/description/default extraction
- `resolveDefault()` — same ctor-parameter lookup and `gettype()` match
- `renderConfigSection()` prose — same paragraph, differing only in "augmenter"/"processor"
  and the `--mode spec` flag

The copies have already drifted, which is the real cost:

| | `AugmenterGenerator` | `ProcessorGenerator` |
|---|---|---|
| Rendering | `Sections` abstraction (`SectionInterface` + `ConfigSettingsSection` etc.), swappable via `setSections()` | hand-rolled inline via `renderer->processorOptions()` |
| Output style | markdown list items | `<span style="font-family: monospace;">` HTML blocks |
| `resolveDefault()` | guards `isDefaultValueAvailable()` | **no guard** — `getDefaultValue()` throws `ReflectionException` on a ctor param without a default (latent; every documented option happens to have one) |
| Class docblock | strips `@implements` | does not |
| `configPrefix` | computed in the collected data | recomputed at render time |
| CLI example | correct | had `operatinId.hash` — a typo the other copy did not have, fixed in 4923c297 |

The divergent output style is user-visible: two pages in the same reference section present
the same kind of information in two different visual formats, for no reason other than that
one generator was refactored onto `Sections` and the other was not.

Suggested: move `collectOptions()`/`resolveDefault()` onto `DocGenerator` (where
`configurableParameters()` already lives), port `ProcessorGenerator` onto the `Sections`
abstraction so both render identically, and parameterise the shared CLI prose on
noun + mode flag. Genuine differences worth keeping: `ProcessorGenerator` also globs the
`Processors` directory to document processors that are not in the default pipeline.

Note this is orthogonal to PR 1 — if config moves to a `#[Config]` attribute, the shared
extraction shrinks but the rendering divergence remains.

**Outcome.** 339 deletions for 184 insertions, in three steps: share the collection, share
the configuration prose, render processors through the shared sections. The plumbing turned
out to be copied in *three* generators, not two — `SpecAttributeGenerator` had it as well —
so that page regenerating byte-identically was the evidence the lift was safe.

Five defects surfaced, only one of them visible in output:

- `ProcessorGenerator::resolveDefault()` calling `getDefaultValue()` unguarded (as predicted
  above; the merged copy keeps the guard, so this entry's claim is now moot)
- `ConfigSettingsSection` indenting only the first line of a description, which pushed
  `expandEnums.enumNames` and its fenced YAML block out of their list item — the one
  user-visible bug, and invisible until a multi-line description went through it
- `getName()` called on what may be a union or intersection type, in five places
- a loop variable shadowing the union it was iterating, so `allowsNull()` tested the last
  member rather than the union
- docblocks naming `AbstractAttribute` in the wrong namespace

The last three came from adding `tools` to the phpstan paths, which had never been analysed.
processors.md also gained the `Unknown keys are reported as warnings` sentence, true of it
all along since `Utils\Pipeline` warns for both pipelines.

### PR 6 — generate code fragments from the classes docs reference

Inlined source in documentation drifts, and has done so twice already:

- the `OA\Flow` constructor example in `dev/pipeline.md` had a fabricated body — it passed a
  positional array to `parent::__construct()`, which would land in `$x`
- the `Resolver\Reflection` sample in #2130 went stale **inside its own PR**: a later commit
  added a `class_exists` guard and changed the return type, and the copy was not updated

Idea: render fragments from the real classes by reflection — signatures without bodies —
and transclude them, so an interface shown in the docs is the interface that exists.

Where it earns its keep: **interfaces and public API shape**. `ResolverInterface`,
`PipeInterface`, `AttributeInterface`, `CompilerInterface` are small, stable, and shown in
docs precisely so a reader can implement them. A signature-only fragment is exactly right
there, and needs no markers in `src/`.

Where it does not: examples that need a *body* to make their point — "normalise on input,
store the simple form" is about what the constructor does, not its signature. A stripper
gives nothing there. The answer for those is `<<< @/snippets/…` transclusion of a real,
test-executed file, or not inlining at all.

**Consider verifying before generating.** A check that every PHP fence naming a project
symbol matches the real signature would have caught both defects above, costs far less than
a generator, and folds into PR 3 rather than adding a fourth thing under `tools/`. Generate
only where the fragment is worth *showing* in full; verify everywhere else.

Sequencing: after PR 4. Adding a third generator while two are still near-duplicates would
put the same code in three places.

### PR 7 — augmenter configuration is documented on two pages

`reference/architecture.md` § "Configuring augmenters" and `reference/builder.md`
§ "Augmenter configuration" both show a `withAugmenters()` block doing the same four things.
`builder.md`'s is a superset — it adds the `PathFilter` example and already links back to
`architecture.md` for pipeline design.

Same split as was applied to the resolver in #2130: `builder.md` owns wiring, because
`withAugmenters()` is a `Builder` method; `architecture.md` owns what the phases are and how
to write an augmenter. Delete "Configuring augmenters" from `architecture.md`.

Predates all of this work; noticed while re-homing the resolver docs.

### PR 8 — remaining spec test gaps

From a coverage survey on 2026-08-29, initially with a structural proxy and then with real
numbers once pcov was installed. Line coverage at that point: spec pipeline 94.2%, classic
92.9%, project 90.6%. Weakest areas were `src/Specification` (84.8%) and `src/Augmenter/`
(91.1%); `src/Spec/` reached 99.2% after the fix below.

Worth adding `--coverage-text` to a CI workflow so the number is visible per PR rather than
measured ad hoc.

Already closed by #2137: `ComponentIndex`, slot-target validation, and the
attributes nothing was compiling.

Still open:

- **`ValidateRelationsTest` has no full spec analogue.** Classic asserts its nesting map is
  bidirectional — if A names B as a parent, B must name A as nested. The spec side now
  checks that slots name real properties, but not the reverse direction: that a class
  claiming it can nest into a parent is one the parent will actually accept.
- **The `null` vs `Undefined::UNDEFINED` convention is undecided.** Classic has
  `AnnotationPropertiesDefinedTest` asserting no property defaults to `null`. Spec is
  deliberately mixed, and the reasoning survives only as a comment in `Augmenter/Types.php`
  ("Can't use `??=` here — const defaults to `Undefined::UNDEFINED`, not null"). Decide what
  the invariant is, then it can be asserted. This is a design call, not a test-writing one.
- **No direct tests**: `Assembler/DefaultAttributeTranslator`,
  `Assembler/OptionalPropertyAttributeTranslator`, `Utils/SourceLocation`,
  `Utils/SpecificationWalker`.

### PR 9 — scratch fixtures as end-to-end coverage, and more for Redocly to check

`tests/Spec/UncoveredAttributesTest.php` covers the previously-unexercised attributes by
building a `Specification` by hand and asserting compiler output. That is a unit test: it
skips assembly and augmentation entirely.

Scratch fixtures would be better, and probably should replace it. A fixture under
`tests/Fixtures/Scratch/` exercises the whole pipeline — assembler, augmenters, compiler —
and produces a YAML file per OpenAPI version. Two things follow from that:

- the attributes get *end-to-end* coverage rather than compiler-only
- the emitted YAML lands in the set `composer redocly` already lints
  (`tests/Fixtures/Scratch/*.yaml`, currently 123 files), so the output is checked against
  the OpenAPI schema rather than only against our own expectations

Worth going further: generate more spec output to file generally, so Redocly validates a
wider surface, and so tests can assert against the same files rather than restating expected
structure inline. Anything asserted inline is an expectation we wrote; anything Redocly
accepts is an expectation the specification wrote.

Candidates for new fixtures: the typed operation subclasses (`Head`, `Options`, `Trace`),
the OAuth flows, `MutualTls` / `OpenIdConnect`, and `Link` — which has an `isRoot()`
condition that no example currently exercises.

### PR 10 — extract the pipeline-agnostic half of `OpenApiTestCase` into concerns

`tests/OpenApiTestCase.php` is 332 lines and 52 test classes extend it. Most of it is
classic — `getAnalyzer()`, `initializeProcessors()`, `processorPipeline()`,
`analysisFromFixtures()`, `allAnnotationClasses()`, `allAttributeClasses()`,
`annotationsFromDocBlockParser()`, `getContext()`, `createOpenApiWithInfo()` — and can be
deleted with classic in v8.

But some of it is not classic at all, and today the only way to reach it is by extending a
classic-flavoured base class.

The spec side has already mostly escaped: all sixteen augmenter tests, plus
`SlotMapConsistencyTest`, `AssemblerTest` and `CompilerTest`, extend plain `TestCase` and
compose traits from `tests/Concerns/`. **Only two hold out**, and between them they need
five members:

| Test | Needs |
|---|---|
| `ScratchTest` | `getTrackingLogger()`, `assertOpenApiLogEntryContains()`, `assertSpecEquals()` |
| `BuilderTest` | the same three, plus `getAnalyzer()` and `getTypeResolver()` |

So the extraction is small and well-bounded:

- **`TracksLogEntries`** — `getTrackingLogger()` + `assertOpenApiLogEntryContains()`, plus the
  `setUp`/`tearDown` that manage the captured entries. Overlaps `AssertsBuilderResult`; see
  below, the overlap now has an answer.
- **`AssertsSpecEquals`** — `assertSpecEquals()`. Overlaps `AssertsSchemaStructure`, which
  compares `allOf` refs and property names order-independently. Same question.
- **`ProvidesTypeResolvers`** — `getTypeResolver()` / `getTypeResolvers()`, used as a data
  provider by both pipelines.
- **`UsesFixtures`** — `fixture()` / `fixtures()`. Path helpers, wanted everywhere.

`getAnalyzer()` is classic and should stay behind; `BuilderTest` needs it only for the
classic-mode cases.

Do the overlap review as part of this rather than after: `tests/Concerns/` has grown to six
traits and at least two of them cover ground `OpenApiTestCase` also covers. Extracting
without reconciling would just move the duplication.

Once the two holdouts are converted, `OpenApiTestCase` is classic-only and its removal is a
straight deletion when classic goes, rather than an untangling.

#### Which log mechanism wins

There are now three overlapping ways to declare what a test expects from diagnostics:

| Mechanism | Channel | Semantics |
|---|---|---|
| `AssertsBuilderResult::expectResultWarnings()` | `Result::warnings()` | tolerance — may appear |
| `assertOpenApiLogEntryContains()` | PSR logger | expectation — must appear, in order |
| `ignoreLogEntries()` | PSR logger | tolerance — may appear |

**Consolidate on the logger.** `Utils\CollectingLogger` both records to `entries()` *and*
forwards to its delegate, and `CompilerInterface::validate()` returns those same entries. So
since #2138, `Result::warnings()` and the PSR logger are two views of one
stream, not two sources. The logger is the general capture point; `Result::warnings()` is a
convenience over it.

The cost of keeping both is already visible: `ExamplesTest` declares the same two compiler
warnings twice, once per mechanism, because they arrive on both channels.

Prior art on a stale branch: `origin/expexts-logger-contains` has an `ExpectsLoggerContains`
trait plus a PHPUnit event subscriber that moves assertions from `tearDown()` to
`afterTestMethodCalled`. That timing change is the interesting part and worth keeping.
Treat it as reference only — it predates `doBuildSpec($hybrid)` so it is based on an old
master, and the implementation is not known to be settled.

Do this before or with the extraction, not after: extracting `TracksLogEntries` while the
overlap stands would just relocate it.

### PR 11 — audit data providers that construct objects

PHPUnit evaluates data providers while collecting tests, before coverage recording starts.
Anything constructed inside a provider is asserted but **never counted as covered**, and the
tests pass either way, so the only symptom is a class reading as untouched despite having
tests.

Found the hard way: `UncoveredAttributesTest` was written with construction in the
providers. Every case passed, and `Operation\Head`, `Options`, `Trace`, three `Flow` classes,
`MutualTls` and `OpenIdConnect` all still reported 0%. Moving construction into the test body
took each to 100% and `src/Spec/` from 79.3% to 99.2%, with no change to the assertions.
Fixed by #2137, and written up in `docs/dev/testing.md`.

**Thirteen other providers do the same thing**, and should be checked for classes that are
only ever built there:

- `CompilerTest`: `nullableProvider`, `exclusiveBoundsProvider`, `schemaFeatureProvider`,
  `defaultAndExampleProvider`, `validationProvider`
- `GeneratorTest`: `sourcesProvider`, `processorCases`
- `DocSnippetsTest::snippetSets`, `Utils/SourceScannerTest::sourcesProvider`,
  `Annotations/AbstractAnnotationTest::identityCases`,
  `Type/TypeResolverTest::resolverAugmentCases`,
  `Analysers/ReflectionAnalyserTest::analysers`,
  `tools/CSFixer/SpecNamespaceAliasFixerTest::provideFixCases`

Most will be harmless — the classes involved are covered elsewhere, so the lost attribution
does not show. The ones to fix are those where a provider is the only place a class is
constructed. Diff coverage before and after moving construction into the test body to tell
which is which.

Note the first scan for this reported no matches at all, from a broken detector. Any re-run
should be sanity-checked against a provider known to construct objects — `nullableProvider`
is a good canary.

### PR 12 — scratch fixtures for the remaining uncovered DTOs

Ongoing rather than a single change: each fixture may surface real bugs, as the first one
did.

`Fixtures/Scratch/Auth{,-spec}.php` covers the security schemes and OAuth flows. Writing it
found two things a unit test could not:

- the pipeline emitted `type: mutualTLS` into **3.0** documents, where that type does not
  exist. Redocly rejected it the moment the fixture produced a real file. Fixed: the 3.0
  compiler now warns and omits, as it already did for webhooks.
- classic cannot express `mutualTLS` at all — `OAT\SecurityScheme` validates `type` against
  http / apiKey / oauth2 / openIdConnect only. Left as-is; classic is frozen.

Still wanted, same treatment: `Operation\{Head,Options,Trace}`, `MediaType\Xml`, `Link`
(its `isRoot()` condition is unexercised), and anything the next coverage run shows thin.

Mechanics worth knowing before starting:

- Discovery globs `Scratch/*.php` and skips `-spec` names, so a **classic `.php` file is
  required** even for spec-only material. Omitting the classic *YAML* is how you skip the
  classic cases — `$spec === null` continues.
- Regenerate by uncommenting `file_put_contents` in `ScratchTest::testScratch()`. Always
  pair it with `--filter <Fixture>`: an unfiltered run rewrites **every** fixture, and the
  current dumper differs from what is committed, so the diff is enormous and mostly noise.
- `$expectedLogs` is keyed `{fixture}-{version}` with no mode component, so a warning raised
  in one mode only cannot be registered without skipping the other mode's case.

### PR 13 — compiler diagnostics never reach `Builder::setLogger()` — **done, #2138**

`Builder::resolveCompiler()` constructs compilers with no logger, so `CollectingLogger` has
nothing to forward to. Compiler warnings reach `Result::warnings()` but never the PSR logger
the caller supplied. The classic path does forward — `doBuildClassic()` wraps the user's
logger — so the two pipelines behave differently for the same `setLogger()` call.

Passing the logger through is a one-line fix, and it was tried: it surfaces a pre-existing
`Schema: const is not supported in OpenAPI 3.0, using enum fallback` warning across ten
`ExamplesTest` and `ScratchTest` cases, each of which then needs the expectation registered.
Worth doing, but as its own change — those ten registrations are the actual work, and they
document warnings nobody currently sees.

### PR 14 — apply the documentation rules to docblocks

`docs/dev/writing-docs.md` was written for markdown pages, but most of what it says is about
precision and economy, and those apply to docblocks unchanged. Docblocks in `src/Spec/`,
`src/Augmenter/`, `src/Processors/` and `src/Annotations/` are additionally *spliced into the
generated reference pages*, so an imprecise one ships to users as published documentation.

Every defect the rules exist to catch has already turned up in a docblock during this work:

- **Stale references** — `AttributeFactory` cited `ExpandHierarchy`, a class that no longer
  exists, and `contains()` for a method actually named `contained()`
- **Inverted precision** — `AttributeInterface::contained()` described the slot as living on
  the declaring attribute; it lives on the parent
- **Self-description** — a fixture docblock explained that it "doubles as a worked example …
  the translation people usually have to guess at"
- **Filler** — "Convenience empty/noop base imlementation", typo included

Rules that transfer directly: do not claim what you have not verified; do not cite line
numbers; detail in exactly one place; no volatile values; no open-ended enumerations; do not
describe the thing you are writing in. Rules that do not: site-absolute links, the
generated-page conventions.

Two things to do while there:

- Generalise the checklist wording from "page" to "page or file". The self-description item
  says *page*, which is why the fixture docblock slipped past a checklist run.
- Decide whether `writing-docs.md` covers both, or whether docblock guidance belongs beside
  the code. One document is probably right — the rules are identical and duplicating them
  would break the rule they state.

Worth pairing with a sweep: `src/Spec/` docblocks are the ones users read, since they become
the Spec Attributes reference.

### PR 15 — make `ScratchTest` log expectations mode-aware

`$expectedLogs` is keyed `{fixture}-{version}`. Diagnostics raised in one mode only cannot be
*expected*, because the key applies to both and the unmatched expectation fails the other
mode's case. They can only be tolerated, which asserts nothing.

Three are tolerated today as a result:

- `Schema: const is not supported in OpenAPI 3.0, using enum fallback` — Docblocks-3.0.0
- `Tag "invalidparent" references non-existent parent "nah"` — Tags-3.2.0
- `mutualTLS security schemes are not supported in OpenAPI 3.0` — Auth-3.0.0

The `Tags` one is the reason to bother. That fixture contains a tag named `invalidparent`
pointing at a parent named `nah`; it exists to provoke that warning, and the warning has
never been observable, so nothing has been checking it fires. Tolerating it keeps the suite
green while still asserting nothing.

Adding a mode component to the key — `{fixture}-{version}-{mode}`, falling back to the
current form — would let all three become expectations. Small change, and it converts three
silent tolerances into three real assertions.

The third arrived as a semantic merge conflict (#2140), which is worth recording as a
pattern rather than a one-off. #2137 added the mutualTLS warning while compiler diagnostics
still went nowhere, so it had nothing to declare. #2138 routed those diagnostics to the
configured logger and seeded `$ignoredLogs` with the two entries *it* surfaced. Both were
green alone; only the merged state failed, and only on `master` after the fact. Any PR that
adds a compiler diagnostic now has to know about a list a different PR introduced —
a coupling that mode-aware keys would not remove, but that reviewers should expect until
some check catches it.

Worth checking at the same time whether other fixtures were written to provoke diagnostics
that the strict-FIFO tracking logger has been swallowing.

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
| --------- | ------ | ------------------ | ------- | ------- |
| directory | 2.6 ms | 57.6 ms            | 1.8 ms  | 63.2 ms |
| reflector | 0 ms   | 18.4 ms            | 48.6 ms | 63.4 ms |

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

### PR 18 — `AttributeGenerator` is the last generator rendering by hand

#2141 moved augmenters, spec attributes and processors onto the shared `Sections`
abstraction. `AttributeGenerator` still renders inline through `Renderer::classDescription()`
and `Renderer::references()`, which exist only for it.

Porting it would finish the job and let those two methods go, the way `processorOptions()`
and `indentedBr()` went. Against that: it generates the classic attributes and annotations
pages, and classic is removed in v8, so this may be work with a short life. Worth doing only
if something else needs to touch that generator anyway.

---

### PR 19 — pin `phpstan/phpstan` as well

#2143 pinned `rector/rector` and `friendsofphp/php-cs-fixer` to exact versions, because
`composer.lock` is not committed and every workflow installs `dependency-versions: highest`,
so a new release of either turns unrelated PRs red.

`phpstan/phpstan` (`^2.2`) has exactly the same exposure — new releases add checks at an
unchanged level. It has been quiet so far, and `phpstan-baseline.neon` absorbs some of it,
but the mechanism is identical and the argument for pinning is the same.

Deliberately not `phpunit/phpunit`: its `^11.5 || >=12.5.22` constraint is wide on purpose,
since the build matrix spans PHP 8.2 to 8.6.

---

## Not doing

**`generator.ignoreOtherAttributes` has no documented home.**
Dropped 2026-08-28 — classic-only by construction (`Generator::getDefaultConfig()` and
`Analysers/AttributeAnnotationFactory`, neither used by the spec pipeline), so spec `-D`
correctly never reports it and the flag has no meaning there. Residual gap accepted: a
classic user running `-D` sees a key that no reference page explains. Not worth fixing for
a pipeline removed in v8.

**`Operation::$operationId` is documented `@var string` but treated as nullable.**
Dropped 2026-08-28 — classic-only, and classic is removed in v8, so it is not worth the
churn. The rector skip for `IfToNullCoalescingAssignRector` on
`src/Processors/OperationId.php` therefore stays permanently; `rector.php` carries an
inline comment explaining why.

Note the systemic cause is still live and worth remembering: `composer.lock` is gitignored,
so CI always resolves the latest dependencies. A new rector or cs-fixer release can turn
every branch's `code-style` job red with no change to the repo — which is how this
surfaced (see PR #2135).

