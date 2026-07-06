# User Adoption Guide

## Who this is for

Users writing `#[OA\Schema]`, `#[OA\Get]`, etc. in their PHP projects today.
Not library authors (see [v6-downstream.md](v6-downstream.md) for that).

## The short version

- **v6.x changes nothing for you.** Your existing attributes keep working.
- **New attributes are available** in the `OpenApi\Spec` namespace — adopt at
  your own pace, or don't.
- **v7 is the breaking change** — old attributes removed. But v6.x gives you
  time to migrate incrementally.

## What stays the same in v6.x

- `#[OA\Schema]`, `#[OA\Get]`, `#[OA\Property]`, etc. all work
- Your existing processors work (`__invoke(Analysis)`)
- `Generator` API is unchanged
- Output (JSON/YAML) is identical
- Doctrine docblock annotations still work (deprecated, removed in v7)

## What's new in v6.x

### New attribute namespace: `OpenApi\Spec`

```php
use OpenApi\Spec as OA;

#[OA\Get('/users/{id}')]
#[OA\PathParam('id', schema: new OA\Schema(type: 'integer', minimum: 1))]
#[OA\Returns(User::class)]
public function getUser(int $id): User { /* ... */ }
```

### Simpler Schema composition

No more duplicated properties across `Property`/`Parameter`/`Header`. JSON Schema
keywords live on `#[Schema]`, stacked on the structural attribute:

```php
// Classic style:
#[OA\Property(type: 'string', minLength: 3, maxLength: 50, pattern: '^[a-z]+$')]
public string $slug;

// New style:
use OpenApi\Spec as OA;

#[OA\Property]
#[OA\Schema(minLength: 3, maxLength: 50, pattern: '^[a-z]+$')]
public string $slug;
```

### No `UNDEFINED` sentinel

New DTOs use `null` for "not set" — standard PHP semantics.

### Version-agnostic attributes

Write once, target any OpenAPI version (3.0, 3.1, 3.2). The compiler handles
version-specific output (nullable syntax, exclusiveMinimum semantics, etc.).

## Incremental adoption

You can mix old and new attributes **per-class** — a single class uses one namespace,
but different classes in the same file or project can use different namespaces.
The factory routes by namespace:

```php
// file: src/Controller/UserController.php — uses new attrs
use OpenApi\Spec as OA;
#[OA\Get('/users')]
// ...

// file: src/Controller/LegacyController.php — uses old attrs
use OpenApi\Attributes as OA;
#[OA\Get(path: '/legacy')]
// ...
```

Both contribute to the same spec output. Cross-referencing works via `$ref` strings.

## Migration steps (when ready)

1. **Change the use statement:** `use OpenApi\Attributes as OA` → `use OpenApi\Spec as OA`
2. **Simplify schemas:** Move JSON Schema keywords from structural attrs to stacked `#[Schema]`
3. **Drop `UNDEFINED` checks:** Replace with `null` / `??=`
4. **Use composition:** Let type inference do its job — less boilerplate needed

Each file can be migrated independently. No flag day required.

## What breaks in v7

- `OpenApi\Attributes\*` and `OpenApi\Annotations\*` namespaces removed
- `Generator::UNDEFINED` removed
- `LegacyTypeResolver` removed
- Processors must implement `ProcessorInterface::process(Specification)`
- Doctrine docblock support removed
- `Context` class removed (use `SourceLocation` / `$_reflector` instead)

## Custom Attachables

If you have custom `Attachable` subclasses:
- They keep working in v6.x (both old and new path support `attachables`)
- Add `#[AllowedParents(...)]` in v6.x to declare valid parent types
- In v7, register a `CompilerExtension` if your Attachable produces spec output

## Custom processors

- Keep using `__invoke(Analysis)` through v6.x — works unchanged
- In v7, implement `ProcessorInterface::process(Specification)` — access DTOs
  via flat collections and finders instead of annotation tree navigation
