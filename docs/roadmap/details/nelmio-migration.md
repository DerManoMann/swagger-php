# Nelmio Migration — Detail

## How Nelmio Works Today

Nelmio builds an OpenAPI spec **programmatically** — it doesn't just read
annotations from source files. It translates Symfony framework metadata
(routes, validation, type info) into swagger-php annotation objects.

### Pipeline

```
Symfony Controller + #[Route] + #[Assert\*] + #[MapQueryString] + #[OA\*]
    ↓
Describers (extract framework metadata → create OA\* annotations)
    ↓
ModelRegistry (class → schema, recursive)
    ↓
Processors (merge, augment, validate)
    ↓
jsonSerialize() → JSON/YAML
```

### Key Components

| Component | Role |
|-----------|------|
| `OpenApiPhpDescriber` | Reads `#[OA\*]` attributes from controllers, merges into spec |
| `RouteDescriber` chain | Extracts path params, `#[MapQueryString]`, `#[MapRequestPayload]` |
| `SymfonyConstraintAnnotationReader` | Translates `#[Assert\*]` → schema constraints |
| `ModelRegistry` | Deduplicates and recursively describes PHP classes as schemas |
| `ObjectModelDescriber` | PropertyInfo + reflection → schema properties |
| `OpenApiPhp/Util.php` | 638-line wrapper around swagger-php's `$_nested` + `merge()` + `UNDEFINED` |
| 3 custom processors | `MapQueryString`, `MapRequestPayload`, `NullableProperty` |

---

## What Nelmio Uses from swagger-php Internals

### `Util.php` — the compatibility layer

Every interaction with swagger-php goes through `Util`. Key patterns:

```php
// Get-or-create via $_nested inspection
$operation = Util::getOperation($path, $method);
$param = Util::getOperationParameter($operation, $name, 'query');
$schema = Util::getSchema($api, $schemaName);
$property = Util::getProperty($schema, $propertyName);

// Deep merge using $_nested + $_types metadata
Util::merge($operation, $annotationArray, $overwrite);

// UNDEFINED checks (50+ occurrences)
if (Generator::UNDEFINED === $operation->operationId) { ... }
```

### Context as cross-phase storage

Describers store metadata for processors to retrieve later:

```php
// In SymfonyMapQueryStringDescriber:
$operation->_context->{self::CONTEXT_KEY} = $modelRef;

// In MapQueryStringProcessor:
$modelRef = $operation->_context->{SymfonyMapQueryStringDescriber::CONTEXT_KEY};
```

### Direct annotation construction

```php
Generator::$context = Util::createContext(['class' => $class, ...]);
$operation = new OA\Get([...]);
$operation->merge([$param1, $param2]);
$operation->mergeProperties($otherAnnotation);
```

---

## What the New Architecture Enables for Nelmio

### Eliminate `Util.php` entirely

`Util.php` exists because swagger-php's annotation model is hard to navigate
programmatically. The assembler + document tree make this unnecessary:

| Current (via Util) | v7 equivalent (`Specification`) |
|-------------------|---------------|
| `Util::getOperation($path, $method)` | `$spec->operations('/users')` + filter by method |
| `Util::getSchema($api, $name)` | `$spec->schema($name)` |
| `Util::getProperty($schema, $name)` | `$schema->properties[$name]` |
| `Util::getOperationParameter($op, $n, $in)` | `$op->parameters` (typed array) |
| `Util::merge($annotation, $from)` | Direct property assignment (no `UNDEFINED`) |
| `Util::searchCollectionItem(...)` | `$spec->filter(Class::class, fn(...) => ...)` |

### Replace `UNDEFINED` with `null`

50+ sentinel checks become unnecessary. DTOs use `null` for "not set" — standard
PHP semantics. Nelmio's conditionals simplify from:

```php
if (Generator::UNDEFINED === $operation->operationId) {
    $operation->operationId = $route->getName();
}
```

to:

```php
$operation->operationId ??= $route->getName();
```

### Replace context tunneling with `Attachable`s

Currently Nelmio stores arbitrary keys on `Context` (dynamic properties) to pass
data between describers and processors. In the new pipeline, use `Attachable` instances:

```php
// Describer attaches metadata:
$operation->attachables[] = new MapQueryStringMeta(modelRef: $ref);

// Processor retrieves:
$meta = $operation->getAttachable(MapQueryStringMeta::class);
```

Attachables already exist as an extension bag on any annotation, and the compiler
silently ignores unhandled ones. Projects can build their own registry patterns
on top if needed — swagger-php just provides the container.

### `ModelRegistry` becomes simpler

Currently uses hash-based deduplication and JSON comparison. With `Specification`'s
schema registry, model registration is:

```php
$spec->add($schema);  // Schema carries its name
// Duplicate detection: name collision handled by Specification
```

### Describers can use `Specification` directly

Instead of constructing annotation objects and calling `merge()`, describers
add DTOs to the spec:

```php
// Current:
$operation = new OA\Get(['summary' => '...']);
$operation->merge([new OA\Parameter([...])]);
Util::getOperation($path, 'get')->mergeProperties($operation);

// v7:
$op = new Operation(path: '/users', method: 'get', summary: 'List users');
$op->parameters[] = new Parameter(name: 'limit', in: 'query');
$spec->add($op);
```

---

## Symfony Attributes → OpenAPI: a natural fit for the new pipeline

Nelmio's core value is translating **Symfony's existing attributes** into OpenAPI.
The framework already provides:

| Symfony attribute | OpenAPI concept |
|------------------|----------------|
| `#[Route('/users', methods: ['GET'])]` | `PathItem` + `Operation` |
| `#[Assert\NotBlank]` | `required: true` |
| `#[Assert\Length(min: 3, max: 50)]` | `minLength` / `maxLength` |
| `#[Assert\Choice(['a', 'b'])]` | `enum: ['a', 'b']` |
| `#[Assert\Range(min: 1, max: 100)]` | `minimum` / `maximum` |
| `#[MapQueryString]` | Parameters from DTO properties |
| `#[MapRequestPayload]` | `RequestBody` with schema from DTO |

With the layered architecture, this translation becomes cleaner because:
1. The target (document tree) has direct typed access — no `$_nested` navigation
2. Schema constraints map naturally to `Schema` DTO properties
3. No `UNDEFINED` ceremony — just set the property

### Potential: enrichment extension point for third-party attributes

swagger-php shouldn't adopt Symfony-specific attributes — but it can provide a
generic mechanism that makes translating them trivial. An `AttributeEnricher`
interface lets downstream projects (Nelmio, custom bundles) register translators
for any third-party attribute:

```php
interface AttributeEnricher
{
    /**
     * Which non-OA attribute class(es) this enricher handles.
     */
    public function handles(): array;

    /**
     * Translate a third-party attribute into OA DTOs on the stack.
     */
    public function enrich(object $attribute, AttributeStack $stack): void;
}
```

The enricher runs during discovery, before assembly. The factory reads all
attributes on a PHP element, routes OA ones into the stack directly, and passes
non-OA ones through registered enrichers. Enrichers produce/modify OA DTOs in
the stack — same output the user would write by hand:

```php
class SymfonyLengthEnricher implements AttributeEnricher
{
    public function handles(): array { return [Assert\Length::class]; }

    public function enrich(object $attribute, AttributeStack $stack): void
    {
        // Target the Schema on the structural attr (Property/Parameter)
        // rather than creating a loose Schema on the stack
        $structural = $stack->findStructural();  // Property, Parameter, etc.
        $schema = $structural?->schema ?? $stack->getOrCreate(Schema::class);
        if ($attribute->min !== null) $schema->minLength = $attribute->min;
        if ($attribute->max !== null) $schema->maxLength = $attribute->max;
    }
}

class SymfonyNotBlankEnricher implements AttributeEnricher
{
    public function handles(): array { return [Assert\NotBlank::class]; }

    public function enrich(object $attribute, AttributeStack $stack): void
    {
        $stack->markRequired();
    }
}
```

Because the enricher has the full stack, it can navigate to the right target —
avoiding ambiguity that would arise from adding a bare `Schema` to a stack that
already has multiple structural attrs.

```
Factory reads PHP element
    ├─ OA\* attrs → stack directly
    └─ non-OA attrs → matching AttributeEnricher → modifies/adds to stack
    ↓
Assembler receives enriched stack (all DTOs, regardless of origin)
```

This keeps swagger-php framework-agnostic while making the "translate framework
attrs → schema constraints" pattern a first-class extension point. Enrichers
just produce the same DTOs users would write — no special output format.

This replaces Nelmio's 250-line `SymfonyConstraintAnnotationReader` with small,
focused, testable enricher classes — each handling one constraint type.

---

## Migration Path by Phase

### v6.x — Zero changes required

- Nelmio's 3 processors keep `__invoke(Analysis)` — works unchanged (processor
  contract doesn't change until v7; v6.x only adds infrastructure alongside)
- `Util.php` continues working against classic annotations
- New DTOs convert to classic annotations — Nelmio doesn't see the difference
- Processors, Describers, `Util.php` all work as before
- `#[AllowedParents]` available for any custom `Attachable`s
- Nelmio can stay on v6 indefinitely

### v7.0 — Migration

- Processors rewrite to `ProcessorInterface::process(Specification)`
- `Util.php` eliminated — `Specification` finders replace it
- Describers produce DTOs and add to `Specification` directly
- `ModelRegistry` simplified — uses `Specification`'s schema registry
- Context tunneling replaced by `Attachable`s

---

## Impact Assessment

| Component | v7 migration effort | Benefit |
|-----------|-------------------|---------|
| `Util.php` (638 lines) | **High** — complete rewrite | Eliminated entirely |
| `MapQueryStringProcessor` | Medium | Half the code (no UNDEFINED/copy dance) |
| `MapRequestPayloadProcessor` | Medium | Same |
| `NullablePropertyProcessor` | Low | Trivial with null-based semantics |
| `OpenApiPhpDescriber` | Medium | Cleaner but same workflow |
| `ModelRegistry` | Medium | Simpler dedup, direct tree access |
| `SymfonyConstraintAnnotationReader` | Low | Replaced by small `AttributeEnricher` impls |
| `RouteDescriberInterface` chain | Low | Already clean |

### Net result for Nelmio

- **~800 lines of compatibility code eliminated** (Util.php + UNDEFINED checks)
- **Processors become trivial** (direct property access on typed tree)
- **Core value preserved** — Symfony→OpenAPI translation logic stays, wrappers go
- **Potential feature absorption** — constraint mapping could become swagger-php native

