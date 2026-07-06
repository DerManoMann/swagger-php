# allowedParents() — Detail

## Design

Method on `AbstractAttribute` that subclasses override to declare valid parents:

```php
abstract class AbstractAttribute implements OpenApiAttributeInterface
{
    /**
     * @return list<class-string<AbstractAttribute>>|null null = unrestricted
     */
    public function allowedParents(): ?array
    {
        return null;
    }
}
```

Subclasses override:

```php
class Schema extends AbstractAttribute
{
    public function allowedParents(): ?array
    {
        return [Property::class, Parameter::class, Header::class, MediaType::class];
    }
}
```

## Semantics

- Returns class list → restricted to those parent types
- Returns `null` → unrestricted (any parent)
- Returns `[]` → no valid parents (root-level only, never nests)

## Assembler integration

The assembler calls `allowedParents()` directly — no reflection, no caching needed:

```php
$allowedParents = $instance->allowedParents();
// null  → unrestricted (Attachable default)
// []    → root-level only (Operation, Info, Tag, etc.)
// [...]​ → nest if exactly one match exists on the same target
```

## Unified mechanism

This replaces both:
- Classic `$_parents` static (removed in v7)
- The previously planned `#[AllowedParents]` attribute (not needed)

One mechanism for everything: core DTOs, Attachable, and downstream extensions.
Same method signature that Attachable already uses — downstream subclasses
continue to work unchanged.

## Migration for Attachable subclasses

No migration needed — `allowedParents()` already exists on `Attachable`.
Downstream authors already using the method keep their code as-is.

```php
// Works today, works in v7
class RateLimit extends Attachable
{
    public function allowedParents(): ?array
    {
        return [Operation::class];
    }
}
```

## Why not an attribute?

- No reflection overhead — just a method call
- Already the pattern downstream Attachable users know
- Simpler: one mechanism, not two (attribute + fallback method)
- No caching complexity
