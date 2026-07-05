# v6.x — Non-Breaking Groundwork

Changes that ship without breaking existing users or downstream extensions.
Each creates a seam that the new architecture builds on.

## Summary

| Change | Purpose | Enables |
|--------|---------|---------|
| `SourceLocation` | Typed source metadata | Drop `Context` dynamic properties |
| Extract merge | Separable construction steps | Attributes become pure DTOs |
| `#[AllowedParents]` | Declarative `Attachable` restrictions | Carries forward permanently |
| `SpecCompilerInterface` | Version-aware output layer | Drop `jsonSerialize()` version logic |

All four can be developed in parallel. Zero impact on existing behavior.

---

## SourceLocation

Typed, immutable value object for source metadata. Replaces the need to navigate
`Context`'s dynamic `__get()` chain.

Added to `AbstractAnnotation` alongside `_context`. Factories populate it after
construction. Processors can start using it. `Context` continues unchanged.

See [details](details/sourcelocation.md).

## Extract Merge from Constructor

Split `AbstractAnnotation::__construct()` into property assignment and merge/nesting
as separate internal methods. Constructor behavior stays identical — this is a pure
internal refactor.

Prerequisite for the new DTO path where the assembler handles merge externally.

See [details](details/extract-merge.md).

## #[AllowedParents]

Declarative attribute replacing `allowedParents()` method on `Attachable` subclasses.
Variadic class-string parameters. Absence means unrestricted.

```php
#[AllowedParents(Operation::class, PathItem::class)]
class RateLimit extends Attachable { ... }
```

Carries forward permanently — `Attachable` is the extension mechanism in all versions.
`$_nested`/`$_parents` left as-is (removed in v7).

See [details](details/allowed-parents.md).

## SpecCompilerInterface

Extract version-aware serialization into a dedicated compiler. `Generator` uses it
when configured; falls back to `jsonSerialize()` otherwise.

This is where all `isVersion()` branching in `jsonSerialize()` migrates to:
nullable handling, `exclusiveMinimum` semantics, type arrays, feature gating.

See [details](details/spec-compiler.md).

