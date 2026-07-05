# Three-Layer Architecture

## The Problem

Annotations currently serve three roles simultaneously:
1. **Declaration** — what the user writes in code
2. **Spec model** — the internal representation of the OpenAPI document
3. **Serialization** — they serialize to JSON/YAML with version-aware logic

This forces version differences, nesting rules, merge logic, and context management
all into one class hierarchy.

## The Solution

```
┌─────────────────────────────────────────────────┐
│  Declaration Layer (Attributes / DTOs)          │
│  • Pure value containers, no behavior           │
│  • Version-agnostic (union of all versions)     │
│  • PHP instantiates them; no intervention needed│
│  • Each carries SourceLocation + Reflector      │
│    (readonly, assigned by factory post-construct)│
└──────────────────────┬──────────────────────────┘
                       │ Factory assigns SourceLocation + Reflector
                       ▼
┌─────────────────────────────────────────────────┐
│  Assembly Layer                                 │
│  • Resolves one attribute stack → root DTO(s)   │
│  • Pairs structural + Schema attrs              │
│  • Nested attrs already in place (constructor)  │
│  • Result added to Specification via add*()     │
└──────────────────────┬──────────────────────────┘
                       │ Root DTOs → Specification
                       ▼
┌─────────────────────────────────────────────────┐
│  Specification (root container, not a DTO)      │
│  • Flat typed collections (not nested tree)     │
│  • Finders: find(), filter(), operations()      │
│  • Schema registry + $ref resolution            │
│  • Processors query/mutate via this             │
└──────────────────────┬──────────────────────────┘
                       │ Processors enrich
                       ▼
Note: this diagram shows the end-state (Phase 2 / v7).
In Phase 1, new DTOs convert to classic annotations and
flow through the existing pipeline instead.
┌─────────────────────────────────────────────────┐
│  Compilation Layer (Version-Specific)           │
│  • One compiler per target version              │
│  • Handles type conversions (nullable, etc.)    │
│  • Validates version constraints                │
│  • Produces JSON/YAML output                    │
└─────────────────────────────────────────────────┘
```

## Key Decisions

- **New attribute namespace** — `OpenApi\Spec\*` (aliased as `OA` in user code)
- **Classic path stays alive through v6** — both produce the same spec output
- **Internal model uses 3.1+ (JSON Schema 2020-12) as canonical form** — compilers downgrade
- **`LegacyTypeResolver` removed in v7** — only `TypeInfoTypeResolver` remains
- **Doctrine/docblock support deprecated in v6, removed in v7**

## Coexistence — Evolutionary Approach

New DTOs and classic annotations coexist within v6.x via a phased flip:

### Phase 1: New → Classic (ships incrementally in v6.x minors)

New DTOs are converted into classic annotations and fed into the existing pipeline:

```
New DTOs → Converter → classic annotations → processors → jsonSerialize()
```

This allows new DTOs to ship incrementally (start with `Schema`, then `Property`,
etc.) without requiring `Specification` or compilers to be complete. Each new DTO
just needs a converter to its classic equivalent. Existing tests, processors,
Nelmio — everything keeps working untouched.

### Phase 2: Classic → New (flip, later v6.x minor)

Once critical mass is reached (`Specification` + compiler proven, most DTOs exist),
the direction flips:

```
Classic annotations → Converter → new DTOs → Specification → compiler
```

Now `Specification` is the primary path and classic annotations are the
compatibility shim. The classic pipeline still works but delegates to the new one.

### Phase 3: New only (v7)

Classic path removed entirely. Only new DTOs → `Specification` → compiler remains.
Converters deleted.

### Converters

Converters are thin mechanical mappers between DTO ↔ annotation. No complex
logic — just property mapping:

```php
// Phase 1: new DTO → classic annotation
$annotation = SpecToAnnotation::convert($operationDto);
// Maps: $dto->path → $annotation->path, $dto->summary → $annotation->summary, etc.

// Phase 2: classic annotation → new DTO
$dto = AnnotationToSpec::convert($annotation);
```

One converter per DTO type that has a classic equivalent. Partial coverage is
fine — only converted types flow through the other pipeline; unconverted types
stay in their native path. The "flip" happens when all types have converters and
`Specification` + compiler are proven in CI.

**null ↔ UNDEFINED mapping:** New DTOs use `null` for "not set"; classic annotations
use `Generator::UNDEFINED`. Converters map between them: `null` → `UNDEFINED` for
unset fields, actual values pass through unchanged. For `$ref` strings specifically,
a non-null `$ref` passes as-is (the classic path handles `$ref` resolution in
processors); `null` becomes `UNDEFINED` so the classic path knows not to emit it.

### Why this works

- New DTOs can ship one at a time — no big-bang switch
- Each phase is independently testable (same output, different internal path)
- Downstream projects don't notice the flip — output is identical
- Converter code is throwaway (gone in v7) but buys incremental delivery
- Cross-referencing works via string-based `$ref` paths throughout

The assembler resolves one attribute stack into root-level DTOs. DTOs support
nesting via constructor args for disambiguation, but have no merge behavior.

See [details/assembler.md](details/assembler.md) and
[details/specification.md](details/specification.md).

## Extension Model

Three extension mechanisms:

- **Extend a core class** → inherits `Specification::add()` routing (DTO type determines collection)
- **Extend `Attachable`** + `#[AllowedParents]` → attaches to allowed parents,
  output via `CompilerExtension`
- **`AttributeEnricher`** → translate third-party attributes (framework validation,
  routing, etc.) into OA DTOs during factory discovery. Keeps swagger-php
  framework-agnostic while enabling Nelmio-style constraint translation as a
  first-class hook. See [details/nelmio-migration.md](details/nelmio-migration.md).

## Implementation Order

All within v6.x as additive, non-breaking changes:

```
Infrastructure (parallel, ships first):
  SourceLocation, extract-merge, #[AllowedParents], SpecCompilerInterface

Phase 1 (sequential, ships incrementally):
  1. New DTOs (OpenApi\Spec\*) — can ship one at a time
  2. Factory + AttributeStack + AttributeEnricher
  3. Assembler — consumes stacks, produces DTOs
  4. Converters (DTO → classic annotation) — enables new attrs to work
     via existing pipeline immediately

Phase 2 (flip — requires all of the above + these):
  5. Specification — flat collections, finders, registry
  6. Compiler (per version) — walks Specification, produces output
  7. Converters (classic → DTO) — feeds old attrs into new pipeline
```

Phase 1 items can ship incrementally across minor releases. Phase 2 is the flip
point — only triggered when coverage is complete and CI proves correctness.

## What's Removed in v7

- `LegacyTypeResolver`
- Classic annotation/attribute namespaces (`OpenApi\Annotations\*`, `OpenApi\Attributes\*`)
- `Context` class, `Generator::$context`
- `$_nested`, `$_parents`, `merge()`, `_unmerged`, `UNDEFINED`
- `Analysis` (replaced by `Specification`)
- `jsonSerialize()` on annotations
- Doctrine annotation support

## What's New in v7

- `ProcessorInterface::process(Specification)` — replaces `__invoke(Analysis)`
