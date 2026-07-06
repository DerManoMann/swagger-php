# Schema Class — Detail

## Design

Single class covering the full JSON Schema surface. `null` means "not declared"
(no `UNDEFINED` sentinel). Uses 3.1+ semantics as the canonical form.

```php
#[Attribute(TARGET_CLASS | TARGET_PROPERTY | IS_REPEATABLE)]
class Schema extends AbstractAttribute
{
    public function __construct(
        // Identity
        public ?string $name = null,
        public ?string $title = null,
        public ?string $description = null,

        // Core type
        public string|array|null $type = null,
        public ?string $format = null,
        public ?bool $nullable = null,

        // String constraints
        public ?int $minLength = null,
        public ?int $maxLength = null,
        public ?string $pattern = null,
        public ?string $contentMediaType = null,
        public ?string $contentEncoding = null,

        // Numeric constraints
        public int|float|null $minimum = null,
        public int|float|null $maximum = null,
        public int|float|null $exclusiveMinimum = null,
        public int|float|null $exclusiveMaximum = null,
        public int|float|null $multipleOf = null,

        // Array constraints
        public Schema|string|null $items = null,
        public ?int $minItems = null,
        public ?int $maxItems = null,
        public ?bool $uniqueItems = null,
        public ?array $prefixItems = null,
        public Schema|bool|null $contains = null,
        public ?int $minContains = null,
        public ?int $maxContains = null,

        // Object constraints
        public ?array $properties = null,
        public ?array $required = null,
        public Schema|bool|null $additionalProperties = null,
        public ?array $patternProperties = null,
        public ?int $minProperties = null,
        public ?int $maxProperties = null,
        public Schema|bool|null $unevaluatedProperties = null,

        // Composition
        public ?array $allOf = null,
        public ?array $anyOf = null,
        public ?array $oneOf = null,
        public Schema|null $not = null,

        // Conditional (3.1+)
        public Schema|null $if = null,
        public Schema|null $then = null,
        public Schema|null $else = null,

        // Enum/const
        public ?array $enum = null,
        public mixed $const = null,

        // Examples
        public mixed $example = null,
        public ?array $examples = null,

        // Meta
        public ?bool $deprecated = null,
        public ?bool $readOnly = null,
        public ?bool $writeOnly = null,
        public mixed $default = null,

        // Reference
        public ?string $ref = null,

        // Discriminator
        public ?string $discriminatorProperty = null,
        public ?array $discriminatorMapping = null,

        // External docs
        public ?string $externalDocs = null,

        // Escape hatch
        public ?array $extra = null,
    ) {}
}
```

## Mutability

DTO properties are mutable (`public`, no `readonly`). Processors and enrichers need
to modify DTOs after construction — setting constraints, resolving types, merging
inherited properties. Only `$_sourceLocation` and `$_reflector` (inherited from
`AbstractAttribute`) are `readonly` — set once by the factory, never changed.

## Version-agnostic usage

Users write in 3.1+ semantics. The compiler handles downgrade:

```php
// User writes (works for any target version):
#[Schema(type: 'string', nullable: true)]
// 3.0 compiler → {"type": "string", "nullable": true}
// 3.1 compiler → {"type": ["string", "null"]}

#[Schema(type: 'integer', exclusiveMinimum: 5)]
// 3.0 compiler → {"type": "integer", "minimum": 5, "exclusiveMinimum": true}
// 3.1 compiler → {"type": "integer", "exclusiveMinimum": 5}
```

## Why not split by type?

JSON Schema doesn't enforce single-type schemas. A schema can combine string
and numeric constraints (e.g. for validation of inputs that could be either).
Splitting would force users to pick one, losing expressiveness.

## The `nullable` convenience

`nullable` is a 3.0 concept. In 3.1+ it's `type: [x, "null"]`. The DTO accepts
both forms as a user convenience — the assembler normalizes to canonical form (type
array), the compiler outputs the version-correct syntax.

## The `extra` escape hatch

For `x-` extensions, unknown keywords, or new JSON Schema features not yet modeled:

```php
#[Schema(type: 'string', extra: ['x-custom' => 'value', '$comment' => 'note'])]
```

Keys in `extra` are merged into the output object at the top level.
