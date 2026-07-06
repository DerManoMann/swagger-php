# Reusable Classic Code for DTO Pipeline

Analysis of existing `src/` code that can be adapted for the new `OpenApi\Spec\*` pipeline.

## Directly Reusable (no changes)

| Component | Location | Purpose |
|-----------|----------|---------|
| SourceFinder | `src/SourceFinder.php` | File/directory discovery (Symfony Finder wrapper) |
| TokenScanner | `src/Analysers/TokenScanner.php` | PHP-Parser class/interface/trait/enum scanning, method/property extraction |
| DocblockTrait | `src/Processors/Concerns/DocBlockTrait.php` | PHPStan-based docblock parsing: `@var`, `@param`, `@example`, `@deprecated`, summary/description |
| RefTrait | `src/Processors/Concerns/RefTrait.php` | FQN→ref-key normalization, `$ref` validation |

## Extract & Adapt

| Component | Reusable Logic | Adaptation |
|-----------|---------------|------------|
| ExpandEnums | `ReflectionEnum` case extraction, backed/unbacked detection, name vs value, `x-enumNames` | Target `Spec\Schema` instead of `OA\Schema`. Remove `Analysis` — pass schemas directly |
| CleanUnusedComponents | Iterative `$ref` graph walk, transitive dependency resolution (up to 10 passes) | Walk `Specification` collections instead of annotation tree |
| OperationId | Class+method→operationId generation, hash option | Get class/method from `Operation::getReflector()` instead of `_context` |
| DocBlockDescriptions | Summary/description extraction from PHPDoc above element | Use `getReflector()` for docblock instead of `_context->comment` |
| AugmentSchemas | Type→`$ref` resolution, infer `type: object` from properties, allOf merge | Work on `Specification->schemas` directly. TypeResolver targets `Spec\Schema` |
| AugmentProperties | Property name inference, `@var` type, const from reflection, `@example` | Use `Property::getReflector()`. Remove merge logic (Assembler handles nesting) |
| AugmentParameters | Name from reflector, required from nullability, schema from type | Use `Parameter::getReflector()`. Same type→schema logic |
| ExpandInterfaces/Traits | Inheritance traversal, allOf creation, method/property dedup | Use TokenScanner ancestry. Output allOf on `Spec\Schema` |
| MergePropertiesTrait | allOf + `$ref` parent pattern, dedup by property name | Apply during augmentation on `Specification` |
| TypeResolver | `NATIVE_TYPE_MAP`, TypeInfo integration, type→format mapping | Extract version-agnostic helper. Remove `isVersion()` branching (always canonical). Target `Spec\Schema` |

## Not Needed (eliminated by design)

| Component | Reason |
|-----------|--------|
| MergeIntoOpenApi/Components/PathItem | No merge step — Assembler builds Specification directly |
| CleanUnmerged | No merge leftovers |
| BuildPaths | Assembler builds paths from Operation DTOs |
| ExpandClasses | Handled by Assembler's `allowedParents()` + reflection |
| PathFilter | Simple array filter on `Specification->operations` — no recursive removal |
| AttributeAnnotationFactory | Replaced by Assembler (reads Spec attributes directly) |
| AnnotationTrait | Annotation-specific recursive removal; DTOs use simple array ops |

## Adaptation Strategy

### Phase 1: Copy portable traits

Create `Spec\Concerns\DocblockTrait` and `Spec\Concerns\RefTrait` — both have zero OA dependencies already. Copy as-is.

### Phase 2: Version-agnostic TypeResolver

Extract from `src/Type/`:
- `NATIVE_TYPE_MAP` (php→openapi type mapping)
- TypeInfo integration (Symfony TypeInfo component)
- Type→format mapping (`int`→`integer`/`int64`, etc.)

Remove:
- `isVersion()` branching — always produce canonical 3.1+ form (compilers handle downgrade)
- `OA\Schema` targeting — output to `Spec\Schema` properties

### Phase 3: Augmenters (replace Processors)

Each augmenter implements `SpecAugmenter` interface (`augment(Specification): void`):

| Augmenter | Based on | Key difference |
|-----------|----------|----------------|
| TypeAugmenter | AugmentSchemas + AugmentProperties + AugmentParameters | Single pass: resolves types on schemas, properties, and parameters using new TypeResolver |
| DocblockAugmenter | DocBlockDescriptions | Uses `getReflector()` for docblock access |
| EnumAugmenter | ExpandEnums | Targets `Spec\Schema` directly |
| InheritanceAugmenter | ExpandInterfaces + ExpandTraits | Builds allOf from class hierarchy using TokenScanner ancestry |
| OperationIdAugmenter | OperationId | Uses `Operation::getReflector()` for class/method context |
| CleanupAugmenter | CleanUnusedComponents | Iterative `$ref` scan over `Specification` collections |

### Phase 4: SourceScanner

Combines:
- `SourceFinder` (file discovery) — use as-is
- `TokenScanner` (class/method extraction) — use as-is
- Returns `ReflectionClass[]` for Assembler consumption

No new code needed beyond a thin orchestrator.

## Key Insight

The main effort is replacing `_context` lookups with `getReflector()` calls and targeting `Spec\*` properties instead of `OA\*`. The algorithms (type resolution, enum expansion, inheritance traversal, `$ref` scanning) are battle-tested and port directly. The merge machinery (~40% of classic processors) is entirely eliminated.
