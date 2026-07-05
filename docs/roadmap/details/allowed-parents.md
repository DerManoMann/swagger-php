# #[AllowedParents] — Detail

## Design

```php
#[Attribute(Attribute::TARGET_CLASS)]
class AllowedParents
{
    /** @var list<class-string> */
    public readonly array $parents;

    public function __construct(string ...$parents)
    {
        $this->parents = $parents;
    }
}
```

Variadic — no brackets needed at call site:

```php
#[AllowedParents(Operation::class, PathItem::class)]
class RateLimit extends Attachable { ... }
```

## Semantics

- Present with classes → restricted to those parent types
- Absent → unrestricted (any parent, same as `allowedParents()` returning `null`)
- Present with empty list → no valid parents (class-level only)

## Factory integration

The factory reads `#[AllowedParents]` via reflection (one read per class, cached).
Falls back to `allowedParents()` if the attribute is not present.

```php
$allowedParents = $this->resolveAllowedParents($annotation);
// 1. Check for #[AllowedParents] attribute on the class
// 2. Fall back to $annotation->allowedParents()
// 3. null means unrestricted
```

## Deprecation of `allowedParents()`

`allowedParents()` method is deprecated in v6.x. Downstream authors migrate:

```php
// Before
class RateLimit extends Attachable {
    public function allowedParents(): ?array {
        return [Operation::class, PathItem::class];
    }
}

// After
#[AllowedParents(Operation::class, PathItem::class)]
class RateLimit extends Attachable { }
```

## Why this carries forward

`Attachable` is the extension mechanism in all versions. Custom attributes that
don't extend core classes attach via this path. `#[AllowedParents]` is the
declarative way to restrict where they land — it's not tied to the classic
annotation model.

## Why not also `#[NestedAnnotation]`?

`$_nested` (parent-side nesting rules) only serves the classic annotation model.
The new DTO pipeline doesn't use it — the assembler knows the mapping externally.
Introducing `#[NestedAnnotation]` would ask downstream authors to adopt it in v6,
then drop it in v7. Not worth the churn.
