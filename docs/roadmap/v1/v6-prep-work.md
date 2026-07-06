# v6 Prep Work

Changes to existing code that must happen before introducing new `Spec\*` pipeline code. These decouple shared utilities from classic-only concerns, making them reusable for both pipelines without duplicating logic.

## 1. Extract `Generator::UNDEFINED` to a Shared Utility

**Why:** 30+ files across the codebase import `OpenApi\Generator` solely for the `UNDEFINED` constant and `isDefault()` method. This creates a hard dependency on the classic Generator class from code that has no other reason to know about it — including future Spec pipeline classes that need the same "not set" sentinel.

**What's involved:**

- Create a standalone class (e.g., `OpenApi\Undefined`) with the constant and static helper:
  ```php
  class Undefined {
      public const VALUE = '@OA\Generator::UNDEFINED🙈';
      public static function isDefault(mixed ...$values): bool { ... }
  }
  ```
- Update all `use OpenApi\Generator` imports that only reference `UNDEFINED` / `isDefault()` to use the new class
- Keep `Generator::UNDEFINED` and `Generator::isDefault()` as thin proxies (BC) that delegate to the new utility
- Files affected: all annotations (`src/Annotations/*.php`), all processors (`src/Processors/*.php`), type resolvers, Spec DTOs that use UNDEFINED

**Outcome:** Any code — classic or spec — can use the undefined sentinel without importing Generator.

## 2. Decouple TypeResolver from `OA\Schema`

**Why:** The type resolution system contains valuable, reusable logic (PHP type → OpenAPI type/format mapping, TypeInfo integration, array/object inference) but is hardcoded to accept and mutate `OA\Schema` objects. The Spec pipeline needs the same type mapping but targets `Spec\Schema` instead.

**What's involved:**

- `TypeResolverInterface` methods (`mapNativeType`, `augmentSchemaType`) accept `OA\Schema` — needs parameterization
- `TypeInfoTypeResolver::setSchemaType()` instantiates `new OA\Schema(...)` for items/additionalProperties — schema creation should be caller's responsibility
- `AbstractTypeResolver::type2ref()` calls `OA\Components::ref()` — ref resolution is pipeline-specific
- `NATIVE_TYPE_MAP` and format mapping (`int` → `integer`/`int64`, etc.) are pure data, fully reusable

**Approach:**

- Extract type mapping data and logic into a generic helper (e.g., `TypeMapper`) that returns structured data (type string, format string, ref target) without creating schema objects
- Classic type resolvers call the helper and apply results to `OA\Schema`
- Spec augmenters call the same helper and apply results to `Spec\Schema`
- Keep existing `TypeResolverInterface` as-is for classic BC; new pipeline uses the extracted helper directly

**Outcome:** Type resolution logic is reusable without depending on `OA\Schema` or `OA\Components`.

## 3. Remove Version Branching from Type Resolvers

**Why:** `TypeInfoTypeResolver` checks `$schema->_context->isVersion('3.0.x')` to decide whether to produce `nullable: true` (3.0) or `type: ["string", "null"]` (3.1+). In the new pipeline, type resolution always produces the canonical 3.1+ form — version-specific output is the compiler's job.

**What's involved:**

- `TypeInfoTypeResolver` line ~89: branches on version context to set nullable vs type array
- Several places where nullable handling differs based on `isVersion()`
- The version context comes from `$schema->_context->version` which is set late in processing

**Approach:**

- Make type resolvers always produce canonical form (type array with `"null"` element for nullable)
- Move version-specific nullable handling into the existing processors that consume type resolver output (for classic) — these already know the target version
- For Spec pipeline: compilers already handle the canonical → version-specific conversion

**Outcome:** Type resolvers are version-agnostic. Version concerns live where they belong: classic processors and Spec compilers.

## 4. Extract Generic Docblock Parser

**Why:** `DocBlockTrait` contains high-quality PHPStan-based docblock parsing that's 90% generic — but it lives in `Processors\Concerns\` and has a few coupling points to `OA\AbstractAnnotation`. The Spec pipeline's docblock augmenter needs the same parsing without the OA-specific parts.

**What's involved:**

- Pure parsing methods (no OA dependencies, fully reusable):
  - `parsePhpDoc()` — PHPStan docblock AST
  - `formatType()` — union/intersection type formatting
  - `extractContent()` — full docblock text extraction
  - `summaryAndDescription()` — summary/description splitting
  - `parseVarLine()` — `@var` type + description
  - `getParamTags()` — `@param` tag extraction
  - `@example`, `@deprecated` detection

- OA-specific methods (stay in classic trait):
  - `isDocblockRoot()` — references `OA\Schema::class`, `OA\Property::class`
  - Priority map for annotation types

**Approach:**

- Create `OpenApi\Util\DocBlockParser` (or similar) containing all the pure parsing methods
- Classic `DocBlockTrait` delegates to the parser and adds OA-specific root detection
- Spec `DocblockAugmenter` uses the parser directly

**Outcome:** Docblock parsing reusable across both pipelines without duplication.

## 5. Namespace Moves

**Why:** Several utilities live in namespace locations that imply they're classic-pipeline-specific (`Analysers\`, `Processors\Concerns\`) but are actually generic. Moving them before v6 avoids awkward cross-namespace imports and signals their shared nature.

**What's involved:**

| Class | Current location | Proposed location | Reason |
|-------|-----------------|-------------------|--------|
| `TokenScanner` | `OpenApi\Analysers\TokenScanner` | `OpenApi\TokenScanner` | It's a PHP file scanner, not an "analyser" — both pipelines use it |
| `RefTrait` | `OpenApi\Processors\Concerns\RefTrait` | `OpenApi\Concerns\RefTrait` | Generic ref utilities, not processor-specific |

**Approach:**

- Move classes, update namespace declarations
- Leave class aliases at old locations for one major version (BC)
- Update internal imports

**Outcome:** Shared utilities have neutral namespace locations. Spec pipeline imports them without implying classic dependency.

---

## Execution Order

```
1. Undefined extraction     (unblocks everything — touched by all other changes)
2. TypeResolver decoupling  (biggest architectural change, benefits from #1)
3. Version branching removal (depends on #2 being clear)
4. Docblock parser extraction (independent, can parallel with #2/#3)
5. Namespace moves           (cosmetic, do last to minimize churn)
```

Items 1–3 are the critical path. Items 4–5 are nice-to-have that reduce friction but don't block the Spec pipeline from working.

## What Does NOT Need Prep Work

These are already reusable as-is:

- **Pipeline** — generic callable chaining, works for both augmenters and processors
- **SourceFinder** — framework-agnostic file discovery
- **TokenScanner** — no OA coupling (just namespace placement, item #5)
- **RefTrait** — no OA coupling (just namespace placement, item #5)
