# Roadmap

High-level evolution plan for swagger-php.

## Vision

Evolve from a monolithic annotation-based architecture toward a layered system that:
- Decouples user-facing declaration (attributes) from the internal spec model
- Cleanly separates version-specific output into compilers
- Removes fragile patterns (static globals, dynamic properties, convention-based nesting)
- Makes extension natural without deep framework knowledge
- Preserves processors as the primary customization point for enriching and
  transforming the spec during generation

### Role of Processors

Processors remain central to the architecture across all versions. They are the
hook where users and downstream projects inject custom logic into the generation
pipeline — augmenting annotations with metadata from reflection, merging
controller-level defaults into operations, resolving types, validating constraints,
or transforming the spec structure.

Processors keep their current `__invoke(Analysis)` contract through v6. In v7,
they migrate to `ProcessorInterface::process(Specification)` — operating on flat
typed collections with finders instead of navigating the annotation tree. One
migration, at the major version boundary.

## Version Overview

### v6.x — Everything Additive (non-breaking)

All new features ship as additive, opt-in additions within v6.x minors. Nothing
breaks — existing code continues to work identically. The new architecture
grows alongside the classic path.

**Infrastructure (can ship first, in parallel):**
- [SourceLocation value object](v6-groundwork.md#sourcelocation)
- [Extract merge from constructor](v6-groundwork.md#extract-merge-from-constructor)
- [#[AllowedParents] for Attachables](v6-groundwork.md#allowedparents)
- [SpecCompilerInterface](v6-groundwork.md#speccompilerinterface)

**New pipeline (ships incrementally after infrastructure):**
- [Architecture](v6-architecture.md) — three-layer design
- [New attribute set](v6-attributes.md) — thin DTOs with composable Schema
- [Compiler layer](v6-compilers.md) — version-specific output
- [Downstream impact](v6-downstream.md) — L5-Swagger, Nelmio, openapi-extras

### v7 — Clean Break

Remove classic path entirely. Only new DTOs, assembler, and compilers remain.

- Remove `OpenApi\Annotations\*` and `OpenApi\Attributes\*` namespaces
- Remove `Context` (replaced by `SourceLocation`)
- Remove `Generator::$context`, `$_nested`, `$_parents`, `UNDEFINED`
- Remove doctrine/docblock support
- Remove `Analysis` (replaced by `Specification`)
- Remove `LegacyTypeResolver`
- Introduce `ProcessorInterface::process(Specification)` — replaces `__invoke(Analysis)`

## Constraints

- PHP attribute construction is opaque: `newInstance()` recursively constructs
  nested attributes. No way to inject context during construction.
- `NelmioApiDocBundle` is deeply coupled to current internals. v7 timeline must
  coordinate with their release cycle.
- `openapi-extras` features are candidates for absorption into core.

## Documents

### Top-level

| Document | Scope |
|----------|-------|
| [v6-groundwork.md](v6-groundwork.md) | Non-breaking infrastructure |
| [v6-architecture.md](v6-architecture.md) | Three-layer architecture design |
| [v6-attributes.md](v6-attributes.md) | New declaration attribute set |
| [v6-compilers.md](v6-compilers.md) | Version-specific compilation |
| [v6-details.md](v6-details.md) | Edge cases and tricky problems |
| [v6-downstream.md](v6-downstream.md) | Impact on downstream projects |
| [v6-user-migration.md](v6-user-migration.md) | End-user adoption path |
| [v6-testing-ci.md](v6-testing-ci.md) | Testing, docs, and CI strategy |

### Detail docs

| Document | Scope |
|----------|-------|
| [details/sourcelocation.md](details/sourcelocation.md) | `SourceLocation` + `$_reflector` design |
| [details/extract-merge.md](details/extract-merge.md) | Constructor split (bridge) |
| [details/allowed-parents.md](details/allowed-parents.md) | `#[AllowedParents]` design |
| [details/spec-compiler.md](details/spec-compiler.md) | `SpecCompilerInterface` |
| [details/schema-class.md](details/schema-class.md) | Full `Schema` DTO with all keywords |
| [details/compiler-extension.md](details/compiler-extension.md) | `CompilerExtension` + `CompilerContext` |
| [details/processor-interface.md](details/processor-interface.md) | `ProcessorInterface` (v7) |
| [details/factory.md](details/factory.md) | Factory + `AttributeStack` design |
| [details/assembler.md](details/assembler.md) | Assembler (replaces merge/`$_nested`) |
| [details/specification.md](details/specification.md) | `Specification` root container |
| [details/nelmio-migration.md](details/nelmio-migration.md) | Nelmio migration path + `AttributeEnricher` |
