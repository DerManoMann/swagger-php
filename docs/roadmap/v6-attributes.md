# New Attribute Set

## Principles

1. **Pure DTOs** — no inheritance from `AbstractAnnotation`, no behavior, no side effects
2. **Version-agnostic** — accept the union of all versions; compiler sorts it out
3. **Composition over duplication** — `Schema` is separate, stacked when needed
4. **User intent over spec mirror** — ergonomic shortcuts for common patterns
5. **Full JSON Schema surface** — all keywords available on Schema when needed

## Two Layers

### API Layer — OpenAPI structural concepts

Endpoints, parameters, responses, security, servers. Designed for ergonomics.

```php
#[Get('/users/{id}')]
#[PathParam('id', schema: new Schema(type: 'integer', minimum: 1))]
#[Returns(User::class)]
```

### Schema Layer — JSON Schema type system

One `Schema` class with the full keyword surface. Never duplicated into
`Property`/`Parameter`/etc.

## Composition Over Duplication

Structural attributes (`Property`, `QueryParam`, `PathParam`) stay thin — only
role-specific fields. All JSON Schema keywords live exclusively on `#[Schema]`:

```php
#[Property]
#[Schema(minLength: 3, maxLength: 50, pattern: '^[a-z]+$')]
public string $email;
```

This eliminates the current problem where `Property`, `Parameter`, `Items`, `Header` all
replicate `Schema`'s 40+ constructor params. Subclassing becomes trivial:

```php
#[Attribute(TARGET_PROPERTY)]
class Slug extends Property {
    public function __construct() {
        parent::__construct(description: 'URL-safe identifier');
    }
}

// Usage
#[Slug]
#[Schema(pattern: '^[a-z0-9-]+$', maxLength: 100)]
public string $slug;
```

## Schema Class

Single class covering the full JSON Schema surface. `null` means "not declared"
(no `UNDEFINED` sentinel). Uses 3.1+ semantics — compiler downgrades for 3.0.

Key property groups: core type, string constraints, numeric constraints, array
constraints, object constraints, composition (allOf/anyOf/oneOf), conditionals
(if/then/else), enum/const, meta, references, escape hatch (`extra` array).

See [details](details/schema-class.md).

## Assembly Rule

The assembler collects attributes on a target and merges:
1. Find the structural attribute (`Property`, `QueryParam`, etc.)
2. Find the optional `#[Schema]` on the same target
3. Structural fields from structural attribute, type/validation from `Schema`
4. If no `Schema` stacked, infer type from PHP reflection

## HTTP method attributes → Operation

`Get`, `Post`, `Put`, `Delete`, `Patch`, `Options`, `Head`, `Trace` are subclasses
of `Operation`. Each sets `$method` in its constructor:

```php
class Get extends Operation
{
    public function __construct(string $path, ...$args)
    {
        parent::__construct(path: $path, method: 'get', ...$args);
    }
}
```

The assembler and `Specification` only deal with `Operation` — the subclasses are
syntactic sugar for users. This continues the pattern from the current codebase.

## Resolved Design Decisions

- **Single `Schema` class** — not split by type. JSON Schema is composable.
- **`$ref` + properties** — DTO stores both. Compiler validates per version.
- **Class-level `Schema`** — IS the definition. Constraints in its constructor params.

## Resolved: Namespace

`OpenApi\Spec\*` — e.g. `OpenApi\Spec\Schema`, `OpenApi\Spec\Operation`,
`OpenApi\Spec\Property`. Users alias as `use OpenApi\Spec as OA;`.

Distinct from `OpenApi\Annotations` and `OpenApi\Attributes`. Could move back to
`Attributes` in a future version once the old namespace is gone — only the alias
changes in user code.
