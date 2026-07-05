# Implementation Status

Snapshot of what has been prototyped vs. what remains, as of the current `roadmap` branch.

## Prototyped

| Component | Location | Notes |
|-----------|----------|-------|
| DTOs (`OpenApi\Spec\*`) | `src/Spec/` | All essential classes (Schema, Property, Operation, Parameter, Response, etc.) |
| `AbstractAttribute` | `src/Spec/AbstractAttribute.php` | Base class with `sourceLocation`, `reflector`, `attachables`, `x`, `allowedParents()` |
| `OpenApiAttributeInterface` | `src/Spec/OpenApiAttributeInterface.php` | Marker interface for all spec DTOs |
| `SourceLocation` | `src/Spec/SourceLocation.php` | Value object with `fromReflector()` |
| `Specification` | `src/Spec/Specification.php` | Flat collection with typed buckets + `add()` routing |
| `Assembler` | `src/Spec/Assembler.php` | Reads attributes, resolves nesting via `allowedParents()`, populates Specification |
| `SpecAnnotationFactory` | `src/Spec/SpecAnnotationFactory.php` | Phase 1 bridge: converts Spec DTOs → classic OA annotations |
| `SpecCompilerInterface` | `src/Spec/SpecCompilerInterface.php` | Interface with `getVersion()`, `compile()`, `validate()` |
| `OpenApi31Compiler` | `src/Spec/OpenApi31Compiler.php` | Full implementation producing structured output arrays |
| `CompilerDiagnostics` / `Diagnostic` | `src/Spec/` | Error/warning collection for validation |
| `CompilerExtension` / `CompilerContext` | `src/Spec/` | Interfaces declared (not wired into compiler yet) |

## Not Yet Touched

| Component | Roadmap reference | Purpose |
|-----------|-------------------|---------|
| `AttributeStack` | `details/factory.md` | Per-element container with `find()`, `findStructural()`, `getOrCreate()` |
| `AttributeFactory` | `details/factory.md` | New-path factory: namespace routing, enricher invocation, metadata assignment |
| `AttributeEnricher` | `details/factory.md` | Translates non-OA attributes (validation, routing) into OA DTOs — key for Nelmio |
| Extract merge from classic constructor | `details/extract-merge.md` | Split `__construct()` into `captureContext()` / `assignProperties()` / `performMerge()` — needed for Phase 2 flip where classic annotations become thin DTOs |
| Specification finders | `details/specification.md` | `schema()`, `operations()`, `find()`, `filter()`, `resolveRef()`, `schemaNameFor()` |
| Schema registry / `$ref` resolution | `v6-details.md` | Late-bound ref resolution after all schemas registered |
| `ProcessorInterface` (v7) | `details/processor-interface.md` | `process(Specification)` — replaces `__invoke(Analysis)` |
| Phase 2 converter (classic → new DTO) | `v6-architecture.md` | Reverse bridge for when Specification becomes primary |
| `OpenApi30Compiler` | `v6-compilers.md` | Downgrade compiler: nullable, exclusiveMin semantics, feature gating |
| `OpenApi32Compiler` | `v6-compilers.md` | 3.2 additions: Tag `summary`/`parent`/`kind`, PathItem `query` |
| CompilerExtension wiring | `details/compiler-extension.md` | Registration on compiler, Attachable → output dispatch |
| SourceLocation on classic `AbstractAnnotation` | `details/sourcelocation.md` | Adding `$_sourceLocation` + `$_reflector` to existing annotations |
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

Two tested paths through the prototype:

1. **Assembler → Compiler** (new pipeline, no classic involvement):
   `ReflectionClass → Assembler::collect() → Specification → OpenApi31Compiler::compile() → array`

2. **Assembler → Factory → Classic pipeline** (Phase 1 bridge):
   `ReflectionClass → SpecAnnotationFactory::build() → classic OA annotations → Generator → YAML`

Both produce correct output for the PetStore fixture. The full existing test suite (1064 tests) passes unchanged.
