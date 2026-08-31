# NelmioApiDocBundle proof of concept

The full write-up behind PR 20 in the [backlog](../../backlog.md).

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
