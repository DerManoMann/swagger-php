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
| `SpecCompilerInterface` | `src/Spec/SpecCompilerInterface.php` | Interface with `getVersion()`, `compile()`, `validate()` |
| `OpenApi31Compiler` | `src/Spec/OpenApi31Compiler.php` | Full declarative implementation with `filter()` stripping null/UNDEFINED/[] |
| `CompilerDiagnostics` / `Diagnostic` | `src/Spec/` | Error/warning collection for validation |
| `CompilerExtension` / `CompilerContext` | `src/Spec/` | Interfaces declared (not wired into compiler yet) |
| `Generator::UNDEFINED` reuse | `src/Spec/Schema.php` | `example`, `default`, `const` use UNDEFINED to distinguish "not set" from explicit null |
| `SpecificationConverter` | `src/Spec/SpecificationConverter.php` | Phase 2 bridge: classic `OA\OpenApi` → `Specification`; handles nullable→type array, callbacks with nested annotations, $ref+description |
| `OpenApi30Compiler` | `src/Spec/OpenApi30Compiler.php` | Downgrade compiler: type array→single+nullable, exclusiveMin boolean form, const→enum, strips 3.1+ keywords |
| `OpenApi32Compiler` | `src/Spec/OpenApi32Compiler.php` | Extends 3.1 with forward-looking Tag summary/parent/kind and PathItem query |

## Tested End-to-End Paths

| Path | Test | Notes |
|------|------|-------|
| Assembler → Compiler | `AssemblerTest` | PetStore fixture, 61 assertions |
| Assembler → Compiler validation | `OpenApi31CompilerTest` | Compile + validate tests |
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

## Not Yet Touched

| Component | Roadmap reference | Purpose |
|-----------|-------------------|---------|
| `AttributeFactory` | `details/factory.md` | New-path factory: namespace routing, enricher invocation, metadata assignment |
| `AttributeEnricher` | `details/factory.md` | Translates non-OA attributes (validation, routing) into OA DTOs — key for Nelmio |
| Extract merge from classic constructor | `details/extract-merge.md` | Split `__construct()` into `captureContext()` / `assignProperties()` / `performMerge()` — needed for Phase 2 flip |
| Specification finders | `details/specification.md` | `schema()`, `operations()`, `find()`, `filter()`, `resolveRef()`, `schemaNameFor()` |
| Schema registry / `$ref` resolution | `v6-details.md` | Late-bound ref resolution after all schemas registered |
| `ProcessorInterface` (v7) | `details/processor-interface.md` | `process(Specification)` — replaces `__invoke(Analysis)` |
| Phase 2 converter (classic → new DTO) | `v6-architecture.md` | ✅ Prototyped as `SpecificationConverter` |
| `OpenApi30Compiler` | `v6-compilers.md` | ✅ Prototyped: downgrade compiler with nullable, exclusiveMin, const→enum, strips 3.1+ keywords |
| `OpenApi32Compiler` | `v6-compilers.md` | ✅ Prototyped: extends 3.1 with forward-looking Tag/PathItem support |
| CompilerExtension wiring | `details/compiler-extension.md` | Registration on compiler, Attachable → output dispatch |
| Generator integration | `details/spec-compiler.md` | `setCompiler()` on Generator, compiler as output path |
| Namespace routing | `details/factory.md` | Per-class routing: `OpenApi\Spec\*` → new pipeline, `OpenApi\Attributes\*` → classic |

## Key Dependencies

```
Extract-merge refactor ──► Phase 2 flip (classic attrs as thin DTOs)
AttributeEnricher ──► Nelmio/framework integration
Specification finders ──► ProcessorInterface (v7)
Schema registry ──► $ref resolution in compiler
OpenApi30Compiler ──► Full version coverage (most complex: downgrade logic)
```

## What Works End-to-End Today

Three tested paths through the prototype:

1. **Assembler → Compiler** (new pipeline, no classic involvement):
   `ReflectionClass → Assembler::collect() → Specification → OpenApi31Compiler::compile() → array`

2. **Assembler → Factory → Classic pipeline** (Phase 1 bridge):
   `ReflectionClass → SpecAnnotationFactory::build() → classic OA annotations → Generator → YAML`

3. **Classic → Converter → Compiler** (Phase 2 bridge):
   `Generator → OA\OpenApi → SpecificationConverter::convert() → Specification → OpenApi31Compiler::compile() → array`

All three produce correct output for complex fixtures (PetStore + full API example with operations, parameters, responses, callbacks, traits, enums, security). The full existing test suite (1066 tests) passes unchanged.
