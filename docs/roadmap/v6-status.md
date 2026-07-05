# Implementation Status

Snapshot of what has been prototyped vs. what remains, as of the current `roadmap` branch.

## Prototyped

| Component | Location | Notes |
|-----------|----------|-------|
| DTOs (`OpenApi\Spec\*`) | `src/Spec/` | All essential classes (Schema, Property, Operation, Parameter, Response, RequestBody, MediaType, Header, Encoding, ExternalDocumentation, Discriminator, Xml, Link, SecurityScheme, Flow, Info, Contact, License, Tag, Server, ServerVariable, Example, Attachable, OpenApi) |
| `OpenApiAttributeInterface` | `src/Spec/OpenApiAttributeInterface.php` | Full contract: `allowedParents()`, `getReflector()`/`setReflector()`, `getSourceLocation()`/`setSourceLocation()`, `getExtensions()`, `getAttachables()` |
| `AbstractAttribute` | `src/Spec/AbstractAttribute.php` | Base class implementing the interface; `reflector`/`sourceLocation` are protected with fluent setters |
| `SourceLocation` | `src/Spec/SourceLocation.php` | Value object with `fromReflector()` |
| `Specification` | `src/Spec/Specification.php` | Flat typed collections + `add()` routing; `$openapi` is non-nullable DTO (initialized in constructor) |
| `Assembler` | `src/Spec/Assembler.php` | Reads attributes via reflection, resolves nesting via `allowedParents()`, populates Specification; typed to concrete reflection classes |
| `SpecAnnotationFactory` | `src/Spec/SpecAnnotationFactory.php` | Phase 1 bridge with full context propagation (resolveContext, buildNestedContext, recursive convertArray, flattenSchema, UNDEFINED-aware extractProperties) |
| `SpecCompilerInterface` | `src/Spec/SpecCompilerInterface.php` | Interface with `getVersion()`, `supports()`, `compile()`, `validate()` |
| `OpenApi31Compiler` | `src/Spec/OpenApi31Compiler.php` | Full declarative implementation with `filter()`, nullable→type array, UNDEFINED-aware fields, version-specific validation |
| `CompilerDiagnostics` / `Diagnostic` | `src/Spec/` | Error/warning collection for validation |
| `Generator::UNDEFINED` reuse | `src/Spec/Schema.php` | `example`, `default`, `const` use UNDEFINED to distinguish "not set" from explicit null |
| `SpecificationConverter` | `src/Spec/SpecificationConverter.php` | Phase 2 bridge: classic `OA\OpenApi` → `Specification`; handles nullable→type array, callbacks with nested annotations, $ref+description |
| `OpenApi30Compiler` | `src/Spec/OpenApi30Compiler.php` | Downgrade compiler: type array→single+nullable, exclusiveMin boolean form, const→enum, strips 3.1+ keywords; validation: paths required, no examples array |
| `OpenApi32Compiler` | `src/Spec/OpenApi32Compiler.php` | Extends 3.1 with forward-looking Tag summary/parent/kind and PathItem query |
| Compiler validation | All compilers | Ported from classic: required fields, version constraints, schema consistency (array needs items, license mutual exclusion). Merge errors eliminated — no merge step in DTO path |

## Tested End-to-End Paths

| Path | Test | Notes |
|------|------|-------|
| Assembler → Compiler | `AssemblerTest` | PetStore fixture, 61 assertions |
| Assembler → Compiler validation | `OpenApi31CompilerTest` | Compile + validate (required fields, license exclusion, array items) |
| 3.0 downgrade + validation | `OpenApi30CompilerTest` | Nullable, exclusiveMin, const→enum, $ref stripping, paths required, no examples |
| 3.2 forward-compat | `OpenApi32CompilerTest` | Version passthrough, webhooks, query method |
| Bridge → Classic pipeline | `ApiBridgeTest` | Full API example comparing against `docs/examples/specs/api/api-3.1.0.yaml` via `assertSpecEquals` (handles property ordering) |
| Bridge → Classic pipeline (simple) | `SpecAnnotationFactoryTest` | Basic conversion tests |
| Classic → DTO → Compiler | `SpecificationConverterTest` | Phase 2 bridge: full API example through `SpecificationConverter` → `OpenApi31Compiler` → assertSpecEquals |

## Deviations from Plan

| Plan says | Implementation does | Reason |
|-----------|--------------------|----|
| `AttributeStack` intermediate | Not needed; Assembler works directly with flat attribute lists | Nesting resolved via `allowedParents()` + reflection; no intermediate container required |
| Slot map on Assembler | `allowedParents()` method on each DTO + reflection-based `nestChild()` | More extensible; custom DTOs declare their own parents |
| `Specification` has finders (`schema()`, `find()`, `filter()`) | Only `add()` routing exists | Finders not yet needed; compiler accesses collections directly |
| `$security` as flat array on Specification | `$specification->openapi->security` via OpenApi DTO | More consistent — OpenApi is a DTO like all others |
| DTOs use `null` for "not set" exclusively | Schema uses `Generator::UNDEFINED` for `example`/`default`/`const` | Needed for properties where `null` is a valid JSON value |
| Converters as separate class(es) | Conversion logic lives in `SpecAnnotationFactory` directly | Single bridge class; converter extraction can happen later if needed |
| Factory + AttributeEnricher as separate component | Not yet started | Phase 1 bridge works without it |

## Generator Integration Strategy

Two fully separate pipelines behind `Generator::generate()`, toggled via mode:

```
Classic (default in v6, deprecated in v7, removed in v8):
  AnalyserInterface → Analysis → ProcessorPipeline → OA\OpenApi → jsonSerialize()

Spec (opt-in in v6, default in v7):
  SourceScanner → Assembler → Specification → AugmenterPipeline → Compiler → array
```

No shared interfaces between pipelines. No modifications to classic code. Generator is the router.

## Not Yet Touched

| Component | Roadmap reference | Purpose |
|-----------|-------------------|---------|
| `SourceScanner` | — | Discovers ReflectionClass[] from source paths (replaces analyser role in new pipeline) |
| `SpecAugmenter` interface | — | `augment(Specification): void` — type resolution, validation rules, docblocks, route attrs |
| `AugmenterPipeline` | — | Ordered list of augmenters, replaces ProcessorPipeline for new path |
| Generator mode switch | — | `setMode('spec')` fork in `generate()`, compiler selection, output serialization |
| Specification finders | `details/specification.md` | `schema()`, `operations()`, `find()`, `filter()`, `resolveRef()`, `schemaNameFor()` |
| Schema registry / `$ref` resolution | `v6-details.md` | Late-bound ref resolution after all schemas registered |
| CompilerExtension wiring | `details/compiler-extension.md` | Registration on compiler, Attachable → output dispatch |

## Key Dependencies

```
SourceScanner ──► Generator mode switch (need source discovery for new path)
SpecAugmenter ──► Type resolution, validation attr support
Schema registry ──► $ref resolution in compiler
Specification finders ──► Augmenters that need to look up schemas
```

Classic pipeline has NO dependencies on new work — it stays frozen.

## What Works End-to-End Today

Three tested paths through the prototype:

1. **Assembler → Compiler** (new pipeline, no classic involvement):
   `ReflectionClass → Assembler::collect() → Specification → OpenApi31Compiler::compile() → array`

2. **Assembler → Factory → Classic pipeline** (Phase 1 bridge):
   `ReflectionClass → SpecAnnotationFactory::build() → classic OA annotations → Generator → YAML`

3. **Classic → Converter → Compiler** (Phase 2 bridge):
   `Generator → OA\OpenApi → SpecificationConverter::convert() → Specification → OpenApi31Compiler::compile() → array`

All three produce correct output for complex fixtures (PetStore + full API example with operations, parameters, responses, callbacks, traits, enums, security). The full existing test suite (1097 tests) passes unchanged.
