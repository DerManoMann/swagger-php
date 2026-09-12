# Planned

Agreed direction, not started. Entries are in number order, not priority — nothing is
queued, so what to pick up next is a choice. Parked entries carry the trigger that
revives them. Flow rules: [README.md](README.md).

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

### PR 16 — the mode performance comparison nobody has run

The README claimed hybrid was "faster" than classic; measured, it's 1.46x slower. The two
existing `*PerformanceTest` classes each measure their own pipeline's cleanup-enabled
overhead against a different baseline at a different size — neither compares modes. A proper
side-by-side, plus whether hybrid's double `Generator` pass (PR 17 territory) explains the
gap, is what's missing before any performance claim goes back in the docs.

Numbers and the follow-up questions: [performance/README.md](performance/README.md).

### PR 17 — reflector sources make scanning optional, and that is where the time is

Prompted by PR 16. Seeding `Builder` with controller reflectors instead of scanning a
directory produced byte-identical output up to 14.76x faster as unrelated application code
grew around a fixed API surface — scanning costs what the codebase costs, reflector-driven
resolution costs what the API surface costs. Not yet actionable: no CLI path takes a
reflector list, and where the controller list comes from (router / DI container / route
cache) needs stating rather than assuming.

Full measurements, phase breakdown, and the open blockers:
[performance/README.md](performance/README.md).

### PR 18 — `AttributeGenerator` is the last generator rendering by hand

#2141 moved augmenters, spec attributes and processors onto the shared `Sections`
abstraction. `AttributeGenerator` still renders inline through `Renderer::classDescription()`
and `Renderer::references()`, which exist only for it.

Porting it would finish the job and let those two methods go, the way `processorOptions()`
and `indentedBr()` went. Against that: it generates the classic attributes and annotations
pages, and classic is removed in v8, so this may be work with a short life. Worth doing only
if something else needs to touch that generator anyway.

### PR 20 — the NelmioApiDocBundle proof of concept, and the docs behind it — **page done, #2168 + #2169; PoC introduced, nelmio#2803**

The documentation half shipped: `guide/extension-points.md` (#2168) covers the hooks and how
they compose, and `reference/extension-points.md` (#2169) generates the list of what runs by
default. What remains is the proof of concept itself, which is outward-facing and a separate
decision.

Two halves, both worth keeping. The design docs are parked; the proof of concept is public
and has been introduced upstream (2026-09-11).

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
the `MapRequestPayload` handling is deliberately one of several possible approaches.

Reviewed, polished and published (2026-09-11). The branch is squashed to a single commit on
top of upstream 5.11.1 at
[`spec-poc`](https://github.com/DerManoMann/NelmioApiDocBundle/tree/spec-poc) in the public
fork, with the write-up at `src/SpecPoC/README.md`. `composer.json` now requires swagger-php
`^6.8` — the earlier `^5.7.8 || ^6.0` resolved to versions without the hooks the PoC uses.

Introduced to the Nelmio project as
[nelmio/NelmioApiDocBundle#2803](https://github.com/nelmio/NelmioApiDocBundle/issues/2803)
(2026-09-11) — deliberately an issue and not a pull request, since the point is an early
heads-up ahead of v7/v8 rather than a change to land.

**Generic instantiations stay out of core.** The PoC emits one schema for `GenericClass<T>`
where the bundle emits eleven, one per instantiation (`GenericClass`, `GenericClass2`, …).
That gap is a decision, not a todo:

- swagger-php already ships the mechanism. `Builder::withResolver()` is the hook, and the
  PoC's own README names a registry-backed resolver as the answer.
- There is no neutral naming scheme to ship. `GenericClass2` is positional and depends on
  encounter order — a presentation choice OpenAPI says nothing about, so core would own that
  scheme forever and anyone wanting a different one would have to fight it.
- `ModelRegistry` already is that naming layer, in the one project known to need it.

Revisit if a second consumer asks. Even then the reusable part is the traversal that expands a
generic into per-instantiation components, not the naming, and that could ship as an optional
package without core committing to a scheme.

Not to be confused with PR 38 — a generic *docblock* resolving to nothing. That one was a core
bug in both pipelines and shipped in #2173.

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

### PR 22 — OpenAPI 3.2 field coverage in `src/Spec/` — **PARKED (2026-09-02)**

Trigger: PR 25's `#[Since]` mechanism exists, and Q5 is answered (for Phase 4).

#2149 is a draft and stays one. Two reasons, and the second is the one that matters:

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
[spec-3.2/README.md](spec-3.2/README.md).

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

### PR 25 — `#[Since]`: declare the version a field arrived in, where it can be used — **PARKED (2026-09-02)**

Trigger: 3.2 work resumes — this is where it starts, as PR 22's precondition.

**Parked with PR 22, but promoted within it.** It is no longer a follow-up that
unlocks Phase 1b — it is the precondition for any 3.2 field landing at all, because the
decision not to merge fields needing later retrofitting means the mechanism has to exist
before the fields do.

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

### PR 31 — nothing notices when a tool exclusion stops excluding anything

`.php-cs-fixer.dist.php` and `rector.php` between them carried seven exclusions that did
nothing: two paths for files that no longer exist (`src/Analysers/TypeResolverTrait.php`,
`tests/Analysers/TokenScannerTest.php`), five rule skips whose rules had stopped firing on
the file named, and one cs-fixer entry suppressing formatting on a whole file to protect
something that was never at risk — its comment said "FQDN in data provider", but the keys
are strings and cs-fixer does not rewrite strings.

Half-life is short: the `IfToNullCoalescingAssignRector` skip was added seven days before it
was found dead, in #2135 — a commit whose subject is "follow rector rule changes".

Both failure modes are silent. A skip for a deleted path is inert, and a skip for a rule that
no longer matches is inert; in both cases `composer lint` stays green and the entry looks
load-bearing.

Two checks would catch it, and both are cheap:

- **every path in an exclusion exists.** Pure file-system check, no tool run.
- **every rule skip still fires.** Remove one path list at a time, run `rector --dry-run`, and
  assert the named file is reported. That is how these were found. Whole-rule skips with no
  path — style preferences like `NewlineAfterStatementRector` — cannot be tested this way and
  would have to be exempt.

The second needs one rector run per skip, so it belongs in a `composer` script run
deliberately rather than in `lint`. `DocsAccuracyTest` is the precedent for the shape: a test
that checks configuration against reality rather than trusting it.

Worth knowing before starting: a file listed under two rules cannot be judged from a single
bulk run — `ComposerAutoloaderScannerTest` is skipped by both
`StringClassNameToClassConstantRector` and `ArrayToFirstClassCallableRector`, and only a
per-rule test showed the second is still needed.

### PR 33 — declared dependency floors nothing verifies — **PARKED (2026-09-11) for v7**

Trigger: v7, where the ROADMAP already raises the `nikic/php-parser` floor this found.

The one finding is the `nikic/php-parser` floor, and raising it to `^5.0` is already a v7
line in [ROADMAP](../../ROADMAP.md). Doing it in v6 drops declared support in a patch or
minor release, which is the one thing the finding does not argue for — the branch is
untested, not broken, and a consumer resolving `require` only really does get 4.19.0. So
the entry stays as the reasoning for that v7 line rather than as work of its own.

PR 34, its sibling, is closed rather than parked — the platform pin it proposed turned out
to be a no-op for the comparison and a regression elsewhere (see [archive.md](archive.md)).
The comparison itself needs no config change, so re-running it here costs one resolve.

Found while wondering whether any `composer.json` floor had quietly become unreachable. The
check is cheap: resolve with `--prefer-lowest` and compare what composer picks against each
declared floor. Anything resolving *above* its lowest branch means that branch is unused.

Run on PHP 8.2, every floor is reachable — `psr/log` 1.1.0, `symfony/finder` 5.4.45,
`symfony/yaml` 5.4.52, `symfony/deprecation-contracts` 2.5.0, `phpunit` 11.5.50 all land in
their lowest branch — with one exception, and it depends on context:

| Context | `nikic/php-parser` resolves to |
| --- | --- |
| with `require-dev` | 5.7.0 — the `^4.19` branch is unreachable |
| a manifest carrying `require` only | 4.19.0 — the floor is real |

What holds it up is entirely the dev coverage stack: `phpunit/php-code-coverage` requires
`^5.7.0`, `sebastian/complexity` and `sebastian/lines-of-code` require `^5.0`.

**The second row was originally credited to a platform pin, and that was wrong** (measured
2026-09-11). The pin has nothing to do with it, and `composer update --no-dev` does not
produce that row either — `--no-dev` skips *installing* dev packages but still solves with
them, so 57 of them land in the lock and php-parser still resolves to 5.7.0. Only a
manifest with no `require-dev` at all gives 4.19.0. The conclusion is unchanged, and if
anything firmer: a consumer really does get 4.19.0, and nothing in this repo can.

**So `^4.19` is declared support CI has never exercised**, and cannot, in any matrix cell. A
consumer really does get 4.19.0, so dropping the branch is a genuine decision rather than a
formality — but the thing being decided is support that was never tested. `TokenScanner` also
calls `createForNewestSupportedVersion()`, which 4.x only gained in 4.18, and 4.x parses no
further than PHP 8.3 syntax, which is what
[PR 31's sibling note](#pr-31--nothing-notices-when-a-tool-exclusion-stops-excluding-anything)
and the v7 ROADMAP entry already record.

Two ways to close it, and they are not exclusive:

- **Raise the floor to `^5.0`.** Already noted for v7 in [ROADMAP](../../ROADMAP.md). This is
  the honest option: it aligns the declaration with what is verified.
- **Keep `^4.19` and test it.** Needs a CI cell that installs `require` only, or a dev set
  that does not drag in `^5`. The first is easy — a job that runs `composer update --no-dev
  --prefer-lowest` and then something that exercises `TokenScanner` without phpunit. That is
  awkward enough that it argues for the first option.

The same `--prefer-lowest` comparison is worth re-running whenever a floor is raised; it costs
one resolve and needs no judgement.

### PR 40 — classic's `Header` models less than half the Header Object

`OpenApi\Annotations\Header` declares `ref`, `header`, `description`, `required`, `schema`,
`deprecated` and `allowEmptyValue`. The specification's Header Object also has `style`,
`explode`, `example`, `examples` and `content`. None of the five exist in classic, in the
annotation or the attribute, so there is no way to write them and nothing to serialise.

`Spec\Header` has `example` and `examples`, and `OpenApi31Compiler::NESTED_MAPS` already
carries `OA\Header::class => ['examples' => 'example']` for them. So the two pipelines
disagree about what a header can say, and the gap is classic's.

**Found while answering [#2177](https://github.com/zircote/swagger-php/issues/2177)**, which
is about `@OA\Examples` under a schema. Checking that `Examples::$_parents` was right for
every context turned up `Header` missing from it — and then that the property it would fill
is absent too. The first reading, recorded as a loose thread while reviewing #2178, was that
the nesting was missing; that was wrong, and worth stating because a missing entry in
`$_parents` looks like a one-line fix and this is not one.

**Whether to do it at all is the question, not how.** `src/Annotations/` and
`src/Attributes/` are closed to new features — [ROADMAP.md](../../ROADMAP.md) removes classic
in v8 — so five new fields there is exactly what that rule exists to prevent. Against that: a
header carrying an example is ordinary OpenAPI, and a user writing one today gets silence
rather than a diagnostic, which is the worst of the three possible answers.

The cheap middle is to say so. `Header` could reject `@OA\Examples` and the other four with
a message naming the spec pipeline, turning a silent drop into a pointer. That is a
diagnostic, not a feature, and it fits inside the closed-area rule.

Related: `@OA\Examples` lists `Header::class` in neither direction, so nothing warns today
even though `Examples::$_parents` is the mechanism that would.

### PR 41 — lowering the `symfony/console` floor to 6.4 — **HELD, branch kept local**

Trigger: someone asks for a 6.4 floor. Not before.

`composer.json` declares `symfony/console` as `^7.4 || ^8.0`. Dropping the floor to
`^6.4 || ^7.0 || ^8.0` is done and working on `fix/symfony-console-constraint`, commit
`0145ef28`. It is **deliberately not pushed and has no PR.**

What the branch contains, since the worktree is gone and the commit is the only record:

- `GenerateCommand` rebuilt on `Command::configure()`/`execute()`, because the
  `#[Argument]`/`#[Option]` attributes it used are 7.4-only
- that option metadata moved into `GenerateInput::getDefinition()`, with
  `GenerateInput::hydrate()` filling the properties from `InputInterface`
- `bin/openapi` registering through `addCommands()`
- `DocsAccuracyTest` pinning only the options this project declares
- a `symfony/string` conflict below 5.4.41

Five files, +141/-23.

**Held on purpose: let others contribute first.** Nobody has asked for a 6.4 floor. Taking it
now means carrying a hand-rolled `getDefinition()`/`hydrate()` pair in place of the attribute
API for the sake of a version nobody has named, and that code has to be maintained against
every Symfony release whether or not it is ever used. If the need is real it will arrive as an
issue, and the branch is then a ready answer rather than speculative maintenance.

Worktree removed 2026-09-11; the branch ref stays.
