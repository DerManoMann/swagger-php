# Roadmap v2

Refined plan based on the validated prototype on the `roadmap` branch, now implemented
on `openapi-builder` (Builder/classic) and `spec-attributes` (full spec pipeline).

## What's Implemented

The three-layer architecture works end-to-end on the `spec-attributes` branch:

```
Sources → Assembler → Specification → Compiler → Output
```

- **Builder** (`OpenApi\Builder`) — unified entry point with `setMode('classic'|'spec')`, `Result` container, NullLogger default for programmatic use (`openapi-builder` branch, merged into `spec-attributes`)
- **DTOs** (`OpenApi\Spec\*`) — 28 classes (Layer 1 + Layer 2 polymorphic subclasses), version-agnostic, pure value containers
- **Assembler** — reflection-based, two-pass nesting via `merge()`/`contains()`/`isRoot()` on each attribute
- **Specification** — flat typed collections with `add()` routing
- **Compilers** — 3.0 (downgrade from 3.1), 3.1 (canonical), 3.2 (extends 3.1)
- **CompilerInterface** — `supports(version)`, `validate(specification)`, `compile(specification)`
- **ExamplesTest** — wired to run spec examples through the spec pipeline via `setMode('spec')`
- **CompilerTest** — 47+ tests with data providers covering version-specific compilation
- **AssemblerTest** — tests for nesting, hierarchy resolution, error reporting
- **CLI** — `GenerateCommand` refactored to use Builder

The `api` example passes end-to-end with fully explicit spec attributes. Real-world
examples (petstore, etc.) are blocked on augmenters — the pipeline has no inference yet.

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

Selected via `Builder::setMode()`. Three planned modes:

### Classic Mode (`setMode('classic')` — default)

The full current stack, untouched. `Generator` → Analysis → Processors → `jsonSerialize()`.
Builder wraps Generator internally; `withGenerator()` provides configuration access.
Handles both `OA\*` annotations and `OA\*` attributes.

### Spec Mode (`setMode('spec')`)

Pure spec attribute pipeline. Only processes `OpenApi\Spec\*` attributes:

```
Sources (scan) → TokenScanner → ReflectionClass[]
    → Assembler (collect + resolve nesting)
    → Specification (flat typed collections)
    → Augmenters (enrich from reflectors)
    → Compiler (version-specific output)
    → Output
```

Key points:
- Only `Spec\*` attributes are processed — `OA\*` annotations/attributes are ignored
- No bridge, no conversion from classic annotations
- Augmenters operate on DTOs with reflector access for inference
- The Compiler only sees DTOs — all version logic is centralized there

### Hybrid Mode (`setMode('hybrid')` — future)

Runs classic `OA\*` annotations/attributes through a bridge into the spec pipeline:

```
Sources (scan)
├── Classes with Spec\* attributes → Assembler → Specification
├── Classes with OA\* attributes   → Classic Analyser + Bridge → Specification
↓
Specification (unified model)
↓
Augmenters → Compiler → Output
```

This enables gradual migration: existing `OA\*` code and new `Spec\*` code coexist
in the same project, processed by the same pipeline. Not yet implemented.

### Timeline

- v6: `classic` (default) and `spec` available
- v7: `spec` default, `hybrid` available for migration, `classic` opt-in
- v8: `classic` removed, `hybrid` removed

## Progress

### Phase 1: Builder ✓

Implemented on `openapi-builder` branch (merged into `spec-attributes`).

```php
$result = (new \OpenApi\Builder())
    ->addSource('src/Controllers')
    ->setMode('spec')       // or 'classic' (default)
    ->setVersion('3.1.0')
    ->build();

echo $result->toYaml();
```

**Done:**
- `Builder` — fluent API: `addSource()`, `setSources()`, `setVersion()`, `setLogger()`, `setMode()`, `setCompiler()`, `withGenerator()`
- `Result` — unified contract: `openApi()`, `toArray()`, `toJson()`, `toYaml()`, `saveAs()`, `files()`, `log()`, `warnings()`, `errors()`, `isValid()`
- `Result::fromClassic()` / `Result::fromSpec()` — factory methods for dual-pipeline output
- `CollectingLogger` — captures log entries for Result diagnostics
- Classic mode wraps Generator internally via `withGenerator()` hook
- Spec mode runs Assembler → Compiler pipeline
- NullLogger default (silent for programmatic use; CLI sets its own logger)
- `GenerateCommand` refactored to use Builder
- Tests migrated from Generator to Builder (ExamplesTest, ScratchTest, ContextTest, processor tests)
- Documentation: `docs/reference/builder.md`

### Phase 2: Spec Attributes — In Progress

Implemented on `spec-attributes` branch. Core infrastructure is complete; the pipeline
produces correct output only when attributes are fully explicit (no inference from code).

**Done:**
- Layer 1 DTOs (`OpenApi\Spec\*`): OpenApi, Info, Contact, License, Server, ServerVariable, Operation, Parameter, RequestBody, Response, MediaType, Encoding, Header, Link, Example, Schema, Property, Discriminator, Xml, ExternalDocumentation, SecurityScheme, Flow, Tag
- Layer 2 polymorphic subclasses: `Operation\{Get,Post,Put,Delete,Patch,Options,Head,Trace}`, `Parameter\{Path,Query,Header,Cookie}Parameter`, `SecurityScheme\{ApiKey,Http,MutualTls,OAuth2,OpenIdConnect}Scheme`, `Flow\{Implicit,AuthorizationCode,Password,ClientCredentials}Flow`
- `AbstractAttribute` base with `SourceLocation`, reflector tracking
- `AttributeInterface` with `merge()`, `contains()`, `isRoot()`
- `Assembler` — two-pass: `resolveNesting()` (stack-resolve siblings) + `resolveHierarchy()` (absorb from inner reflectors)
- `Specification` — flat typed collections
- `CompilerInterface` — `supports()`, `validate()`, `compile()`
- `OpenApi31Compiler` — canonical (800+ lines, full spec coverage)
- `OpenApi30Compiler` — downgrades from 3.1 (nullable keyword, boolean exclusive bounds, no $ref siblings, no webhooks/const/prefixItems/if-then-else)
- `OpenApi32Compiler` — extends 3.1 (ready for Tag summary/parent/kind)
- Version/compiler auto-resolution in Builder
- `api` example with spec attributes (fully explicit — passes without augmenters)
- CompilerTest with 47+ data-driven tests
- AssemblerTest covering nesting, hierarchy, edge cases
- ExamplesTest wired with `setMode('spec')` for spec implementation directories

**Not yet working:**
- Real-world spec examples (petstore, etc.) — blocked on augmenters for type/description inference
- Callback attribute (not yet implemented)
- PathItem attribute (operations grouped by compiler, no standalone PathItem DTO yet)

### Phase 3: Augmenters — Not Started

The pipeline gap. Without augmenters, spec attributes must declare everything explicitly —
no inference from PHP types, docblocks, or class structure. This is the main blocker for
making the spec pipeline usable for real-world code.

Each augmenter operates on a `Specification` and enriches DTOs using their attached reflectors:

| Augmenter | What it does | Classic equivalent |
|-----------|-------------|-------------------|
| **TypeResolver** | Infer schema `type`/`format`/`$ref` from PHP type hints | `AugmentSchemas` (type part) |
| **DocblockReader** | Fill `summary`/`description` from `/** */` comments | `AugmentSchemas` (docblock part) |
| **EnumValues** | Populate `enum` from PHP enum cases | `AugmentSchemas` (enum part) |
| **Inheritance** | Build `allOf`/property merging from interfaces/traits | `ExpandClasses`/`ExpandInterfaces`/`ExpandTraits` |
| **OperationId** | Auto-generate `operationId` from method names | `OperationId` |
| **ParameterAugmenter** | Infer parameter types from method signatures | `AugmentParameters` |
| **DefaultValues** | Fill `default` from PHP default values | `AugmentProperties` |
| **CleanUnused** | Remove unreferenced component schemas | `CleanUnusedComponents` |

Priority order for unblocking examples:
1. TypeResolver — needed by virtually every schema
2. DocblockReader — summary/description on operations and schemas
3. Inheritance — needed for polymorphism/using-traits/using-interfaces examples
4. OperationId — needed if not explicitly set
5. EnumValues, ParameterAugmenter, DefaultValues — fill in gaps
6. CleanUnused — nice-to-have, not blocking

### Phase 4: Integration

Partially done, remaining:

**Done:**
- SourceScanner — shared infrastructure, resolves mixed inputs into file lists (extracted to `Utils\SourceScanner` on master)
- TokenScanner — discovers classes in source files for reflection

**Remaining:**
- Specification finders (`schema()`, `find()`, `filter()`, `resolveRef()`) — needed by augmenters that resolve `$ref` targets
- Schema registry / `$ref` resolution — needed for Inheritance augmenter and cross-references
- CompilerExtension wiring (Attachable → output)
- Validation pipeline (currently `validate()` on CompilerInterface, needs richer diagnostics)

### Phase 5: Test Fixtures & Examples

**Acceptance criterion:** each existing example produces identical YAML output when
re-implemented with spec attributes and run through the spec pipeline. The examples
are the primary validation that augmenters work correctly.

| Example | Spec variant | Status |
|---------|-------------|--------|
| api | ✓ exists | ✓ passes (fully explicit) |
| petstore | not yet | blocked on TypeResolver, DocblockReader |
| misc | not yet | blocked on TypeResolver |
| nesting | not yet | blocked on TypeResolver |
| polymorphism | not yet | blocked on Inheritance |
| using-interfaces | not yet | blocked on Inheritance |
| using-links | not yet | blocked on TypeResolver |
| using-refs | not yet | blocked on TypeResolver |
| using-traits | not yet | blocked on Inheritance |
| webhooks | not yet | blocked on Callback DTO |

Separately, the test suite has organically grown with mixed patterns. When adding
spec-native fixtures:

- Establish a single pattern: one fixture class → one expected YAML output
- Spec pipeline tests should use the Assembler directly (no Analysis/Generator indirection)
- Group by feature (security, parameters, schemas, inheritance) not by bug/PR

### Phase 6: Ship

- Documentation and migration guide (spec attributes)
- Update all examples with spec attribute variants (dual tabs in docs)
- Deprecation notices on classic path
- Announce spec mode as opt-in for v6

## Version Timeline

| Version | What happens |
|---------|-------------|
| v6.x | Builder ships. Classic default. Spec mode opt-in. |
| v7 | Spec mode default. Hybrid mode for migration. Classic opt-in. Remove legacy namespaces, Context, Analysis, doctrine support. |
| v8 | Classic and hybrid removed. Spec only. |

## Key Design Decisions (validated)

- No `AttributeStack` — Assembler works directly with flat lists
- No separate Factory — Assembler reads attributes via reflection directly
- `merge()`/`contains()`/`isRoot()` on each attribute over static `$_parents`/`$_nested` maps
- `UNDEFINED` retained on Schema for null-valid fields (`example`, `default`, `const`)
- Canonical form is 3.1+ (JSON Schema 2020-12) — compilers downgrade
- Processors stay as `__invoke(Analysis)` through v6; migrate in v7
- Builder named `Builder` (not `OpenApiBuilder`) — simpler, same namespace disambiguates
- `setMode('classic'|'spec')` over boolean flag or separate builder classes — transitional, extensible
- NullLogger default on Builder — silent for programmatic use; CLI sets its own logger
- `Result` with factory methods (`fromClassic`/`fromSpec`) — single return type, dual internals
- `withGenerator(callable)` as escape hatch for Generator configuration — avoids duplicating Generator's full API on Builder

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
