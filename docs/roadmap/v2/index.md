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

### Phase 4: Test Fixtures

The current test suite has organically grown with mixed patterns and approaches. When
reimplementing fixtures for the spec pipeline:

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
