# SourceLocation — Detail

## Design

```php
final class SourceLocation
{
    public function __construct(
        public readonly ?string $filename = null,
        public readonly ?int $line = null,
        public readonly ?string $namespace = null,
        public readonly ?string $class = null,
        public readonly ?string $interface = null,
        public readonly ?string $trait = null,
        public readonly ?string $enum = null,
        public readonly ?string $method = null,
        public readonly ?string $property = null,
        public readonly ?bool $static = null,
        public readonly ?array $uses = null,
        public readonly string|array|null $extends = null,
        public readonly ?array $implements = null,
    ) {}

    public function fullyQualifiedName(?string $source): ?string { /* ... */ }
}
```

## How it lands

- Add `readonly ?SourceLocation $_sourceLocation` and `readonly ?\Reflector $_reflector`
  to `AbstractAnnotation` alongside `_context`
- Factories populate both from the `\Reflector` after construction
- Static factory methods: `SourceLocation::fromReflector(\Reflector)` and
  `SourceLocation::fromContext(Context)` for migration
- Both properties are `readonly` — set once by the factory, immutable after that

## `$_reflector` — live reflection handle

The `\Reflector` is attached directly on the DTO as a sibling to `$_sourceLocation`.
This avoids double lookup (the analyser already has it) and gives processors direct
access for type inference, docblock reading, etc.

```php
// On AbstractAnnotation (v6.x addition):
public readonly ?SourceLocation $_sourceLocation = null;
public readonly ?\Reflector $_reflector = null;
```

`SourceLocation` is the immutable coordinates (file, line, class, method).
`$_reflector` is the transient live handle — consumed by enrichment processors,
not part of serialization or identity. Same lifecycle as `_context` today.

## `AbstractAttribute` — base class for new DTOs

All new DTOs (`OpenApi\Spec\*`) extend `AbstractAttribute`. It provides the two
readonly metadata properties and an `attachables` collection:

```php
abstract class AbstractAttribute
{
    public readonly ?SourceLocation $_sourceLocation = null;
    public readonly ?\Reflector $_reflector = null;

    /** @var list<object> */
    public array $attachables = [];
}
```

- `Operation`, `Schema`, `Property`, `Parameter`, `Response`, etc. all extend it
- `Attachable` does NOT extend `AbstractAttribute` — it's a separate lightweight
  base for custom extension objects that attach to any DTO
- Replaces `AbstractAnnotation` for the new namespace (no `merge()`, no `$_nested`,
  no `jsonSerialize()`, no `UNDEFINED`)
- Mutable public properties for spec data; only `$_sourceLocation` and `$_reflector`
  are `readonly`

## Why not just fix `Context`?

`Context` serves multiple roles:
- Source metadata (file, line, class, method)
- Tree navigation (parent chain, `with()`, `root()`)
- Annotation registration (`annotations[]`)
- Custom storage (processors write arbitrary keys)
- Version / logger carrier

`SourceLocation` is only the first role — pure, typed, no side effects. The other
roles stay on `Context` during v6.x transition.

## Promoted constructor param handling

For promoted properties, the source comes from the constructor parameter but the
property name comes from the class. `SourceLocation` captures both:
- `property` is set (it's a class property)
- `method` is NOT set (it's not "inside" a method for annotation purposes)
- `$_reflector` is the `\ReflectionProperty` — processors that need the constructor
  docblock can navigate from there via `$ref->getDeclaringClass()->getConstructor()`
