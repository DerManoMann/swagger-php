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
config), **#2130** (resolver step), **#2143** (rector 2.6.5, and pinned tooling),
**#2144** (scratch fixtures for Head/Options/Trace and Link, PR 9),
**#2145** (`TypedList::clear()`, PR 21).

phpstan now covers `tools/` as of #2141, so the doc generators have static analysis for the
first time. pcov is installed locally, so coverage numbers are real rather than inferred:
spec pipeline 94.2%, classic 92.9%, project 90.6%.

Suggested order for what is left:

1. **PR 1** — `#[Config]`, so `-D` output and the reference pages derive from one
   declaration rather than two rules aligned by hand.
2. **PR 3** — `composer docs:check`. The review checklist in `docs/dev/writing-docs.md`
   already specifies the runnable half; this is implementing it.
3. **PR 8** — the remaining test gaps. Run coverage in CI first; the survey behind it used a
   structural proxy and cannot see weakly-exercised code.
4. **PR 6** — generated code fragments, and only if PR 3's verification turns out not to be
   enough on its own. Verifying is much cheaper than generating.
5. **PR 7** — augmenter config on two pages. Small, independent, do it whenever.
6. **PR 10** — free the last two spec tests from `OpenApiTestCase`. Worth doing before v8
   rather than as part of removing classic: it turns that removal into a deletion.
7. **PR 11** — audit the thirteen providers that construct objects. Cheap, and until it is
   done any coverage figure understates reality by an unknown amount.
8. **PR 22** — OpenAPI 3.2 field coverage in `src/Spec/`. Phases 1-3 are ready; Phase 4 is
   blocked on Q5.

Q3 revisits when spec stops being beta (v7); Q4 when classic is removed (v8). Q5 should be
settled before starting PR 22's Phase 4.

Entries that need supporting material, or have grown too long for the numbered list, get a
folder under `.claude/backlog/`, named for the topic rather than the entry number so it
survives renumbering. So far: [`backlog/benchmarks/`](backlog/benchmarks/README.md), the
scripts behind every number in PR 16 and PR 17 — there so the measurements can be re-run
rather than trusted; [`backlog/performance/`](backlog/performance/README.md), PR 16 and
PR 17's own write-ups; [`backlog/testcase-concerns/`](backlog/testcase-concerns/README.md),
PR 10's; [`backlog/nelmio-poc/`](backlog/nelmio-poc/README.md), PR 20's; and
[`backlog/spec-3.2/`](backlog/spec-3.2/README.md), the full field-by-field audit behind
PR 22.

Merged entries move to [`backlog/archive.md`](backlog/archive.md) once done — the terse form
stays in "Where this stands" above.

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

**Q5. How does a reusable, `$ref`-able `PathItem` or `MediaType` get a component identity?** —
OPEN (2026-09-01)
`Parameter`/`Header`/`Link` solve this with a component-key constructor field (`parameter:`,
`header:`, `link:`) plus a conditional `isRoot()` that is true only when that key is set and
`ref` is not. `PathItem` has neither — it is unconditionally root, and its `path` is always a
resolved URL, never a component name, so nothing distinguishes "the shared metadata for
`/pets/{id}`" from "a reusable path-item template to `$ref` from elsewhere". `MediaType` is
worse: `$mediaType` is simultaneously the value (`'application/json'`) and, today, the only
thing that could serve as a lookup key — a named `components.mediaTypes` entry needs an
identity independent of the media-type string itself. Blocks Phase 4 of PR 22
(`Components.pathItems` / `Components.mediaTypes`). See the "Phase 4" section of
[`backlog/spec-3.2/README.md`](backlog/spec-3.2/README.md) for the two options sketched so
far.

---

## Follow-up PRs

Agreed direction, deliberately **not** done in the doc-cleanup work. Merged entries move to
[`backlog/archive.md`](backlog/archive.md); numbers are not reused.

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

### PR 10 — extract the pipeline-agnostic half of `OpenApiTestCase` into concerns

`tests/OpenApiTestCase.php` is 332 lines and 52 test classes extend it, but most of it is
classic and can be deleted with classic in v8. Two spec-side holdouts — `ScratchTest` and
`BuilderTest` — still extend it only to reach five members that aren't classic at all
(`getTrackingLogger()`, `assertOpenApiLogEntryContains()`, `assertSpecEquals()`, plus
`getAnalyzer()`/`getTypeResolver()` for `BuilderTest`'s classic-mode cases). Extracting those
into `tests/Concerns/` traits — reconciling overlap with `AssertsBuilderResult` and
`AssertsSchemaStructure` along the way — turns removing `OpenApiTestCase` into a straight
deletion instead of an untangling. Also settles which of three overlapping
diagnostic-assertion mechanisms to standardise on: the PSR logger, since `CollectingLogger`
already forwards to it and `Result::warnings()` is just a view over the same stream.

Full plan, the member table, and the log-mechanism comparison:
[`backlog/testcase-concerns/README.md`](backlog/testcase-concerns/README.md).

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

**#2144** added scratch fixtures for `Operation\{Head,Options,Trace}` and `Link` (including
its `isRoot()` condition). Still wanted: `MediaType\Xml`, and anything the next coverage run
shows thin.

Mechanics worth knowing before starting:

- Discovery globs `Scratch/*.php` and skips `-spec` names, so a **classic `.php` file is
  required** even for spec-only material. Omitting the classic *YAML* is how you skip the
  classic cases — `$spec === null` continues.
- Regenerate by uncommenting `file_put_contents` in `ScratchTest::testScratch()`. Always
  pair it with `--filter <Fixture>`: an unfiltered run rewrites **every** fixture, and the
  current dumper differs from what is committed, so the diff is enormous and mostly noise.
- `$expectedLogs` is keyed `{fixture}-{version}` with no mode component, so a warning raised
  in one mode only cannot be registered without skipping the other mode's case.

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

The README claimed hybrid was "faster" than classic; measured, it's 1.46x slower. The two
existing `*PerformanceTest` classes each measure their own pipeline's cleanup-enabled
overhead against a different baseline at a different size — neither compares modes. A proper
side-by-side, plus whether hybrid's double `Generator` pass (PR 17 territory) explains the
gap, is what's missing before any performance claim goes back in the docs.

Numbers and the follow-up questions: [`backlog/performance/README.md`](backlog/performance/README.md).

### PR 17 — reflector sources make scanning optional, and that is where the time is

Prompted by PR 16. Seeding `Builder` with controller reflectors instead of scanning a
directory produced byte-identical output up to 14.76x faster as unrelated application code
grew around a fixed API surface — scanning costs what the codebase costs, reflector-driven
resolution costs what the API surface costs. Not yet actionable: no CLI path takes a
reflector list, and where the controller list comes from (router / DI container / route
cache) needs stating rather than assuming.

Full measurements, phase breakdown, and the open blockers:
[`backlog/performance/README.md`](backlog/performance/README.md).

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

### PR 20 — the NelmioApiDocBundle proof of concept, and the docs behind it

Two halves, both parked and both worth keeping.

**The design docs** were on `feat/downstream-support`, 1632 lines across four files. That
branch has been dropped — almost everything it proposed has since shipped:

| Proposal | Status |
| --- | --- |
| widen `addSource()` to accept reflectors | shipped, `string\|\SplFileInfo\|\Reflector\|iterable` |
| reflector parameter on `AttributeTranslatorInterface::translate()` | shipped |
| add `Schema\Ref` | shipped, `src/Spec/Schema/Ref.php` |
| add `Pipeline::insertAfter()` | dropped — `TypedList::insert()` takes a callable returning an index, so this is redundant |
| document programmatic `Specification` population | becomes the extension points page below |
| document the Attachable metadata pattern | as above |
| document `Undefined` scope in spec attributes | as above |

Its tip was `d6a0ac5d` if any of the rest is ever wanted back.

What does not exist anywhere else is the set of design principles it recorded — constraints
on how the pipeline should *not* be extended, each with a reason:

- **Do not widen property types for downstream convenience.** The strong typing is the
  point. Downstream metadata belongs in Attachables, not in `$ref: string|object`.
- **Do not add framework-specific code.** swagger-php stays framework-agnostic; translators,
  augmenters and attachables are the contract.
- **Do not add event or listener patterns.** The pipeline is deterministic and debuggable.
  Events introduce non-obvious ordering and make testing harder.
- **Do not expose Assembler internals.** `collect()` is the contract; two-pass resolution is
  an implementation detail.

These belong in the extension points page — they answer "why can't I just…", which is the
question an integrator actually arrives with.

**The proof of concept** is `DerManoMann/NelmioApiDocBundle` at `spec-poc`, locally
`../NelmioApiDocBundleFork`. About 540 lines under `src/SpecPoC/` — three attributes, two
augmenters, five translators, plus `Run.php` and a `poc.php` entry point. It exercises the
extension points rather than being a real integration: attribute translators for Symfony's
routing and `MapRequestPayload` / `MapQueryParameter`, augmenters for models, and auto
annotation of classes and public properties via a translator. `Run.php` carries a note that
the `MapRequestPayload` handling is deliberately one of several possible approaches. There
are uncommitted changes in the working tree, including a `ModelAutoTranslator` →
`SchemaAutoTranslator` rename.

The plan is to review it, polish lightly — it is meant to showcase the extension points, not
to be production code — and put it in front of the Nelmio project as an early heads-up ahead
of v7/v8.

**Re-check the docs against master before showing anyone.** They were written before #2130
and several claims have moved:

- `downstream-integration.md` opens with "they don't scan files — they discover endpoints via
  framework routing and use reflection on known controller classes". That is now largely
  served by reflector sources plus the resolver, and PR 17 measures what it buys.
- `SWAGGER_PHP_NATIVE_SPEC_PLAN.md` argues against hybrid partly on "HybridBridge doesn't
  transfer attachables". `Spec\Attachable` and an `attachables` bucket on `Specification`
  exist now, so at minimum the wording needs revisiting even if the conclusion holds.
- Its other objection, that `HybridBridge` skips annotations without reflectors, still reads
  true — `HybridBridge` bails on `!$annotation->_context->reflector instanceof \Reflector`.

**An extension points page comes out of this.** Agreed as part of the work: a `docs/dev/`
page for developers integrating swagger-php into their own tooling, written from whatever
the PoC shows actually works rather than from the API surface alone. Nothing under `docs/`
addresses that audience today — `reference/builder.md` documents the hooks one at a time,
which is not the same as showing how they compose.

The surface it would cover, all already public:

| Hook | For |
| --- | --- |
| `Builder::addSource(\Reflector)` | seeding from framework routing instead of a directory scan (PR 17) |
| `AttributeFactory::withTranslators()` | `AttributeTranslatorInterface` — turning foreign attributes into spec ones |
| `Builder::withAugmenters()` | `PipeInterface` — enriching the specification, grouped into phases |
| `Builder::withResolver()` | `ResolverInterface` — supplying classes the specification refers to but does not contain |
| `Builder::withAttributeFactory()` | assembly-time control |
| `Builder::setCompiler()` | `CompilerInterface` — version-specific output |
| `Builder::withGenerator()` | the classic escape hatch, hybrid only |

The PoC exercises the first four, which is the useful signal about what to write up first.
Worth capturing the dead ends too — `Run.php` already notes that its `MapRequestPayload`
handling is one of several approaches and probably not the best.

**A fictive example under `docs/examples` is the better artefact for the docs.** Size fits:
the unit to compare against is one processor example — `schema-query-parameter` is 114 lines
plus a small `app/` fixture and an expected yaml — not the 8422-line `specs/` tree. A
synthetic framework integration would shed the Symfony routing and bundle wiring the real
PoC needs, and could plausibly land in 150-250 lines while still showing reflector sources,
a translator and an augmenter together.

The catch is that **`docs/examples/processors/` is verified by nothing**:

- `UsesExamples::examplePath()` hardcodes `docs/examples/specs/`, so `ExamplesTest` never
  sees it
- `composer redocly` lints `docs/examples/specs/**/*.yaml` and `tests/Fixtures/Scratch/*.yaml`,
  not the processor yamls
- only phpstan touches it, via the `docs/examples` path

Both processor examples therefore carry expected-output yaml that nothing compares against.
That is an argument for the approach rather than against it — the same captured-not-composed
problem as the CLI help text — but wiring `processors/` into the suite has to come with it,
or the new example rots the same way. Companion to PR 9, which widens what Redocly checks.

Suggested split: the fictive example carries the documentation and can be verified, so it is
what the extension points page links to and what the Nelmio project reads. The fork PoC stays
where it is as evidence the approach works against a real bundle, and is not polished into
documentation.

### PR 22 — OpenAPI 3.2 field coverage in `src/Spec/`

Spec attributes are meant to be version-agnostic: one DTO holds every version's fields, and
the per-version `Compiler` (`OpenApi30Compiler` / `OpenApi31Compiler` / `OpenApi32Compiler`)
decides what to emit — see `nullable` handling in `OpenApi30Compiler::compileSchema()` for the
established pattern. `OpenApi32Compiler` already exists, but as a two-method subclass built
for the one piece of 3.2 already implemented (`Tag::$parent`/`$kind`, plus a validation rule
for dangling `parent` references). It comments "adds Tag summary/parent/kind and PathItem
query" — the PathItem/query half was never done.

A field-by-field audit against the [3.2.0 spec](https://spec.openapis.org/oas/v3.2.0.html)
found ten more additions with no DTO field at all: `OpenApi::$self`, `Server::$name`,
`Example::$dataValue`/`$serializedValue`, a `cookie` parameter style, a `querystring`
parameter location, the `query` HTTP method plus `additionalOperations` for custom verbs,
`MediaType::$itemSchema`, `Encoding::$itemEncoding`/`$prefixEncoding`, OAuth2's
`deviceAuthorization` flow, and `Security\Scheme::$oauth2MetadataUrl`. Two of them —
`Components.pathItems` and `Components.mediaTypes` — don't fit the existing component-key
pattern and need Q5 settled first.

Full audit (with spec citations), the phase breakdown, and Q5's two sketched options:
[`backlog/spec-3.2/README.md`](backlog/spec-3.2/README.md).

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

