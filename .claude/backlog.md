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
**#2145** (`TypedList::clear()`, PR 21), **#2146** (`#[Config]` attribute, PR 1, plus the
PR description template), **#2147** (`AssertsSpecEquals` extracted and
`AssertsBuilderResult` retired, part of PR 10), **#2148** (`ExpectsLogEntries`, the rest of
PR 10's logger half), **#2151** (phpstan pinned, PR 19) and **#2152** (`$argv` guard, out of
PR 19), **#2150** (`Undefined::UNDEFINED` on every `mixed` property, the narrow half of
PR 8's null question).

phpstan now covers `tools/` as of #2141, so the doc generators have static analysis for the
first time. pcov is installed locally and CI runs `--coverage-text`, so coverage numbers are
real rather than inferred; the current baseline and what it does and does not mean are in
PR 11, in one place rather than restated here.

### The goal, as of 2026-09-02

**Make the spec pipeline as good as it can be at what it already does**, rather than
widening what it covers. Three strands: finish the test migration as far as it will go,
find behaviour classic tests assert and spec tests do not, and get the documentation right.

This displaced the previous order, which had 3.2 field coverage in the middle of it. **PR 22
and PR 25 are parked** — see PR 22 for the reasoning, which is worth reading before either
is picked up again, because it inverts their dependency.

Suggested order:

1. **PR 26** — features and quirks classic handles that spec does not. The largest unknown,
   and the reason this order exists. **Slices 1 and 2 are done** — three live validation gaps,
   no JSON-pointer resolution, and no deserialization. Only `tests/Processors/` is left.
2. **PR 15** — mode-aware `ScratchTest` log keys. Before the new fixtures, not after: the key
   shape is what every new fixture's `$expectedLogs` inherits.
3. **PR 10** — finish the extraction, as far as it goes. Early, so the tests PR 26 produces
   are written on the traits rather than on `OpenApiTestCase`.
4. **PR 8** + **PR 12** — remediation, from what PR 26 and the coverage baseline in PR 11
   actually show.
5. **PR 14** — docblocks in `src/Spec/`. The most user-facing documentation work here: those
   docblocks are spliced into `reference/spec-attributes.md`, so an imprecise one ships as
   published documentation.
6. **PR 7** — augmenter config on two pages. Small, independent, do it in passing.
7. **PR 3** — `composer docs:check`. After the content work under this goal, not before:
   it locks down documentation that has just been made correct rather than guarding
   documentation about to be rewritten.
8. **PR 20's extension points page only** — the largest remaining documentation gap, since
   nothing under `docs/` addresses integrators. The Nelmio proof of concept stays parked;
   it is outward-facing and a separate decision.

**PR 11 is closed** — measured 2026-09-02, no change needed, and its entry now carries the
coverage baseline the rest of this list should work from.

**PR 6** stays conditional on PR 3. **PR 16**, **PR 17**, **PR 18**, **PR 23** and **PR 24**
are all orthogonal to this goal; 24 is cheap enough to fold into anything already touching
CONTRIBUTING.

Q3 revisits when spec stops being beta (v7); Q4 when classic is removed (v8). Q5 is parked
with PR 22.

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

**Q3. Are the classic-vs-spec output differences real?** — DEFERRED (2026-08-28), now
answerable
Leave the hedged claims for now; spec mode is still optional/beta. Revisit before spec
becomes the default.

The evidence turns out to exist already and to be cheap to read: `ScratchTest` compares all
three modes against one shared expected document unless a `-spec.yaml` override exists, so
the five overrides in `tests/Fixtures/Scratch/` **are** the list of real divergences —
`Auth`, `DuplicateRef`, `MergeTraitsExtended`, `NullRef` (3.1/3.2 only) and
`MultiTypeProperty` (type-info resolver only). The other 34 families assert byte-identical
output. Reading those five diffs settles this without a survey; see PR 26.

**Q4. How much of `docs/adr/` is actually an ADR?** — DEFERRED (2026-08-28)
Written as a reference/experiment to see if the format was useful. Both files describe the
*classic* pipeline only, so they stay as-is and serve as reference until classic is removed.
Condensing them into LLM context is acceptable in principle, just not now.

**Q5. How does a reusable, `$ref`-able `PathItem` or `MediaType` get a component identity?** —
PARKED (2026-09-02, opened 2026-09-01)
Parked with PR 22 rather than answered — it only ever mattered as a blocker on that entry's
Phase 4, and nothing else waits on it.
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

~~Worth adding `--coverage-text` to a CI workflow so the number is visible per PR rather than
measured ad hoc.~~ Already there — `build.yml` sets `coverage: pcov` and runs
`composer test -- --coverage-text` (found while closing PR 11, which carries the numbers).

Already closed by #2137: `ComponentIndex`, slot-target validation, and the
attributes nothing was compiling.

Still open:

- **`ValidateRelationsTest` has no full spec analogue.** Classic asserts its nesting map is
  bidirectional — if A names B as a parent, B must name A as nested. The spec side now
  checks that slots name real properties, but not the reverse direction: that a class
  claiming it can nest into a parent is one the parent will actually accept.
- **The `null` vs `Undefined::UNDEFINED` convention is part decided.** Classic has
  `AnnotationPropertiesDefinedTest` asserting no property defaults to `null`. Spec is
  deliberately mixed, and the reasoning survived only as a comment in `Augmenter/Types.php`
  ("Can't use `??=` here — const defaults to `Undefined::UNDEFINED`, not null").

  The narrow half is settled and now written up in `docs/dev/pipeline.md` under "`null` means
  unset, except where `null` is a value": a `mixed` property defaults to
  `Undefined::UNDEFINED`, because `null` is a legal value for it and so cannot also mean "not
  set". That was already what 12 of the 14 `mixed` properties in `src/Spec/` did — it just had
  nowhere to be read. The two deviations were `Example::$value` and `Link::$requestBody`.

  **Done in #2150**, and it corrected a claim this entry previously made. Aligning the two was
  described here as changing no output "since `filter()` drops `null` and
  `Undefined::UNDEFINED` alike". It does change output, and that is the point: `filter()`
  strips `null`, `Undefined::UNDEFINED` **and `[]`**, so a field left inside the `filter()`
  call cannot emit an explicit `null` or an explicit empty array — both legal values for a
  `mixed` property. Emitting them takes a branch outside `filter()`, which is what
  `compileSchema()` already did for `default`, `const` and `example`; #2150 made
  `compileExample()` and `compileLink()` match. Reverting either branch fails a test in each
  direction.

  What is still open is the wider invariant classic asserts — whether *nullable* properties
  should also avoid a `null` default. Only that needs deciding; the narrow rule is asserted
  by `UndefinedDefaultsTest` as of #2150.
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

**Partly done. Un-paused 2026-09-02** — it is item 4 in the current order.

#2147 extracted `AssertsSpecEquals` and retired `AssertsBuilderResult`; #2148 added
`ExpectsLogEntries`, which covers the logger members. `ScratchTest` is now two small
extractions (`fixture()`, `getTypeResolvers()`) from dropping the base class.

The earlier decision was to stop there, on the grounds that migrating existing tests earns
little while `OpenApiTestCase` still has to exist. What changed is PR 26: an audit that
produces a batch of new spec tests wants the trait vocabulary in place first, or those tests
get written against the base class and have to be migrated later anyway.

"As far as possible" is the shape of it, and the ceiling is known. `ScratchTest` can reach
`extends TestCase`. `BuilderTest` cannot — it needs `getAnalyzer()` and `getTypeResolver()`
for its classic-mode cases, and `getAnalyzer()` is classic and should stay behind. Finishing
means `ScratchTest` converted and `BuilderTest` left as the single deliberate holdout, not
`OpenApiTestCase` deleted.

The event-subsystem approach `origin/expexts-logger-contains` explored was investigated and
rejected — that branch can be dropped. Full plan, the member table, the log-mechanism
comparison, and what the PHPUnit event measurements showed:
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

**Done 2026-09-02 — no change needed.** Measured rather than reasoned, and the premise does
not hold: nothing in `src/` loses coverage attribution to a data provider. The whole entry
closes without a PR.

- **Fifteen providers construct objects**, not thirteen. The list above is right as far as it
  goes and misses `ResolverTest::fullBuildProvider` and `OpenApiTestCase::getTypeResolvers`.
- **No class is left uncovered by it.** Every class at 0% line coverage is explained by
  something else (below), and the two candidates a static scan flagged as provider-only —
  `Processors\OperationId` and `Type\LegacyTypeResolver` — have constructor execution counts
  of 414 and 257. Both are built indirectly by the code under test, through `Generator`'s
  default processor pipeline, which a `new X(` scan cannot see. That is the flaw in the
  static heuristic, and the reason the conclusion here rests on the coverage run rather than
  on the scan.
- **`--coverage-text` in CI was already done.** `build.yml` sets `coverage: pcov` and runs
  `composer test -- --coverage-text`, with `phpunit.xml.dist` scoping `<source>` to `src`. The
  number has been visible per PR all along; PR 8's suggestion to add it is stale.

Why #2137 was different: `UncoveredAttributesTest` existed *only* to construct those DTOs, so
the provider was genuinely the sole construction site. That is a property of a test written to
touch otherwise-unreached classes, not of data providers generally.

The canary earned its place again, and the detector broke a second time with a new cause: the
scan ran against a `git archive` export, and `.gitattributes` marks `tests/` `export-ignore`,
so it read a tree with no tests in it and reported zero matches — the same output as the first
broken detector, for an unrelated reason. Keep the canary; do not trust the detector.

**Baseline at 2026-09-02**, after #2150-#2152, project-wide: lines 92.20% (5926/6427),
methods 76.17%, classes 61.98%.

**What the run did find**, none of it about providers:

| At 0% | Statements | Why |
| --- | --- | --- |
| `Console/GenerateCommand` | 52 | `CommandlineTest` shells out via `exec()`, so seven real tests run it in a subprocess and none of it is attributed |
| `Spec/MediaType/Xml` | 9 | genuinely unexercised — PR 12 already names it |
| `Attributes/ServerVariable` | 9 | classic |
| `Annotations/MediaType` | 6 | classic |
| `Console/GenerateInput`, `Console/GenerateFormat`, `Builder/Mode` | 5, 3, 3 | small value types |
| `Annotations/Attachable` | 1 | classic |

Under 70%: `Processors/Concerns/RefTrait` 33.3%, `Annotations/PathItem` 44.4%,
`Loggers/DefaultLogger` 50%, `Annotations/Parameter` 62.5%, `Utils/SourceLocation` 66.7%
(46/69) — the last already named in PR 8 as having no direct test.

**`Console/GenerateCommand` is the same symptom one level up**, and is now the largest
misleading number in the report: 52 statements reading as untested while seven tests exercise
them. Anyone reading the coverage report will see it as the biggest gap in `src/` and it is
not one. Making it visible means either running the CLI in-process or accepting the number and
writing it down — worth deciding, since the alternative is someone redundantly testing a
covered command. This is the honest version of what PR 11 was looking for.

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
- **A spec-only fixture still needs a classic file.** Discovery globs `Scratch/*.php` and
  skips `-spec` names, so `Foo-spec.php` is only found if `Foo.php` exists. With no
  `Foo3.x.y.yaml` or `-classic.yaml`, every classic combination skips on `$spec === null`
  and the anchor is never loaded — it exists purely so the glob finds the pair.
  `Scratch/Spec32.php` is the first of these, and says so in a comment. Worth deciding
  whether discovery should key off `-spec.php` directly instead, which would remove the
  need for the anchor entirely.
- **Document-level attributes need their own class.** `Server::merge()` targets both
  `PathItem` and `Operation`, so a `Server` sitting alongside two `Operation\Get`
  attributes on one class fails with `Ambiguous merge: OpenApi\Spec\Server matches
  multiple siblings on the same target`. Put `OpenApi`/`Info`/`Server` on a class of their
  own. The message names the cause, but only after the fixture already looks finished.
- **The fixture is the redocly coverage.** `composer redocly` lints `Scratch/*.yaml`, so a
  fixture is the only thing in the suite that checks emitted documents against the OpenAPI
  schema. Two rules bite in practice: `no-invalid-media-type-examples` validates `value`
  against the media type's schema (it does not know `dataValue`/`serializedValue`), and
  `path-parameters-defined` wants a `Parameter\Path` for every `{brace}` in the path.

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

`ExpectsLogEntries` (#2148) is half of this: it supplies the `expectLogEntry()` /
`allowLogEntry()` split that names the distinction honestly, and it drops the FIFO ordering
so declaration order stops mattering. It does **not** change the key, so the three
tolerances above only become assertions once the mode component lands as well — do both in
one change.

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

**PARKED (2026-09-02).** #2149 is a draft and stays one. Two reasons, and the second is the
one that matters:

- The branch is a half-done phase. Phase 1a landed, Phase 1b needs a mechanism that does not
  exist, and merging the half would leave 3.2 support that is neither absent nor complete —
  the hardest state to reason about later.
- **Fields that need retrofitting later should not be merged now.** Every field Phase 1a adds
  is one PR 25 would then have to go back and annotate. Adding work for a mechanism that is
  already specified, in order to ship a partial version of the feature that mechanism exists
  to support, is the wrong order.

**This inverts the dependency between PR 22 and PR 25.** This entry previously read that
PR 25 "blocks PR 22's Phase 1b", i.e. a follow-up that unlocks one sub-phase. It is now a
**precondition for the whole entry**: no 3.2 field lands until the drop-diagnostic mechanism
is in place, so new fields declare their version as they are written rather than being
annotated retroactively. PR 25's own entry says the same thing from the other side — the
twentieth field is the one nobody remembers.

Q5 is parked with this, since Phase 4 was the only thing waiting on it.

Nothing here is abandoned, and the audit below is the expensive part — it stays valid. What
is parked is the merging, not the finding.

**One piece was rescued.** The branch's first two commits were the `Undefined::UNDEFINED`
convention, which touches no 3.2 field and never opens `OpenApi32Compiler`. Split to
`refactor/undefined-defaults` and opened as #2150, where it belongs to PR 8. Worth checking
for that before parking a branch: the base of a stack is often independent of what sits on
top of it.

---

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

**Phase 1 is half done** on `feat/spec-3.2-fields`, unmerged: `OpenApi::$self`,
`Server::$name`, `Response::$summary`, `Example::$dataValue`/`$serializedValue` and
`Security\Scheme::$deprecated`/`$oauth2MetadataUrl`, each a plain property on the DTO plus an
`OpenApi32Compiler` override, with the Example Object's mutual-exclusion rules as a
validation warning. What is left of Phase 1 needs more than a property, which is why it was
split off: `MediaType::$itemSchema` and the `itemEncoding`/`prefixEncoding` pair are
object-valued and so raise the slot-target question, and the `deviceAuthorization` flow and
`cookie` style add enum values, which is a per-version validity question rather than a
compiler emit.

The compiler's class docblock is deliberately not an inventory of which fields 3.2 adds —
having a compiler per version is what records that. The remaining work is tracked here
instead.

Phase 1a's tests started as fourteen `CompilerTest` cases pairing "3.2 emits" with "3.1
omits", and were folded into a `Scratch/Spec32` fixture instead: one source, three expected
documents, and the version matrix asserts the omissions for free. That cut 207 lines of test
code to 10 and put the emitted documents under `composer redocly`, which is the only thing
that would have caught `MediaType::$description`. Only two cases could not move — the
`$self` key position, because `assertSpecEquals` compares maps order-independently, and the
mutual-exclusion warnings, which need a document redocly rejects. Both stayed in
`CompilerTest` with a docblock saying why. Prefer a fixture for the next batch of fields.

Re-deriving the audit from the published JSON Schemas (diffing
[3.1](https://spec.openapis.org/oas/3.1/schema/2022-10-07) against
[3.2](https://spec.openapis.org/oas/3.2/schema/2025-09-17), then checking each hit against
the prose) corrected it twice, and both are worth keeping:

- **`Response::$summary` and `Security\Scheme::$deprecated` were missed.** Both are real
  3.2 additions — §4.17.1 and §4.27.1 — and are in the branch above.
- **`MediaType::$description` is in the 3.2 JSON Schema but not in the spec.** The Media Type
  Object's fixed-field table (§4.14.1) does not list it. It was implemented, `redocly lint`
  rejected it, and the prose backs redocly, so it was backed out — the schema is wrong. Worth
  knowing before someone re-derives the same field and assumes redocly is behind.

Two lessons about method, for whoever picks up Phases 2-4: the JSON Schema diff is a better
starting point than reading the prose (it found two fields the read-through missed), and the
prose is the tiebreak when they disagree. `redocly lint` on a compiled document is the
cheapest confirmation that the output is actually accepted — it was already right about
`MediaType::$description`, and it accepted every other field on the branch.

Full audit (with spec citations), the phase breakdown, and Q5's two sketched options:
[`backlog/spec-3.2/README.md`](backlog/spec-3.2/README.md).

### PR 24 — nothing says what a commit message should contain

`.github/PULL_REQUEST_TEMPLATE.md` and CONTRIBUTING's "Pull request titles follow
`type(Scope): subject`" both govern pull requests. The only rule touching commits is one
line in `AGENTS.md` — the same `type(Scope): subject` shape and the list of allowed types —
and it says nothing about bodies.

That is a gap rather than a deliberate omission, because CONTRIBUTING leans on the commit
trail without ever describing it: "History that lives elsewhere — earlier attempts,
abandoned branches, related work in other pull requests — belongs in the issue or commit
trail, not here." Material is being routed somewhere the conventions never define.

The PR template's own guidance transfers almost unchanged — why the change exists rather
than how, identifiers in backticks, no code snippets, keep to the change at hand — so this
is a short addition to CONTRIBUTING next to the PR paragraph, not a new document.

Came up while splitting the `Undefined::UNDEFINED` work out of `feat/spec-3.2-fields`:
writing the message meant guessing at a convention that only exists for PR descriptions.

### PR 25 — `#[Since]`: declare the version a field arrived in, where it can be used

**Parked with PR 22 (2026-09-02), but promoted within it.** It is no longer a follow-up that
unlocks Phase 1b — it is the precondition for any 3.2 field landing at all, because the
decision not to merge fields needing later retrofitting means the mechanism has to exist
before the fields do. So when 3.2 work resumes, this is where it starts.

That ordering also makes the entry cheaper than it looks. The nineteen silent drops it closes
have nothing to do with 3.2 and stand on their own; doing it first means Phase 1a's seven
fields are written with `#[Since]` from the start rather than annotated afterwards.

The work below is unchanged. Note the prior-art review and the JSON Schema alternative are
still unstarted — those come before writing any attribute, not after.

**Nothing should disappear from a document without saying so.** That is the principle; the
compilers are about half way to it. Compiling a `Schema` with every keyword set at 3.0 and
diffing against 3.1 shows six keywords warn and nine vanish in silence:

| Warns | Silent |
| --- | --- |
| `prefixItems`, `unevaluatedItems`, `unevaluatedProperties`, `if`/`then`/`else`, `examples`, `const` | `contentMediaType`, `contentEncoding`, `contains`, `minContains`, `maxContains`, `patternProperties`, `propertyNames`, `dependentRequired`, `dependentSchemas` |

Add `Tag::$summary`/`$parent`/`$kind`, silent in both 3.0 and 3.1 since they landed, and the
seven fields PR 22 Phase 1a added the same way, and **nineteen fields drop silently today**.

Hand-writing nineteen checks would match the precedent in
`OpenApi30Compiler::validateSchemas()`, and scale exactly as badly as PR 15 documented:
#2137 added a diagnostic, #2138 seeded the ignore list, both were green alone and only the
merged state failed. The twentieth field is the one nobody remembers.

**The mechanism to copy is `#[Config]` (#2146)** — a declarative attribute on a constructor
parameter, plus a static reflection helper, driving behaviour the code does not restate.
`#[Since('3.2.0')]` in the same shape pays off three times:

1. **Diagnostics.** One check in the base compiler warns for any set property the target
   version cannot carry. New fields get their warning by declaring themselves.
2. **Generated docs.** `reference/spec-attributes.md` is spliced from these docblocks, so
   the version a field arrived in can be rendered per parameter instead of being invisible.
3. **Retiring the prose form.** Seven `@param` lines already carry a hand-written `(3.2+)`
   and `Tag::$parent`/`$kind` carry nothing — the fact is restated, inconsistently, in the
   one place PR 3 says restated facts go to rot.

Enum cases can carry attributes too, so `ParameterStyle::Cookie`,
`FlowType::DeviceAuthorization` and `HttpMethod::Query` are covered. That is what makes
PR 22's Phase 1b tractable: its awkwardness was that those are *values*, not fields, with
nowhere to hang the rule.

#### Coverage is partial by design, and that is the important part

A spec attribute is not a wire-format record. The DTO models the domain once — the spec
way — and how that serializes per version is the compiler's separate question. So a set
field has **three** possible fates, not two:

| Fate | Examples | Who decides |
| --- | --- | --- |
| **Emitted** | most fields | the compiler's field list |
| **Translated** | `nullable` → `type: [x, 'null']`, `const` → `enum: [c]`, `examples` → `example`, boolean vs numeric `exclusiveMinimum` | hand-written per-version code |
| **Dropped** | the nineteen above | nothing, today |

`#[Since]` only speaks to the emitted/dropped boundary. It says *"the wire format gained
this field in version X"*, not *"you may not set this property below version X"* —
`Schema::$nullable` is settable at every version and means something at every version; only
its serialization moves. Marking it `#[Since]` anything would be wrong, and marking `const`
`#[Since('3.1.0')]` would produce a "dropped" warning for a field that is in fact
translated.

So the attribute should cover **only fields that need no special handling**, and everything
translated keeps its hand-written rule and its hand-written documentation. Partial coverage
is the correct outcome here, not a shortfall — the alternative is forcing a declarative
mechanism to describe translations, which is where the can of worms is.

Which means the drift test below needs an explicit "handled elsewhere" list. That list is
worth having for its own sake: **the set of translated fields is currently written down
nowhere**, only inferable by reading three compilers side by side.

**The drift test.** Populate every field on a DTO, compile at each version, assert nothing
absent from the output passed without a diagnostic — with the translated fields exempted by
name. Roughly twenty lines; the table above came from exactly that script, run ad hoc.

#### Look for prior art first

Versioned models with per-version serialization is not a new problem, and none of the leads
below have been checked — they are where to start, not findings:

- **JMS Serializer** (PHP) has `Since`/`Until` on properties with a version-aware exclusion
  strategy. Closest thing to this proposal in the same language, and worth reading for the
  parts that are not obvious: how the version is threaded through, whether both ends were
  actually needed, and what it does about fields that need transforming rather than
  including or excluding.
- **Spectral** rulesets declare applicability per format (`oas2`, `oas3_0`, `oas3_1`), and
  **Redocly**'s `struct` rule knows which fields each version allows — it is what rejected
  `MediaType::$description`. Both express "which field, which version" as data rather than
  as code, and Redocly is already in `node_modules/`, so it costs nothing to look.
- **Protobuf**'s `reserved` and deprecation handling is the same shape one step further
  along, and is the obvious place to check whether a "removed in" axis earns its keep.

There is also a **structural alternative worth weighing before writing any attribute**: the
OpenAPI Initiative publishes a machine-readable JSON Schema per version, and diffing two of
them is exactly how the audit table in this entry was produced. Deriving the
emitted-versus-dropped boundary from those schemas would need no annotation on the DTOs at
all. Against it: they describe the wire format only, so translated fields still need
hand-written rules either way; and they would have to be vendored and pinned, which is a
dependency on someone else's release cadence. But it removes the burden of remembering to
annotate, which is the whole failure mode this entry exists to prevent — so it deserves a
fair hearing rather than being dismissed for being less idiomatic.

Open before starting:

- **Are ranges needed?** Only for a field *removed* from the wire format and not translated
  in its place. There is no such case today — 3.2 drops `Link.body`, which the DTO never
  had. Start with `#[Since]` alone and add the other end when something actually needs it.
- **Should `Example::$dataValue` be translated rather than dropped?** Phase 1a dropped it
  for 3.0 and 3.1 by fiat, but `value` is the older field for the same thing, so translating
  is arguable. A question about that field, not about the mechanism — but the mechanism
  makes the choice explicit rather than accidental.
- **Warning volume.** Diagnostics are per-occurrence, so a 3.0 document with 200 schemas
  using `contains` gets 200 lines. Consistent, but worth choosing rather than discovering.
- **Fixture churn.** Nineteen new warnings move existing `$expectedLogs`. `Scratch/Spec32`
  alone would emit fourteen, though being spec-only it dodges PR 15's mode-key problem.

Reflection cost is not among the worries: the map is built once per run and cacheable per
class, the way `Config::forConstructor()` already is.

Prompted by the question of whether Phase 1b's new enum values should warn or drop silently.
The answer is warn — and so should the eighteen fields that already do not.

### PR 23 — audit where classes ended up after the `Utils/` migration

#2039 moved `TokenScanner` and others into `OpenApi\Utils`, and it has been the default
landing spot for anything that isn't obviously `Spec\`/`Attributes\` vocabulary ever since.
Some of what's there is genuinely general-purpose (`TypedList`, `TokenScanner`,
`SourceLocation`), and some looks like it belongs to a subsystem that already has its own
directory:

- `AttributeFactory` — assembly-time attribute translation, driven by
  `AttributeTranslatorInterface` implementations; `src/Assembler/` already exists for exactly
  this (`AbstractAttributeTranslator`, `DefaultAttributeTranslator`,
  `OptionalPropertyAttributeTranslator`)
- `PipeInterface` — the augmenter extension-point interface `Builder::withAugmenters()`
  accepts. `src/Contracts/` holds every other public extension-point interface
  (`AttributeInterface`, `AttributeTranslatorInterface`, `CompilerInterface`,
  `ResolverInterface`); `PipeInterface` reads like the odd one out
- `SpecificationWalker` — walks a `Specification`; `src/Specification/` already exists for
  its collaborators (`ComponentIndex`)
- `CollectingLogger` — `src/Loggers/` already exists (`DefaultLogger`) and holds none of the
  other logger implementations

Not a decision, just a survey worth doing before the next class has to land in `Utils/` by
default because nobody checked whether it has a better home — which is how this came up:
picking a home for `Config` (PR 1) required reasoning it through from scratch instead of
following a clear convention.

Each move is small in isolation (rename, fix imports, likely a deprecated shim at the old
location the way `src/Pipeline.php` and `src/SourceFinder.php` already alias into `Utils/` —
several of these classes are used by downstream code, per the extension-points table in
PR 20) but touches call sites across `src/` and `tools/`, so worth batching into one pass
rather than doing it ad hoc mid unrelated PRs.

### PR 26 — features and quirks classic handles that spec does not

**Not a coverage exercise.** Line coverage is 92.2% and fine (PR 11). Nor is it about test
counts. Classic's unit tests are the closest thing this project has to a written
specification of edge-case behaviour — each one exists because somebody hit something. The
question is which of those behaviours spec **does not have, or handles differently**, and for
each difference whether it is deliberate.

Two framings were tried and discarded first, both worth knowing so they are not retried:

- **Document-level parity is already covered, continuously.** `ScratchTest::mostSpecific()`
  falls back to a shared `{name}{version}.yaml` used by classic, hybrid *and* spec, so a
  fixture with no `-spec.yaml` override **asserts all three modes emit the same document**.
  34 of 39 families do exactly that. The five that diverge — `Auth`, `DuplicateRef`,
  `MergeTraitsExtended`, `NullRef` (3.1/3.2), `MultiTypeProperty` (type-info resolver) — are
  the catalogue of known divergence, in file form. Reading those five answers **Q3** with
  evidence that already exists on disk, and is the cheapest thing on this list.
- **Test-size comparison is noise.** An early pass compared classic and spec test line counts
  per augmenter (`Types` looked worst at 602 → 88). Then a full 24-scenario diff of
  `AugmentPropertiesTest`'s inference matrix through both pipelines came out **identical at
  3.0 and 3.1**. Feature parity there is complete; only the assertions are thin. Size proves
  nothing.

So the target is the **unit tests that assert DTO state directly**, not fixtures. That is
where the asymmetry lives: `tests/Annotations/` has 13 files of per-DTO behaviour tests,
`tests/Spec/` has 2 — and both of those are structural invariants
(`SlotMapConsistencyTest`, `UndefinedDefaultsTest`). Spec has **no per-DTO unit tests at all**.

#### The theme, which predicts the findings better than reading tests one at a time

Classic has 25 diagnostic sites, spec 18 — but of different kinds. Spec's are almost entirely
version capability (`not supported in OpenAPI 3.0`, `mutualTLS`, `webhooks`). Classic's also
include input validation: enum values, uniqueness, malformed codes.

> **Spec validates what the target version can carry. Classic also validates whether the user
> wrote something valid.**

Every gap below follows from that one sentence. Use it as the search heuristic for the
remaining slices.

Why none of this showed up sooner: `composer redocly` would reject all three live gaps, but
only inside a fixture — and nobody writes a fixture containing a typo. Document-level parity
testing structurally cannot find an input-validation gap.

#### Slice 1 — `tests/Annotations/` (13 files), done 2026-09-02

Live gaps, each reproduced with a real `Builder` run. All three emit a document that fails
OpenAPI validation while the pipeline reports success:

| Quirk | Classic | Spec |
| --- | --- | --- |
| Duplicate explicit `operationId` | warns, `validate()` false (`Annotations/Operation.php:231`) | silent, `isValid()` true, both emitted as `getItem` |
| Invalid response code — `Default`, `5xX`, `6XX` | warns (`Annotations/Operation.php:219`) | silent, emits `"Default"` as the response key |
| Invalid schema `type` — `strig` | warns | silent, emits `type: strig` |

Latent, not live:

- **JSON-pointer ref encoding.** `Annotations\Components::refEncode()`/`refDecode()` escape
  `~` → `~0` and `/` → `~1`; spec has no equivalent anywhere. Unreachable in normal use
  because spec component names are class-derived, so they contain neither character. Reachable
  via an explicit `schema: 'foo/bar'`, and load-bearing the moment `#/paths/...` refs or
  `components.mediaTypes`/`pathItems` land — which ties it to **Q5** and PR 22 Phase 4.

Generated operationIds are **not** at risk: `OperationIds::generateId()` returns
`METHOD::path::Class::method`, unique by construction. Only explicitly set ones collide.

Deliberate differences — spec makes the invalid state unrepresentable rather than warning
about it. **Record these; do not "restore parity" by adding checks that cannot fire:**

| Classic warns | Spec |
| --- | --- |
| `in="dunno"` | impossible — `Parameter\{Path,Query,Header,Cookie}` are separate classes |
| `example` and `examples` are mutually exclusive | impossible — no spec DTO carries both fields |

Already present in spec, no action: License `url`/`identifier` exclusion
(`OpenApi31Compiler.php:64`), version validation (stronger than classic — per-compiler
`VERSIONS` plus `supports()`), required `info`/`info.title`, orphan validation (#2137).

Not a gap but a visible behaviour difference: `OperationIds::$hash` defaults to **true**, so
spec emits md5 operationIds where classic emits readable ones.

#### Slice 2 — `AnalysisTest`, `ContextTest`, `SerializerTest`, `RefTest`, `tests/Utils/`, done 2026-09-02

**The big one: spec cannot resolve an arbitrary JSON pointer.**

`Annotations\OpenApi::ref()` resolves any `#/...` pointer against the annotation tree, and
`RefTest` exercises four shapes of it: `#/info`;
`#/paths/~1api~1~0~1endpoint/post/responses/default` — a path ref with **both** escapes;
`#/components/schemas/String/x-custom-key` — a ref *into a vendor extension value*; and
`#/components/schemas/String/x-custom-key/properties/value` — the same, one level deeper.

Spec's `ComponentIndex::find()` handles `#/components/{bucket}/{name}` and nothing else. It
splits on the first slash and treats the whole remainder as the component name, so a deeper
pointer looks up a component literally named `Foo/properties/bar`, finds nothing, and
**returns null with no diagnostic**. `#/paths/...` refs cannot be expressed or resolved at
all, and there is no unescaping of `~0`/`~1` anywhere in the spec pipeline.

This upgrades slice 1's ref-encoding finding from latent to real: `~0`/`~1` is not a
hypothetical, classic exercises it for path refs today. Both halves are the same missing
capability.

Related asymmetry, benign but worth knowing: `Augmenter\Refs` line 144 *emits*
`#/components/schemas/{name}/allOf/{index}/{path}` — a deep pointer the spec pipeline cannot
itself resolve. Emitting is fine, the consumer resolves it; the two directions simply are not
symmetric.

**No deserialization in spec.** `Serializer::deserialize()` and `deserializeFile()` return
`Annotations\AbstractAnnotation`, so document → objects exists only for classic.
`SerializerTest` covers a full Petstore round trip and `allOf` property deserialization.
Note `Serializer` has **zero consumers inside `src/`** — it is public API only, which cuts
both ways: nothing internal breaks without it, and downstream code may depend on it with no
spec-mode successor. A scope decision rather than a bug, but it should be a decision.

Parity confirmed, no action:

- **Class hierarchy.** `AnalysisTest` covers `getSubclasses`, `getAllAncestorClasses`,
  `getDirectAncestorClass`, `getInterfacesOfClass`, `getTraitsOfClass`.
  `Augmenter\Inheritance\Schemas` walks parents, traits and interfaces in one place — the
  work classic splits across `ExpandClasses`, `ExpandTraits` and `ExpandInterfaces`.
- **Docblock name resolution.** `ContextTest::testFullyQualifiedName` asserts classic resolves
  relative names, `use` statements and aliases. Verified empirically that spec does too:
  `@var Aliased`, `@var Models\Pet`, `@var \FqnSpec\Models\Pet` and `@var Aliased[]` all
  resolve to the right `$ref`. Reflection gives spec this for free where classic needed
  `Context::fullyQualifiedName()`.

**`tests/Utils/` is not a parity question.** Its eight subjects are shared or spec-side
(`AttributeFactory` and `Config` are used only by spec; `TypedList`, `TypeMapper`,
`SourceFinder`, `SourceScanner`, `Pipeline` are pipeline-agnostic; only `TokenScanner` has
classic callers). Nothing to compare — worth recording so the next pass skips it.

#### Method notes

Three false alarms, all hit in one sitting, all of which look like feature gaps:

1. **`#[OA\Property(type: 'integer')]` does not exist in spec** — the twin fixture would not
   instantiate. It is the documented design difference: `Property` does not extend `Schema`,
   so the type goes on a stacked `#[OA\Schema]`.
2. **Comparing `Result::toArray()` invents differences** — `[]` vs `{}` for empty schemas,
   which vanish at the serialized level where both emit `{ }`.
3. **Comparing at default versions invents more** — classic defaults to 3.0, spec to 3.1, so
   `nullable: true` vs `type: [string, "null"]` reads as divergence.

**Compare serialized output at a pinned version.** Anything else manufactures phantom gaps.

Also: `Augmenter\Cleanup` drops unreferenced components, so a probe fixture whose schema is
not referenced by an operation produces an empty document and looks like a collection failure.

#### Remaining slices

Only `tests/Processors/` is left — the per-processor behaviour tests, read against their
mapped augmenters. `tests/Annotations/AttributesSyncTest` is classic-internal
(annotations ↔ attributes) and has no spec meaning; `tests/Utils/` was checked and does not
apply (slice 2).

Output goes to spec-side assertions in the existing per-augmenter tests and to compiler
`validate()` rules — **not** to new `Scratch` fixtures. That directory is 210 flat files
across 39 families whose entire `-spec`/anchor naming scheme is dual-pipeline scaffolding:
at v8 the 39 classic anchors go, the 5 `-spec.yaml` overrides become the only expectation,
and `mostSpecific()` loses three of its five rungs. Restructure it then, when there is one
pipeline and the right shape is obvious; adding to it now organises around a convention that
is about to evaporate. This also caveats PR 12, whose premise is "write more scratch
fixtures".

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

