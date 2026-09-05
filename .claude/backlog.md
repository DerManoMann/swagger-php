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
PR 8's null question), **#2153** (attribute targets plus `AttributeTargetsTest`, PR 26's first
finding, and the writing-rules scope, PR 14's first half).

Since then: **#2154** (input validation plus the `SpecificationWalker` fix, PR 26),
**#2155** (ref escaping, PR 26), **#2156** (`Server`/`ServerVariable` summaries, PR 14),
**#2157** (`OpenApiTestCase` migrated to extracted traits, PR 10 finished),
**#2158** (`DocsAccuracyTest` and `composer docs:check`, PR 3),
**#2159** (inner-to-outer sibling merge, PR 27), **#2160** (mode-aware `ScratchTest` log
keys plus the duplicate 3.0 diagnostic it exposed, PR 15), **#2161** (level 6 type
annotations for the spec namespaces, PR 29), **#2162** (the method-level `Schema` crash and
the slot-type invariant that found it, PR 8), **#2163** (the significance-clause rule, and
the writing rules stated to cover commits), **#2164** (the stacked-attribute sweep, PR 12).

phpstan now covers `tools/` as of #2141, so the doc generators have static analysis for the
first time. pcov is installed locally and CI runs `--coverage-text`, so coverage numbers are
real rather than inferred; the current baseline and what it does and does not mean are in
PR 11 in [`backlog/archive.md`](backlog/archive.md), in one place rather than restated here.

### The goal, as of 2026-09-02

**Make the spec pipeline as good as it can be at what it already does**, rather than
widening what it covers. Three strands: finish the test migration as far as it will go,
find behaviour classic tests assert and spec tests do not, and get the documentation right.
The first strand finished with #2157 (PR 10); what is left of the other two is the order
below.

This displaced the previous order, which had 3.2 field coverage in the middle of it. **PR 22
and PR 25 are parked** — see PR 22 for the reasoning, which is worth reading before either
is picked up again, because it inverts their dependency.

Suggested order:

1. **PR 7** — augmenter config on two pages. Small, independent, do it in passing.
2. **PR 20's extension points page only** — the largest remaining documentation gap, since
   nothing under `docs/` addresses integrators. The Nelmio proof of concept stays parked;
   it is outward-facing and a separate decision.
3. **PR 8's last item** — whether a *nullable* property should also avoid a `null` default.
   A decision, not code; everything else in that entry is closed.

**PR 12** is ongoing by design — the next fixture comes from whatever the next coverage run
shows thin, and the entry carries the numbers and the mechanics.

**PR 6** was conditional on PR 3, which #2158 finished — its verify-first half now has a
home in `DocsAccuracyTest`, so what remains is the per-fragment verify-or-generate choice
in its entry. **PR 16**, **PR 17**, **PR 18**, **PR 23**, **PR 24** and **PR 29**
are all orthogonal to this goal; 24 is cheap enough to fold into anything already touching
CONTRIBUTING.

Q3 revisits when spec stops being beta (v7); Q4 when classic is removed (v8). **Q5 is live
again** — it governs `Response` in shipped code, not just PR 22's Phase 4.

Entries that need supporting material, or have grown too long for the numbered list, get a
folder under `.claude/backlog/`, named for the topic rather than the entry number so it
survives renumbering. So far: [`backlog/benchmarks/`](backlog/benchmarks/README.md), the
scripts behind every number in PR 16 and PR 17 — there so the measurements can be re-run
rather than trusted; [`backlog/performance/`](backlog/performance/README.md), PR 16 and
PR 17's own write-ups; [`backlog/testcase-concerns/`](backlog/testcase-concerns/README.md),
PR 10's; [`backlog/nelmio-poc/`](backlog/nelmio-poc/README.md), PR 20's; and
[`backlog/spec-3.2/`](backlog/spec-3.2/README.md), the full field-by-field audit behind
PR 22.

Entries move to [`backlog/archive.md`](backlog/archive.md) once finished, whether they merged
or closed without a change — the terse form stays in "Where this stands" above. Move an entry
when nothing in it is still work; the archive is linked, so cross-references keep resolving.

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
output. Reading those five diffs settles this without a survey; see PR 26 in
[`backlog/archive.md`](backlog/archive.md).

**Q4. How much of `docs/adr/` is actually an ADR?** — DEFERRED (2026-08-28)
Written as a reference/experiment to see if the format was useful. Both files describe the
*classic* pipeline only, so they stay as-is and serve as reference until classic is removed.
Condensing them into LLM context is acceptable in principle, just not now.

**Q5. How does a reusable, `$ref`-able `PathItem` or `MediaType` get a component identity?** —
REOPENED (2026-09-03; opened 2026-09-01, parked 2026-09-02)
Parked with PR 22 on the grounds that only that entry's Phase 4 waited on it. That turned out
to be wrong: the same question governs `Response` in shipped code and the four component-key
types' `isRoot()` implementations — see below. It is now a live design question independent of
3.2.
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

**No longer hypothetical, and no longer only about 3.2 (2026-09-03).** The shape Q5 describes
already ships in `Response`, and the four component-key types disagree with each other about
what it means. Found while adding response-key validation in #2154, which had to key off
position because the object could not be asked.

`Response::$response` is overloaded exactly the way `MediaType::$mediaType` is: it is the HTTP
status code when the response is nested in an operation, and the component name when the
response sits in the `responses` bucket. `isRoot()` cannot separate them — it only checks the
key is set. Two consequences, both verified:

- **A status-code response that fails to nest becomes a component named after the code.**
  Disable `Augmenter\Cleanup` and `components.responses.200` appears. **Classic does exactly
  the same** — `MergeIntoComponents` merges any non-nested annotation whose `$_parents`
  include `Components`. So the overload is inherited, and it originates in the document shape:
  the Response Object has no name of its own, its identity is always the key of the map holding
  it.
- **What spec changed is the discriminator, not the overload.** Classic recorded position as a
  fact and checked it (`_context->is('nested')`); spec infers rootness from field presence.
  That is the regression, and it is what a fix should restore.

Declaring both the key and `ref` then behaves four different ways:

| Declared on a class with key **and** `ref` | Result |
| --- | --- |
| `RequestBody(request: 'x', ref: …)` | `components.requestBodies.x = {$ref: …}`, accepted |
| `Response(response: 'x', ref: …)` | throws `Non-root attribute … remains after resolution` |
| `Parameter(parameter: 'x', ref: …)` | throws |
| `Link(link: 'x', ref: …)` | throws |

`RequestBody::isRoot()` is the only one without the `ref === null` clause, and
`docs/dev/pipeline.md` records that as a fact without a reason. It is tempting to call it the
odd one out and add the clause — but that is probably backwards:

- **`Response` needs the clause**, because key + `ref` is the *ordinary* pattern there:
  `responses: {200: {$ref: …}}` is `response: 200, ref: …`, which must nest rather than become
  a component. The clause is load-bearing precisely because the key doubles as a value.
- **`RequestBody` is right to omit it.** `$request` is documented as a component key and can
  never be a positional value, so key + `ref` can only mean "a component that aliases another",
  which is legal OpenAPI and what the code emits.
- **`Parameter` and `Link` have dedicated keys too** (`parameter`, `link`, distinct from
  `name` / `operationId`), so by that reasoning they should permit the alias as `RequestBody`
  does. They throw.

So three of the four are defensible alone and no two share a rule. The rule that would explain
all of them is **"does this key double as a value?"** — which is Q5's question, arriving in a
fourth place. Answering it settles, uniformly: whether key + `ref` is an alias or a
contradiction, what `PathItem` and `MediaType` need in order to be `$ref`-able, and whether
`Response` should ever have carried one field for two jobs.

Cheap and independent of the answer: **warn when a root `Response`'s key looks like a status
code.** A reusable response named `200` is a failed merge every time, and it is the only part
of this a user sees today.

---

## Follow-up PRs

Agreed direction, deliberately **not** done in the doc-cleanup work. Merged entries move to
[`backlog/archive.md`](backlog/archive.md); numbers are not reused.

### PR 3 — keep derivable documentation in sync automatically — **done, #2158**

Moved to [`backlog/archive.md`](backlog/archive.md).

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
a generator, and now has a home: `DocsAccuracyTest` (#2158), whose read-only-operations
check already verifies one inline signature claim by reflection — the pattern to extend.
Generate only where the fragment is worth *showing* in full; verify everywhere else.

Sequencing: unblocked. The two gates were PR 4 (generators merged, #2141) and PR 3 (the
verify home, #2158); both are done. What remains is the per-fragment choice above.

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
`composer test -- --coverage-text` (found while closing PR 11, archived, which carries the
numbers).

Already closed by #2137: `ComponentIndex`, slot-target validation, and the
attributes nothing was compiling.

**One item is left** — the nullable-default question in the second bullet. The first and
third are closed, kept for the reasoning.

Still open:

- **`ValidateRelationsTest` splits in two, and only half transfers.** Classic asserts three
  things; the bidirectional half — `$_parents` ↔ `$_nested` — has **no spec analogue at
  all**. Spec declares nesting once, on the child, as target + slot, so the two halves
  cannot disagree. Nothing to test.

  What does transfer is classic's third assertion, that a property's type agrees with the
  nesting map. Ported as `SlotMapConsistencyTest::testSlotsAcceptTheDeclaringClass()`, and it
  found a real defect on the first run: `Schema::contained()` named `Schema::$properties`, a
  `list<Property>`. Reachable through a bare `#[OA\Schema]` on a method — nothing wraps it
  into a `Property` there — and it crashed `Augmenter\Inheritance\Schemas` with a
  `TypeError`. Removing the declaration alone turned the crash into a silent wrong document,
  because `Augmenter\Names` then named the orphan after its *declaring* class and it
  replaced the real schema. Fixed together, with `Names` restricted to class reflectors and
  missing-name diagnostics matching classic's "missing key-field".
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
- ~~**No direct tests**: `Assembler/DefaultAttributeTranslator`,
  `Assembler/OptionalPropertyAttributeTranslator`, `Utils/SourceLocation`,
  `Utils/SpecificationWalker`.~~ `SpecificationWalker` gained `SpecificationWalkerTest` with
  #2154. **The other three are deliberately left to the integration tests** (2026-09-05): both
  translators run on every spec build and report 100% line coverage through it, and all three
  are small enough that a unit test would restate the implementation rather than pin
  behaviour. Revisit only if one grows a branch the fixtures do not reach.

### PR 10 — extract the pipeline-agnostic half of `OpenApiTestCase` into concerns — **done, #2147 + #2148 + #2157**

Moved to [`backlog/archive.md`](backlog/archive.md).

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
its `isRoot()` condition). `MediaType\Xml` is covered by `XmlContentEquiv{,-spec}.php`,
which pins the shortcut, the verbose `MediaType` form and the array form against each other
in both modes; `MethodProperty{,-spec}.php` covers a getter as a schema property.

Still wanted: whatever the next coverage run shows thin. As of 2026-09-05 that is
`src/Specification` (84.3%, `ComponentIndex` 84.3%) and `src/Augmenter` (91.1%, weakest
`PathItems` 83.6%, `Types` 84.9%, `EnumDescriptions` 84.4%, `OperationIds` 82.6%);
`Builder/Result` is 70.3%. `src/Console` reads 0% only because `CommandlineTest` drives the
CLI through `exec()`, so the subprocess is never measured — nothing to fix there.

**Prefer the bare-bones form.** A fixture that spells out what the pipeline would infer
cannot detect the inference breaking — it pins the literal instead. Two habits to undo:

- **Explicit values that are inferred.** `type: 'array'` alongside `items` is the common one;
  dropping it gives byte-identical output, so the fixture asserts strictly more without it.
  An explicit `#[OA\Property]` where the Schema shortcut would supply one is the same defect.
  Not every explicit value is redundant — `property:` on a method is required, since a method
  supplies no name.
- **Inline constructor nesting where siblings would do.** `responses: [new OA\Response(...)]`
  never exercises sibling merge; stacking the attributes does, and since #2159 the stacked
  form resolves inner-to-outer whichever way it is declared. It is also the better form to
  write by hand — no nesting, no `new` — so a fixture written that way doubles as the style
  worth copying, which matters because fixtures are what people read. `Response`,
  `RequestBody` and
  `Encoding` were converted for that reason; `XmlContentEquiv` and `MethodProperty` were
  written the inline way and are worth converting.

**Stacking is limited to one container per target.** An attribute can only be lifted out of
its parent if that leaves exactly one candidate to merge into. Found the hard way while
sweeping; each of these fails loudly rather than silently, which is #2159 working:

- two operations on one class or method — a stacked `OA\Response` matches both and raises
  `Ambiguous merge` (`MultiplePathsForEndpoint`)
- a request body already stacked — the `OA\MediaType` then has both a `Response` and a
  `RequestBody` to merge into (`Encoding`, `RequestBody`)
- stacked `OA\Link` attributes meant as *component* links — a sibling `OA\Response` captures
  them into the response instead (`Link`, and `examples/specs/using-links`)

Classic accepts the stacked form too, so the `_at` snippet variants convert alongside the
`_spec` ones; the `_an` variants cannot, docblocks having only the nested form.

Swept in #2164 across 27 fixtures, 17 spec-mode examples and 6 cookbook snippets, with no generated
document changing anywhere. `petstore`'s `PetController` still inlines `parameters` and
`security` — its operations were single-line walls, and only responses were lifted.

Mechanics worth knowing before starting:

- Discovery globs `Scratch/*.php` and skips `-spec` names, so a **classic `.php` file is
  required** even for spec-only material. Omitting the classic *YAML* is how you skip the
  classic cases — `$spec === null` continues.
- Regenerate by uncommenting `file_put_contents` in `ScratchTest::testScratch()`. Always
  pair it with `--filter <Fixture>`: an unfiltered run rewrites **every** fixture, and the
  current dumper differs from what is committed, so the diff is enormous and mostly noise.
- `$expectedLogs` is keyed `{fixture}-{version}`, applying to every mode, or
  `{fixture}-{version}-{mode}` for a diagnostic only one mode raises; both keys contribute
  when both are present (#2160). `ExpectsLogEntries` is strict, so an undeclared entry fails
  rather than passing unnoticed.
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

### PR 15 — make `ScratchTest` log expectations mode-aware — **done, #2160**

Moved to [`backlog/archive.md`](backlog/archive.md).

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
| subclassing an `OpenApi\Spec` attribute | a constructor that derives the attribute's own arguments, typically by reflecting over a target class |

The PoC exercises the first four, which is the useful signal about what to write up first.
Worth capturing the dead ends too — `Run.php` already notes that its `MapRequestPayload`
handling is one of several approaches and probably not the best.

**Subclassing a spec attribute is a working extension point that nothing tests.** Found
while doing PR 15, which removed the `echo` that had been announcing the two `Scratch`
fixtures with no `-spec.php` counterpart. They turn out to mean opposite things:

- `ThirdPartyAnnotation` is **permanently classic-only**. The scenario is a Doctrine
  `@Annotation` class sharing a docblock with `@OA\Schema`; spec assembly never runs the
  annotation parser at all — `Assembler` goes straight to `AttributeFactory`, and docblocks
  are read only for `@var`/`@param` types. There is no spec analogue to write. Its other
  content, `Child extends SomeParent` with a schema on both, is already covered by
  `Docblocks-spec.php`.
- `ComplexCustomAttributes` is **a real gap, and belongs here rather than with the fixtures**.
  It is about user attributes extending `OAT\Schema`/`Property`/`Response` with constructors
  that reflect over a target class — an integrator question, not a classic-syntax one. Spec
  attributes are not `final`, and the pattern works: a subclassed `Spec\Schema` on a class
  and a subclassed `Spec\Response` on a method both resolve, emitting the derived `required`
  list and the `$ref`-ed response. Verified by hand, covered by nothing.

Do not transliterate the classic fixture when writing the spec one. It reads like a pasted
bug report — commented-out experiments, `list(): string` with no return — and its `Item`
subclass, `ref` alongside `title`/`description`, is the `$ref`-with-siblings case
`CompilerTest::test30RefStripsDescription` and `test31RefAllowsSiblings` already pin. What is
worth keeping is the shape: constructors deriving `required` from public properties, and
`#/components/schemas/{ShortName}` refs built from a `class-string`.

With the `echo` gone both fixtures now skip spec mode silently, which reads as an oversight
in the one case and nothing at all in the other. A suite-wide invariant over
`Scratch/*.php` — every fixture has a `-spec.php` or an allowlist entry carrying its reason
— would record the distinction. Deliberately not done in PR 15: the allowlist only earns its
keep once the `ComplexCustomAttributes` half is actually written.

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

### PR 28 — `HybridBridge` converts a webhook's operation twice

`collect()` dispatches each classic annotation through a `match`, and most branches guard with
`!$annotation->_context->is('nested')`. The `Annotations\Operation` branch does not. A classic
`Webhook` holding a `Post` therefore yields two spec operations: one from
`convertWebhook()`, and one from the flat operation branch picking the nested `Post` up again.

Benign today. The second copy has neither `path` nor `webhook`, so `compilePaths()` and
`compileWebhooks()` both skip it and the emitted document is correct — which is why it has
never shown. It surfaced only because #2154's `operationId` uniqueness check counted both
copies and reported a duplicate that does not exist in the source.

#2154 works around it by skipping operations that reach no document, which is independently
correct: uniqueness is a property of the document. The duplication itself is untouched.

The fix looks like one guard, matching the neighbouring branches — the `PathItem` branch
already carries `&& !($annotation instanceof Annotations\Webhook)` for the same
double-handling, so the shape is established. What needs checking first is whether a nested
operation inside a plain `PathItem` relies on the unguarded branch, since `convertPathItem()`
may or may not carry its operations across.

Classic-only by construction, so it disappears at v8 — worth weighing against fixing it.

---

### PR 29 — phpstan level 6, and what the spec namespaces already cost

#2161 annotated the 58 missing-type gaps in `OpenApi\Spec`, `Compiler`, `Augmenter`,
`Assembler` and `Contracts`, so those namespaces report nothing at level 6. **The analysed
level is still 5, and nothing enforces the new state** — phpstan has no per-path level
setting, so this is the code side of a bump, not the bump.

Measured at the time, level 6 over `src`, `tests`, `tools` and `docs/examples`:

| | count |
|---|---|
| `missingType.iterableValue` | 340 |
| `missingType.return` | 132 |
| `missingType.generics` | 66 |
| `missingType.property` | 43 |
| `missingType.parameter` | 38 |
| `argument.templateType` | 4 |
| **total** | **623** |

By area: `docs/examples` 193, `tests` 117, classic (`src/Attributes` + `src/Annotations`) 89,
`tools` 32, the spec namespaces 58 (now zero), everything else in `src/` the rest.

Two findings worth not rediscovering:

- **It is not a return-type problem.** Rector's `typeDeclarations` set is already enabled and
  `composer lint` is clean, so native return types are done; 125 of the 132 that remain are in
  `docs/examples`. The real gap is array value types and `Reflection*` generics.
- **Tooling does not get you there.** Rector 2.6.5 ships an `@experimental`
  `typeDeclarationDocblocks` set — 15 rules aimed squarely at `iterableValue`. Applied across
  the tree it took 623 → 570, and the spec scope 79 → 72. It also emits FQCNs that cs-fixer
  then rewrites, and prefers `array<int, string>` where `list<string>` is right, so its output
  needs reviewing rather than trusting. Not worth wiring in for 8.5%.

**Enforcement was attempted and dropped.** A `composer analyse:spec` script running
`phpstan analyse --level=6` over the spec paths works, but only after scoping the three
`ignoreErrors` in `phpstan.neon.dist` by path *and* adding `reportUnmatched: false` to each —
phpstan reports a path-scoped ignore as unmatched when its path is not in the analysed set,
which fails the run. Losing obsolete-ignore detection on the main run is a worse trade than
the guard is worth. Revisit if phpstan gains per-path levels, or once the tree is close
enough to raise the level globally.

**`docs/examples` is the next chunk and is not a typing cleanup.** Most of its 193 are
`missingType.property` and `missingType.return` on example DTOs, and property types feed the
type resolver — adding them changes the generated schemas and the committed YAML fixtures.
Worth doing, as its own PR where the fixture diff is the thing being reviewed, not as a
by-product.

The rest is classic and dies with v8, which is what makes a global bump a much smaller job
then than now.

### PR 27 — sibling merge depends on declaration order, and loses attributes silently — **done, #2159**

Moved to [`backlog/archive.md`](backlog/archive.md).

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

