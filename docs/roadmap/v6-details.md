# Tricky Details

Edge cases and hard problems identified during planning.

## Type Resolution

- `TypeInfoTypeResolver` has one version branch (`non-zero-int`: `not: {enum: [0]}`
  vs `not: {const: 0}`). Fix: always produce 3.1+ canonical form.
- Union types generate inline `Schema` objects (`oneOf`). During transition, these
  stay as `Schema` objects; new pipeline wraps them into tree nodes.
- Reuse `TypeInfoTypeResolver` in the new pipeline — don't rebuild what works.

## Promoted Constructor Parameters

Docblock comes from constructor, type from parameter. `SourceLocation` carries
enough info, or enrichment phase checks reflection directly.

## Class Inheritance

`ExpandClasses`/`ExpandInterfaces`/`ExpandTraits` create `allOf` compositions.
Same approach in new pipeline — enrichment produces `allOf` with `$ref` to parent.
Version-agnostic, correct pattern.

## `$ref` Resolution

Late-bound — must run after all schemas are registered. Both pipelines share a
schema registry so cross-references (new→old, old→new) work via string paths.
Self-referencing works because refs are name-based, not object-identity.

## Mixed Old + New Attributes

Factory routes each attribute to its pipeline by namespace. Both contribute to
same spec output. Mixing is per-class — a single class uses one namespace, but
different classes in the same file or project can use different namespaces.
Cross-referencing works because `$ref` paths are strings (`#/components/schemas/Foo`).

## Custom Annotations (`Attachable` Extension)

- **Extend core class** → inherits `Specification::add()` routing (DTO type determines collection)
- **Extend `Attachable`** + `#[AllowedParents]` → attaches to allowed parents
- **`CompilerExtension`** tells the compiler what to emit for custom `Attachable`s
- Unregistered `Attachable`s silently omitted (with optional diagnostic)

## Doctrine Positional Nesting

Only relevant to classic path. New attributes are always explicit (`properties: [...]`).
No concern for new pipeline.

## `Generator::$context` During Transition

Stays for classic path. New DTOs don't use it — `SourceLocation` assigned after
construction by the factory. Removed in v7 with classic path.

## `Context` as Custom Storage (Nelmio Pattern)

`NelmioApiDocBundle` stores arbitrary keys on `Context` between describer and processor
phases. New pipeline needs an alternative — likely a metadata bag on the tree node
or a separate side-channel registry.

## Performance

- Routing is per-class. In practice most files contain a single class, so
  typically only one pipeline runs per file. No double-processing of a class.
- Converter overhead (Phase 1): thin property mapping per DTO, no reflection or
  deep cloning. Cost is proportional to number of attributes, not their complexity.
- Schema registry is lightweight (names → objects, flat map).
- During Phase 1, both pipelines exist in memory but only one runs per class —
  no dual processing. The converter is a one-way transform, not a round-trip.

## `allOf` + Properties Merge

If a schema has both `properties` and `allOf`, properties move into a new `allOf`
entry. Structural transformation in assembler/enrichment, not compiler.

## Discriminator Mapping

DTO stores raw class names. Enrichment resolves to `$ref` paths (same timing as
property type resolution — after all schemas registered).
