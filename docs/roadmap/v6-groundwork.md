# v6.x — Non-Breaking Groundwork

Changes that ship without breaking existing users or downstream extensions.
Each creates a seam that the new architecture builds on.

## Summary

| Change | Purpose | Enables |
|--------|---------|---------|
| `SourceLocation` | Typed source metadata | Drop `Context` dynamic properties |
| Extract merge | Separable construction steps | Attributes become pure DTOs |
| `allowedParents()` | Unified parent declaration method | Carries forward permanently |
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

## allowedParents()

Method on `AbstractAttribute` that all DTOs override to declare valid parent types.
Same method that `Attachable` subclasses already use — unified for all attributes.

```php
class Schema extends AbstractAttribute
{
    public function allowedParents(): ?array
    {
        return [Property::class, Parameter::class, Header::class, MediaType::class];
    }
}
```

Replaces both `$_parents` statics (removed in v7) and the previously considered
`#[AllowedParents]` attribute. No reflection, no caching — just a method call.

See [details](details/allowed-parents.md).

## SpecCompilerInterface

Extract version-aware serialization into a dedicated compiler. `Generator` uses it
when configured; falls back to `jsonSerialize()` otherwise.

This is where all `isVersion()` branching in `jsonSerialize()` migrates to:
nullable handling, `exclusiveMinimum` semantics, type arrays, feature gating.

See [details](details/spec-compiler.md).

