# LLM docs backlog

Decisions taken, questions still open, and follow-up work — for the documentation and the
tooling around it.

The facts this file used to collect were written up in #2136 and now live in `docs/dev/`
(`pipeline.md`, `testing.md`, `docs-toolchain.md`, `writing-docs.md`), with terminology in
`CONTEXT.md` and the pre-PR checklist in `CONTRIBUTING.md`. Those are the source; add to
them, not here.

What stays here is the reasoning behind decisions — including the ones not to do something,
which the code and the docs do not record.

## Where this stands

Merged: **#2134** (spec docs cleanup), **#2135** (rector rule changes), **#2136** (developer
docs, and the writing rules). **#2130** (resolver step) is rebased and mergeable, with its
doc changes re-homed onto the page split #2136 introduced. `test/spec-coverage` is pushed
and unopened — `ComponentIndex`, slot-target validation, and the attributes nothing was
compiling.

Suggested order for what is left:

1. **PR 4** — deduplicate the two doc generators. It collapses the divergence that has
   already produced one real bug, and both PR 1 and PR 6 get cheaper afterwards because
   there is a single extraction path to change instead of two.
2. **PR 9** — scratch fixtures. Cheap, and it widens what Redocly validates rather than what
   we assert about ourselves. Probably supersedes part of `test/spec-coverage`.
3. **PR 1** — `#[Config]`, so `-D` output and the reference pages derive from one
   declaration rather than two rules aligned by hand.
4. **PR 3** — `composer docs:check`. The review checklist in `docs/dev/writing-docs.md`
   already specifies the runnable half; this is implementing it.
5. **PR 8** — the remaining test gaps. Run coverage in CI first; the survey behind it used a
   structural proxy and cannot see weakly-exercised code.
6. **PR 6** — generated code fragments, and only if PR 3's verification turns out not to be
   enough on its own. Verifying is much cheaper than generating.
7. **PR 7** — augmenter config on two pages. Small, independent, do it whenever.

Q3 revisits when spec stops being beta (v7); Q4 when classic is removed (v8).

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

### PR 4 — deduplicate `AugmenterGenerator` and `ProcessorGenerator`

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

From a coverage survey on 2026-08-29. Note the survey used a structural proxy — *is this
class executed by anything at all* — because no coverage driver is installed locally. It
finds dead spots, not weakly-exercised ones. CI runs with pcov, so
`composer test -- --coverage-text` in a workflow would give real numbers cheaply, and is
probably worth doing before acting on any of this.

Already closed in `test/spec-coverage`: `ComponentIndex`, slot-target validation, and the
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

