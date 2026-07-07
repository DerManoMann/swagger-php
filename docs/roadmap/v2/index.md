# Roadmap v2

Refined plan based on the validated prototype on the `roadmap` branch.

## What's Proven

The three-layer architecture works end-to-end:

```
Sources → Assembler → Specification → Augmenters → Compiler → Output
```

- **DTOs** (`OpenApi\Spec\*`) — 23 classes, version-agnostic, pure value containers
- **Assembler** — reflection-based, `allowedParents()` nesting, no intermediate stack
- **Specification** — flat typed collections with `add()` routing
- **Compilers** — 3.0 (downgrade), 3.1 (canonical), 3.2 (forward-looking)
- **Both bridges** — new→classic and classic→new, producing identical output
- **OpenApiBuilder** — unified entry point, dual-mode, auto-detection, `BuildResult` contract

All passing against real fixtures (PetStore, full API with callbacks/security/enums).

## Attribute Strategy

The spec attributes ship in two layers:

### Layer 1: Full 3.1 Implementation

A 1:1 mapping of OpenAPI 3.1 objects as attribute classes. Same structure as the spec,
same field names. No shortcuts, no magic — just typed DTOs.

Exception: `Schema` is used as a composite in places where the spec embeds a schema
inline (Property, Parameter, Header, MediaType items). These get a `schema` property
(or use `Schema` directly on the target) rather than duplicating all schema keywords.

This is the canonical set:
- OpenApi, Info, Contact, License
- Server, ServerVariable
- PathItem, Operation, Parameter, RequestBody, Response
- MediaType, Encoding, Header, Link, Example
- Schema, Discriminator, Xml, ExternalDocumentation
- SecurityScheme, Flow
- Tag, Callback

### Layer 2: Polymorphic Convenience Subclasses

Built on top of Layer 1 as sub-namespaced classes:

```
Operation\Get, Operation\Post, Operation\Put, Operation\Delete, Operation\Patch, ...
Parameter\PathParameter, Parameter\QueryParameter, Parameter\HeaderParameter, Parameter\CookieParameter
SecurityScheme\ApiKeyScheme, SecurityScheme\HttpScheme, SecurityScheme\OAuth2Scheme, ...
Flow\ImplicitFlow, Flow\AuthorizationCodeFlow, Flow\PasswordFlow, Flow\ClientCredentialsFlow
```

Each subclass pre-sets the discriminator field and only exposes relevant constructor params.

### Milestone

This two-layer set is what ships in v6 — enough to:
- Update all examples and documentation (dual tabs: classic attributes + spec attributes)
- Reimplement all test fixtures against the spec pipeline
- Prove the full pipeline end-to-end for real-world usage

## Pipeline Modes

Two modes, no hybrid merge:

### Classic Mode

The full current stack, untouched. `Generator` → Analysis → Processors → `jsonSerialize()`.
This is the default in v6.

### Spec Mode

A single pipeline that handles **both** attribute namespaces:

```
Sources (scan)
├── Classes with Spec\* attributes → Assembler → Specification
├── Classes with OA\* attributes   → Classic Analyser + Bridge Processor → Specification
│                                     (single processor: convert OA annotations to DTOs)
↓
Specification (unified model)
↓
Augmenters
↓
Compiler → Output
```

Key points:
- One Specification instance — no dual-pipeline merge, no collision detection
- `OA\*` classes run through a **minimal** set of classic processors — only those needed
  to assemble the tree structure, not to augment/enrich data:
  1. `MergeIntoOpenApi` — builds the `OA\OpenApi` root from loose annotations
  2. `MergeIntoComponents` — nests schemas/responses into `OA\Components`
  3. `BuildPaths` — groups operations into PathItems by path
  4. `MergeJsonContent` / `MergeXmlContent` — structural shorthand expansion
  - NOT needed: `CleanUnmerged` — spec pipeline will have its own cleanup strategy
  - NOT needed: `ExpandClasses`/`ExpandInterfaces`/`ExpandTraits` — the spec pipeline's
    InheritanceAugmenter handles this uniformly from reflectors for all DTOs
- The `SpecificationConverter` then converts the structured tree to DTOs, propagating
  reflectors from `$annotation->_context->reflector` via `setReflector()`
- `Spec\*` classes go through the Assembler directly into the same Specification
- Augmenters run uniformly on **all** DTOs in the Specification regardless of source —
  bridged DTOs have reflectors too, so type inference, docblock extraction, ref resolution
  etc. work identically
- Classes are routed by which attribute namespace they use (auto-detected per class)
- The Compiler only sees DTOs — it doesn't know or care about the source

This means:
- v6: both modes available, classic is default
- v7: spec mode is default, classic available via explicit opt-in
- v8: classic mode removed, bridge processor removed

## Remaining Work

### Phase 1: OpenApiBuilder

Introduce the Builder as the new public entry point. The new pipeline concepts (Specification, Assembler, Augmenters, Compilers) don't fit into Generator's design, so this is a clean break rather than an extension.

The base Builder (using defaults only) provides the same API for both classic and new pipelines — users adopt it first, then migrate annotations at their own pace.

```php
$yaml = (new OpenApiBuilder())
    ->withLogger($logger)
    ->withSources(new SourceFinder(['src/Controllers', 'src/Models']))
    ->build()
    ->toYaml();
```

- Unified `BuildResult` contract (files, diagnostics, output)
- Classic mode wraps Generator internally
- Spec mode runs new pipeline (Assembler → Augmenters → Compiler)

### Phase 2: Augmenters

The pipeline gap. `getDefaultAugmenters()` currently returns `[]`.

- Docblock → summary/description
- Type resolution → schema types/formats/$refs
- Enum → enum values from PHP enums
- Inheritance → allOf from interfaces/traits
- OperationId → auto-generation
- Cleanup → unused component removal

### Phase 3: Integration

- SourceScanner — shared infrastructure extracted from `Generator::scanSources()`. Resolves mixed inputs (strings, iterables, directories, SplFileInfo) into a file list. Both pipelines use it: classic feeds files to the Analyser, spec feeds them to TokenScanner → ReflectionClass[] → Assembler.
- Specification finders (`schema()`, `find()`, `filter()`, `resolveRef()`)
- Schema registry / `$ref` resolution
- CompilerExtension wiring (Attachable → output)

### Phase 4: Test Fixtures & Validation

**Acceptance criterion:** once augmenters are complete, the full existing test suite can run
through the spec pipeline via the bridge (classic annotations → structural processors →
SpecificationConverter → Specification → Augmenters → Compiler) and produce identical output
to the classic pipeline. No new test expectations needed — the existing suite validates the
spec pipeline by definition.

This means:
- Every classic test fixture is automatically a spec pipeline regression test
- Parity failures pinpoint exactly which augmenter behaviour is missing or diverges
- The spec pipeline is "done" when all classic tests pass through it unchanged

Separately, the test suite itself has organically grown with mixed patterns. When adding
spec-native fixtures:

- Consolidate duplicates (many scratch fixtures test overlapping things)
- Drop PHP syntax fixtures that are no longer relevant (php7.php, older namespace tests)
- Establish a single pattern: one fixture class → one expected YAML output
- Spec pipeline tests should use the Assembler directly (no Analysis/Generator indirection)
- Group by feature (security, parameters, schemas, inheritance) not by bug/PR

This is a cleanup opportunity, not a blocker.

### Phase 5: Ship

- Wire OpenApiBuilder into Generator as opt-in
- Documentation and migration guide
- Deprecation notices on classic path

## Version Timeline

| Version | What happens |
|---------|-------------|
| v6.x | Builder ships. Classic default. Spec mode opt-in. |
| v7 | Spec mode default. Classic available via `setMode('classic')`. Remove legacy namespaces, Context, Analysis, doctrine support. Introduce `ProcessorInterface::process(Specification)`. |
| v8 | Classic mode removed entirely. |

## Key Design Decisions (validated)

- No `AttributeStack` — Assembler works directly with flat lists
- No separate Factory — Assembler reads attributes via reflection directly
- `allowedParents()` method over `#[AllowedParents]` attribute or `$_parents` statics
- `UNDEFINED` retained on Schema for null-valid fields (`example`, `default`, `const`)
- Canonical form is 3.1+ (JSON Schema 2020-12) — compilers downgrade
- Processors stay as `__invoke(Analysis)` through v6; migrate in v7

## Classic Prep Work — Done / Skipped

The original plan (v1/v6-prep-work.md) identified prep work to decouple classic code before
introducing spec pipeline code. Status:

**Completed (on master):**
- Extract `Generator::UNDEFINED` to standalone `Undefined` class (#2035)
- Remove version branching from type resolvers (#2037)
- Extract generic docblock parsing to `Utils\DocBlockParser` (#2038)
- Move `TokenScanner` + others to `Utils` namespace (#2039)
- Extract `TypeMapper` as pipeline-agnostic utility (#2040)
- Extract `SourceScanner` from Generator (#2042)

**Skipped — not needed:**
- Decouple processors from Generator (inject TypeResolver directly)
- Formal `ProcessorInterface` on classic side
- `SourceLocation` on `AbstractAnnotation`
- Extract merge from `AbstractAnnotation::__construct()`
- `RefTrait` → static utility class
- Bridges between classic and spec pipelines

**Rationale:** With the Builder running both pipelines side by side independently, there's no
need to refactor classic internals to support the new pipeline. The spec pipeline is a clean
parallel system — it doesn't share processors, doesn't need classic code to be restructured,
and doesn't require bridging. Classic stays frozen; new code is additive. The completed
extractions (TypeMapper, DocBlockParser, SourceScanner, TokenScanner) provide the shared
utilities both pipelines need. Everything else is unnecessary churn on code that will be
retired in v8.
