# Archive

Full write-ups for merged [backlog](../backlog.md) entries. The terse, one-line form of each
stays in backlog.md's "Where this stands"; this is where the reasoning behind them lives once
they stop being work left to do. Kept rather than deleted for the same reason the rest of the
backlog exists — decisions and the "outcome" write-ups they produced don't survive anywhere
else.

Entries keep their original PR number; numbers are not reused.

### PR 1 — declare config with a `#[Config]` attribute — **done, #2146**

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

**Outcome.** Built as proposed. `OpenApi\Utils\Config` sits beside `Pipeline` rather than in
`src/Attributes/`/`Spec/`, since it describes the pipeline itself, not an OpenAPI document.
`Pipeline::getConfig()` now discovers settings via the attribute instead of reflecting over
every `set*()` method with a non-object value; `DocGenerator` prefers the attribute's
description, falling back to the old heuristic for `src/Processors/`, which hasn't adopted
`#[Config]` — `composer docs:gen` output is unchanged. A new `ConfigTest` scans
`src/Augmenter/` asserting every `#[Config]` parameter has a matching setter; verified it
catches real drift by renaming a setter and watching the test fail.

Landed alongside, in the same PR: a `.github/PULL_REQUEST_TEMPLATE.md` (Overview + Changes,
see `CONTRIBUTING.md`) codifying the PR-description convention this backlog itself had
started following.

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

### PR 9 — scratch fixtures as end-to-end coverage, and more for Redocly to check — **done, #2144**

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

**Outcome.** #2144 added scratch fixtures for `Head`, `Options`, `Trace` and `Link` (root).
Auth fixtures (`MutualTls`, `OpenIdConnect`, OAuth flows) were already covered by #2137's
`Auth` fixture. Remaining candidate: `MediaType\Xml`.

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

### PR 21 — `TypedList::clear()` — **done, #2145**

`TypedList` can `add()`, `insert()`, `remove()` and `get()`, but there is no way to empty it.
Anyone wanting to start from a clean slate — no augmenters, no resolvers, no translators —
has to remove entries one at a time by class, which means knowing the whole default set
first.

That is exactly what someone experimenting with the pipeline wants to do, and what the
extension points page in PR 20 would otherwise have to talk them through. Small addition,
and it makes `withAugmenters(fn ($p) => $p->clear()->add(new OnlyMine()))` expressible.

`Pipeline` wraps a `TypedList`, so both benefit.

### PR 19 — pin `phpstan/phpstan` as well — **done, #2151**

#2143 pinned `rector/rector` and `friendsofphp/php-cs-fixer` to exact versions, because
`composer.lock` is not committed and every workflow installs `dependency-versions: highest`,
so a new release of either turns unrelated PRs red.

`phpstan/phpstan` (`^2.2`) has exactly the same exposure — new releases add checks at an
unchanged level. It has been quiet so far, and `phpstan-baseline.neon` absorbs some of it,
but the mechanism is identical and the argument for pinning is the same.

Deliberately not `phpunit/phpunit`: its `^11.5 || >=12.5.22` constraint is wide on purpose,
since the build matrix spans PHP 8.2 to 8.6.

**#2151 pins it to 2.2.12.** Two things surfaced while doing it, both worth keeping:

- **`^2.2` was already fiction.** `rector/rector` 2.6.5 requires `phpstan/phpstan ^2.2.10`,
  so since #2143 pinned rector the phpstan version has been dictated transitively by a
  different package's pin — 2.2.9 cannot be installed at all. Pinning a tool can move a tool
  nobody pinned.
- **`composer analyse` was failing locally and passing in CI**, which looked like the exact
  drift this entry predicts and was not. phpstan decides whether `$argv` is defined by reading
  the **running** PHP's `register_argc_argv` ini setting; a local 8.5 build with it off
  reports `Variable $argv might not be defined` in `tools/docgen.php`, CI's 8.3 with it on
  does not. Same phpstan version, same repository, different answer. #2152 guards the variable
  so the result no longer depends on the machine. Worth remembering before attributing the
  next environment-dependent analysis failure to a release.
