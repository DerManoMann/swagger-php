# Archive

Full write-ups for finished [backlog](../backlog.md) entries — merged, or closed without a
change because the premise did not hold. The terse, one-line form of each stays in
backlog.md's "Where this stands"; this is where the reasoning behind them lives once they stop
being work left to do. Kept rather than deleted for the same reason the rest of the
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

### PR 11 — audit data providers that construct objects — **done, no change needed**

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

### PR 26 — features and quirks classic handles that spec does not — **done**

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

Live, but only via an explicit component key — **superseded by slice 2, read that instead**:

- **JSON-pointer ref encoding.** `Annotations\Components::refEncode()`/`refDecode()` escape
  `~` → `~0` and `/` → `~1`; spec has no equivalent anywhere. Filed here as latent, on the
  reasoning that spec component names are class-derived and so contain neither character.
  Slice 2 then verified it with an explicit `schema: 'Odd/Name~With'`, which spec emits
  unescaped and malformed with no warning. Load-bearing the moment `#/paths/...` refs or
  `components.mediaTypes`/`pathItems` land — **Q5** and PR 22 Phase 4.

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

**Ref escaping is missing, and it outlives classic.** Classic's `Components::ref()` escapes
component names by default (`refEncode`: `~` → `~0`, `/` → `~1`); spec's `ComponentIndex`
builds `#/components/{bucket}/{name}` by concatenation with no escaping. Verified — a schema
declared `#[OA\Schema(schema: 'Odd/Name~With')]` emits:

```
$ref: "#/components/schemas/Odd/Name~With"     # spec, malformed, no warning
$ref: "#/components/schemas/Odd~1Name~0With"   # classic, correct
```

A consumer resolving spec's version looks for `components → schemas → Odd → Name~With` and
finds nothing. `Augmenter\Cleanup` builds the same unescaped string for its used-ref lookup,
so the two agree with each other and neither notices. This is **the one ref finding that
survives v8**, and it is the machinery `components.pathItems`/`mediaTypes` will need — see
**Q5**.

**Pointer resolution is classic-only and goes away.** `Annotations\OpenApi::ref()` resolves
arbitrary `#/...` pointers — `RefTest` covers `#/info`,
`#/paths/~1api~1~0~1endpoint/post/responses/default`, and refs *into* `x-` vendor extension
values to arbitrary depth. Spec's `ComponentIndex::find()` handles
`#/components/{bucket}/{name}` only, splitting on the first slash and treating the remainder
as the name, so a deeper pointer silently returns null. That looked like a gap until the
consumers were checked: the resolver's only internal callers are
`Annotations\AbstractAnnotation::validate()` and `Processors\AugmentMediaType`, both classic
and both removed in v8, and spec already re-implements what it needs in `Augmenter\Refs` and
`ComponentIndex`. **Not a gap** — but the narrowness should be a deliberate choice rather than
an accident, since nothing warns when a deep pointer fails to resolve.

Taking the whole remainder as the name has a second consequence, found while landing #2155:
`find()` accepts a component name **either escaped or raw** — both
`#/components/schemas/Odd~1Name~0With` and `#/components/schemas/Odd/Name~With` resolve, since
the unescaped slash just ends up inside the name it splits out. Lenient rather than designed.
Pinned by a test in `ComponentIndexTest` so that tightening it is a deliberate change, and
worth settling in the same pass as the deep-pointer question: both come down to how strictly
this parses.

**Deserialization is classic-only and goes away.** `Serializer::deserialize()` and
`deserializeFile()` return `Annotations\AbstractAnnotation`; `SerializerTest` covers a
Petstore round trip and `allOf` deserialization. Decided 2026-09-02: `Serializer` is classic
code, deprecated in v7 and removed with the rest in v8. No spec successor is planned, and
document → objects is not a direction the spec pipeline takes. **Not a gap.**

Related asymmetry, benign: `Augmenter\Refs` line 144 emits
`#/components/schemas/{name}/allOf/{index}/{path}` — a deep pointer the spec pipeline cannot
itself resolve. Emitting is fine, the consumer resolves it; the directions simply are not
symmetric.

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

#### Slice 3 — `tests/Processors/` (19 files), done 2026-09-02

**Ten spec attribute classes can target nothing.** They declare
`#[\Attribute(\Attribute::IS_REPEATABLE)]` with **no `TARGET_*` flag**. `IS_REPEATABLE` is
128 and `TARGET_ALL` is 127, so `flags = 128` sets zero target bits and PHP itself rejects the
attribute in every position:

```
#[OA\Header(header: 'X-Rate')] class H {}
→ Attribute "OpenApi\Spec\Header" cannot target class (allowed targets: )
```

Affected: `Header`, `Example`, `ServerVariable`, `Flow`, `Flow\{Implicit,AuthorizationCode,
Password,ClientCredentials}`, `MediaType\Json`, `MediaType\Xml`.

They work fine as **constructor arguments**, which is how every fixture uses them
(`content: new OA\MediaType\Json(...)`), so nothing fails today. What is impossible is
attribute position — the primary way users are told to write spec attributes.

Four things make this a defect rather than a design choice:

- **It is inconsistent inside one family.** `MediaType` is `flags=133`
  (`TARGET_CLASS|TARGET_METHOD|IS_REPEATABLE`); its own subclasses `Json` and `Xml` narrow to
  nothing. `Schema\Items`, the third shortcut handled by the same `Shortcuts` augmenter,
  declares four targets correctly.
- **It contradicts the documented model.** `docs/dev/pipeline.md` lists `Header`, `Example` and
  `MediaType` under "never root — these must nest inside a parent **or sit in a `Components`
  container**". Sitting in a `Components` container means attribute position.
- **It explains a known symptom.** `Spec/MediaType/Xml` sits at 0% line coverage (PR 11's
  table, and PR 12 names it as a wanted fixture). It is uncovered because it cannot be used as
  an attribute and no fixture instantiates it either.
- **It is the same family as #2137**, "the attributes nothing was compiling", so this class of
  mistake has already happened once and was fixed case by case.

**This is exactly what PR 8's open `ValidateRelationsTest` item would catch.** Classic asserts
its nesting map is bidirectional; the spec analogue would assert that every class declaring
`#[\Attribute]` can actually be targeted where its `contained()` slots say it belongs. An
invariant test finds all ten at once; reading tests one at a time found them by accident.
Argues for spending the remediation budget on invariants rather than cases.

Filtered out as classic mechanics — the pipeline's internal tree-merging, which spec does
differently by construction: `CleanUnmerged` (spec uses Assembler orphan validation, #2137),
`MergeIntoComponents` and `MergeIntoOpenApi` (Assembler/Compiler), `AugmentSchemas`,
`AugmentRequestBody` and `AugmentRefs` (`Names`/`Types`/`Refs` — and `RefsTest` at 122 lines
already exceeds `AugmentRefsTest` at 41), `CleanUnusedComponentsPerformance`
(`CleanupPerformanceTest` exists).

Parity confirmed, no action. The config surface lines up almost exactly —
`$whitelist`/`$withDescription` (`Tags`), `$enumNames` (`Enums`), `$hash` (`OperationIds`),
`$enabled` (`Cleanup`), `$tags`/`$paths` (`PathFilter`) — plus:

- **tag whitelist `'*'` wildcard** — implemented, `Tags.php:101`
- **`@param` descriptions onto operation parameters** — `Docblocks` reads them
- **enum class-strings and `UnitEnum` instances in an enum array** — handled by `Enums`
- **path merging**, two operations on one path — `compilePaths()` does `$paths[$path] ??= []`
- **allOf composition on inheritance** — `Inheritance\Schemas::addAllOfRef()`

One config difference, not a defect: classic's
`AugmentParameters::$augmentOperationParameters` toggles reading operation-docblock `@param`
descriptions. Spec does it unconditionally, with no off switch. Fewer knobs, and nothing has
asked for the toggle.

How the bug was found, which is worth repeating: probing classic's
`MergeJsonContentTest::testNoParent` — a *diagnostics* test, chosen because slice 1's theme
said diagnostics is where gaps live — meant writing `#[OA\MediaType\Json]` in attribute
position for the first time, and it threw. The classic test that led there turned out to be
about something else entirely.

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

**Expect each finding to lead somewhere else.** Two of the four gaps turned into different
bugs once probed. Chasing the missing `operationId` check found `SpecificationWalker::visit()`
starting a fresh `SplObjectStorage` per bucket, so **every** `visit()` caller could see an
attribute twice — including the request-body diagnostic that has been there all along. Chasing
the same check found PR 28. Both were invisible for the same reason as #2153's ten classes: no
fixture exercised the path. That is now three defects behind one blind spot, which says more
about where to spend test effort than any coverage number has.

**Then filter for what outlives classic.** Slice 2 produced three findings that looked like
gaps and were not: deserialization, arbitrary pointer resolution, and `x-`-extension ref
traversal are all classic-only, going away in v7/v8. The test to apply before filing anything
is *"who consumes this, and do they survive v8?"* — the pointer resolver's only callers are
`AbstractAnnotation::validate()` and `AugmentMediaType`, both classic. What survived that
filter in slice 2 was one thing, ref escaping, and it is the one worth acting on. Classic's
test suite describes the *classic* pipeline, so a behaviour being tested there is not by
itself evidence that spec needs it.

Also: `Augmenter\Cleanup` drops unreferenced components, so a probe fixture whose schema is
not referenced by an operation produces an empty document and looks like a collection failure.

#### Where this leaves it

**None — the audit is complete.** All three slices are done.

Findings to act on, in the order they are worth doing:

1. ~~**Ten attributes with no `TARGET_*` flag**~~ — **done, #2153**, with `AttributeTargetsTest`
   as the invariant that prevents the eleventh. Targets were taken per class from the
   containers it nests into rather than pasted as `TARGET_ALL`.
2. **The spec `ValidateRelations` analogue** (PR 8). #2153 covers the narrow version — every
   attribute declares a target, and a subclass keeps one of its parent's. The full
   bidirectional check is still open.
2b. **Sibling merge depends on declaration order** — found while landing #2153, now **PR 27**.
3. ~~**Three input-validation gaps**~~ — **#2154**, open: duplicate explicit `operationId`,
   invalid response code, invalid schema `type`. Carries the `SpecificationWalker` fix, and
   produced PR 28.
4. **Ref escaping** (slice 2). Needed on its own merits and a prerequisite for Q5 /
   PR 22 Phase 4.

Also settled along the way: **Q3** is answerable from the five `-spec.yaml` overrides, and
`Serializer` / arbitrary pointer resolution / `x-`-extension ref traversal are classic-only
and correctly absent.

Output goes to spec-side assertions in the existing per-augmenter tests and to compiler
`validate()` rules — **not** to new `Scratch` fixtures. That directory is 210 flat files
across 39 families whose entire `-spec`/anchor naming scheme is dual-pipeline scaffolding:
at v8 the 39 classic anchors go, the 5 `-spec.yaml` overrides become the only expectation,
and `mostSpecific()` loses three of its five rungs. Restructure it then, when there is one
pipeline and the right shape is obvious; adding to it now organises around a convention that
is about to evaporate. This also caveats PR 12, whose premise is "write more scratch
fixtures".

---
