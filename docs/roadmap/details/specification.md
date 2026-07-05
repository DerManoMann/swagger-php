# Specification — Detail

## Role

`Specification` is the root container for the OpenAPI spec being built. It is NOT
a DTO — it's infrastructure that holds DTOs in flat collections, provides finders,
and the schema registry. The compiler is responsible for structuring the output
(grouping operations by path, nesting into components, etc.).

## Flat collections, not a nested tree

`Specification` does NOT mirror the JSON output structure. It holds flat typed
collections. The compiler groups and nests when producing output:

```php
// Specification holds:
$spec->operations = [...]   // flat list — each knows its path + method
$spec->schemas = [...]      // flat list — each knows its name
$spec->responses = [...]    // flat list — each knows its key
$spec->parameters = [...]   // flat list
$spec->tags = [...]

// Compiler produces nested JSON:
// paths:
//   /users:
//     get: { ... }      ← grouped by path + method
//     post: { ... }
// components:
//   schemas:
//     User: { ... }     ← keyed by name
```

This means there's no "slot map" or "where does this DTO go in the tree" question.
Every DTO the assembler produces has a natural place in `Specification` — determined
by its type and identity properties (path, method, name, key).

## Contract: Assembler → Specification

All DTOs returned by the assembler have a natural place in `Specification`. The
`add()` method routes by type:

- `Operation` → operations collection (carries `$path` + `$method`)
- `Schema` → schemas collection (carries `$name`)
- `Response` → responses collection (carries `$key`)
- `Parameter` → parameters collection (carries `$name` + `$in`)
- `Webhook` → webhooks collection (carries `$name` + operations; 3.1+)
- `Info`, `ExternalDocs`, `Tag`, `Server`, etc. → top-level properties

No mapping table needed — the DTO type determines where it goes.

## Interface sketch

```php
class Specification
{
    // Flat collections
    private array $operations = [];
    private array $schemas = [];
    private array $responses = [];
    private array $parameters = [];
    private array $requestBodies = [];
    private array $headers = [];
    private array $securitySchemes = [];
    private array $links = [];
    private array $callbacks = [];
    private array $webhooks = [];
    private array $tags = [];
    private array $servers = [];

    // Singular top-level
    public ?Info $info = null;
    public ?ExternalDocs $externalDocs = null;
    public array $security = [];

    // --- Insertion ---

    public function add(object $dto): void { ... }
    // Routes by type; identity is on the DTO itself

    // --- Finders ---

    public function schema(string $name): ?Schema { ... }
    public function operations(?string $path = null): array { ... }

    /** Find all DTOs of a given type. */
    public function find(string $class): array { ... }

    /** Find with predicate. */
    public function filter(string $class, callable $predicate): array { ... }

    // --- Registry (for $ref resolution) ---

    public function resolveRef(string $ref): ?object { ... }
    public function schemaNameFor(string $className): ?string { ... }
}
```

## Flow

```
Factory (per PHP element)
    ↓ attribute stack
Assembler → root-level DTO(s) (identity on the DTO)
    ↓
Specification::add() → routes by type into flat collections
    ↓
Processors → query/mutate via Specification finders
    ↓
Compiler → walks collections, groups/nests, produces JSON/YAML
```

## Why not a DTO?

`Specification` needs behavior that DTOs shouldn't have:
- Finder/query methods (`find()`, `filter()`, `operations()`)
- Registry logic (`resolveRef()`, `schemaNameFor()`)
- Duplicate detection (two schemas with same name)
- Diagnostic collection (warnings about overwrites, missing refs)

DTOs are pure data. `Specification` is the working surface.

## Assembler scope

The assembler is deliberately narrow — one stack in, DTOs out:

```php
class Assembler
{
    /**
     * Resolve a stack of attributes on a PHP element into root-level DTOs.
     * Each returned DTO has a natural place in Specification.
     */
    public function assemble(AttributeStack $stack): array
    {
        // Pair structural + schema attrs
        // Nested attrs already in place (from constructor args)
        // Resolve stacked Schema onto structural attr
        // Return: [Operation], [Schema], [Response], etc.
    }
}
```

The caller just does `$spec->add($dto)` for each result.

## Relationship to `Analysis`

`Analysis` is the existing pipeline container. In v7, `Specification`
replaces it entirely:

- **v6.x:** Processors still receive `Analysis` via `__invoke`. Internally, new DTOs
  convert to classic annotations — processors don't see the difference.
- **v7:** `Analysis` removed. Processors receive `Specification` directly via
  `ProcessorInterface::process(Specification)`.

## What replaces Nelmio's `Util.php`

| Util method | Specification equivalent |
|-------------|--------------------------|
| `Util::getPath($api, $path)` | `$spec->operations('/users')` |
| `Util::getSchema($api, $name)` | `$spec->schema($name)` |
| `Util::getOperation($path, $method)` | `$spec->filter(Operation::class, fn($o) => ...)` |
| `Util::getProperty($schema, $name)` | `$schema->properties[$name]` |
| `Util::searchCollectionItem(...)` | `$spec->filter(Class::class, fn(...) => ...)` |
