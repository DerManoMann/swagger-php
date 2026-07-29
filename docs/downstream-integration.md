# Downstream Integration: Making the Spec Pipeline Accessible

> Design notes for making the spec pipeline easy to integrate for downstream projects
> (NelmioApiDocBundle, Lumen swagger, API Platform, custom frameworks).

## The Problem

Downstream projects integrate with swagger-php in fundamentally different ways than standalone usage:

1. **They don't scan files** — they discover endpoints via framework routing (Symfony's RouteCollection, Laravel's Router, etc.) and use reflection on known controller classes.

2. **They add domain-specific metadata** — serialization groups, model descriptions, security annotations, query string expansion — that needs to flow through the pipeline.

3. **They need to inject processing logic** — custom processors/augmenters that run at specific points relative to the built-in ones.

4. **They create spec objects programmatically** — not everything comes from PHP attributes on source code.

The spec pipeline is architecturally better suited for all of these than the classic pipeline, but a few gaps remain.

## Changes

### 1. Widen `Builder::addSource()` to Accept Reflectors

**Current:** `addSource(string|iterable $source)` — only file paths (or iterables of paths).

**Proposed:** `addSource(string|\ReflectionClass|iterable $source)`

The file scanning path (`SourceScanner` → `TokenScanner` → `Assembler::collect()`) is just a roundabout way to get `\ReflectionClass` instances. Framework integrations already have their class graph — they shouldn't need to reverse-engineer file paths.

```php
// Standalone (unchanged):
$builder->addSource('src/');

// Framework integration (new):
$builder->addSource($controllerReflection);
$builder->addSource($iterableOfReflectionClasses);

// Mixed (new):
$builder->addSource(['src/Models/', $controllerReflection]);
```

**Implementation:** Partition sources in `doBuildSpec()`:

```php
$reflectors = [];
$fileSources = [];

foreach ($this->sources as $source) {
    if ($source instanceof \ReflectionClass) {
        $reflectors[] = $source;
    } elseif (is_string($source)) {
        $fileSources[] = $source;
    } elseif (is_iterable($source)) {
        foreach ($source as $item) {
            if ($item instanceof \ReflectionClass) {
                $reflectors[] = $item;
            } else {
                $fileSources[] = $item;
            }
        }
    }
}

// File path → existing TokenScanner flow (warms cache)
$files = (new SourceScanner($this->getLogger()))->scan($fileSources);
foreach ($files as $file) {
    foreach (array_keys($tokenScanner->scanFile($file)) as $class) {
        if (class_exists($class) || interface_exists($class) || enum_exists($class) || trait_exists($class)) {
            $assembler->collect(new \ReflectionClass($class));
        }
    }
}

// Reflectors → direct collect (TokenScanner warms lazily via detailsFor())
foreach ($reflectors as $reflector) {
    $assembler->collect($reflector);
}
```

**TokenScanner cache note:** When reflectors are fed directly (no prior file scan), the `TokenScanner` cache isn't pre-warmed. The `Inheritance` augmenter and `AttributeFactory::membersOf()` call `detailsFor(\ReflectionClass)` which lazily scans the file via `$class->getFileName()`. This is actually *better* for framework integrations — they only scan files for classes they actually use, not every file in a directory. A Symfony app with 200 controllers in a 2000-file `src/` directory scans 200 files, not 2000.

### 2. `Schema\Ref` — A Typed Reference-Only Schema

The `spec-attributes.md` TODO mentions:
> A `OA\Schema\Ref` attribute (with title/description 3.1.0+), $ref required — extends `OA\Schema`

This is valuable for downstream projects because it provides a purpose-built attribute for "this property is a reference to another schema" — the most common pattern in framework-generated specs.

```php
namespace OpenApi\Spec\Schema;

use OpenApi\Spec as OA;

/**
 * A reference-only schema — $ref is required, most other Schema properties are unavailable.
 *
 * In OpenAPI 3.1+, $ref can be combined with title and description to override
 * the referenced schema's metadata without duplicating the definition.
 *
 * Usage:
 *   #[OA\Property(schema: new OA\Schema\Ref(ref: Pet::class))]
 *   #[OA\Property(schema: new OA\Schema\Ref(ref: '#/components/schemas/Pet', title: 'The pet'))]
 *   #[OA\Property(schema: new OA\Schema\Ref(ref: Pet::class, description: 'Override desc'))]
 */
#[\Attribute(\Attribute::TARGET_ALL | \Attribute::IS_REPEATABLE)]
class Ref extends OA\Schema
{
    public function __construct(
        string $ref,
        ?string $title = null,
        ?string $description = null,
        ?array $x = null,
        ?array $attachables = null,
    ) {
        parent::__construct(
            ref: $ref,
            title: $title,
            description: $description,
            x: $x,
            attachables: $attachables,
        );
    }
}
```

**Why this matters for downstream:**
- Downstream projects generate many `$ref` schemas programmatically. A dedicated `Ref` class makes intent clear and reduces boilerplate.
- The constrained constructor prevents accidentally setting type/properties/etc. alongside a `$ref` (which is invalid in 3.0).
- The `Refs` augmenter already resolves FQCN strings → `#/components/schemas/...` paths, so `new Ref(ref: Pet::class)` works naturally.

### 3. Specification Programmatic Population

Downstream projects need to add spec objects that don't originate from PHP attributes — e.g., schemas described from serialization metadata, operations for routes with no OA attribute, security schemes from framework config.

The `Specification::add()` method already handles this:

```php
$specification->add(
    new OA\Schema(schema: 'Pet', type: 'object', properties: [...]),
    new OA\Operation(path: '/pets', method: 'get', ...),
);
```

**What's needed:** Document this as a supported integration pattern, not just an internal API. Downstream augmenters should be able to add new root attributes to the specification during their pass:

```php
// In a custom augmenter:
public function __invoke(mixed $payload): mixed
{
    // Discover a model that needs a schema
    $schema = $this->describeModel(Pet::class);
    $payload->schemas[] = $schema;  // Direct bucket access
    // or
    $payload->add($schema);         // Via add() for validation

    return null;
}
```

Both paths work today. The distinction: `add()` validates that the attribute is a root; direct bucket access skips validation. Document that augmenters may use either.

### 4. Augmenter Ordering Guarantees

The current pipeline offers:
- Group ordering (`Resolve` → `Reduce` → `Augment`)
- `Pipeline::insert($pipe, before: ClassName::class)` — insert before a specific pipe
- Registration order within a group

**This is sufficient** — downstream projects can position their augmenters precisely:

```php
$builder->withAugmenters(function (Pipeline $pipeline) {
    // Run after Names (schemas have names) but before Types (don't infer types for refs)
    $pipeline->insert(new ModelResolutionAugmenter(), before: Types::class);

    // Run after Types (properties have schemas) but before Refs (refs need schemas to exist)
    $pipeline->insert(new MapQueryStringAugmenter(), before: Refs::class);
});
```

**Consideration:** Should we add `insertAfter()` for symmetry? Currently achievable with a custom matcher lambda but less ergonomic:

```php
// Current:
$pipeline->insert($pipe, fn ($items) => /* find index after target */);

// Proposed:
$pipeline->insertAfter($pipe, Names::class);
```

### 5. `AttributeTranslatorInterface` as the Extension Mechanism

This is the primary hook for downstream projects to participate in attribute collection.

**Use case:** A framework wants to synthesize spec attributes from framework-specific metadata. For example, Symfony's `#[Route]` doesn't produce any OpenAPI output, but downstream wants an `OA\Operation` for every routed method.

```php
class SymfonyRouteTranslator implements AttributeTranslatorInterface
{
    public function getAttributes(\ReflectionClass|\ReflectionMethod|... $reflector): array
    {
        // Don't add extra ReflectionAttributes — the default translator handles those
        return [];
    }

    public function translate(array $attributes): array
    {
        // If this is a routed method with no Operation, synthesize one
        if ($this->isRoutedMethod($reflector) && !$this->hasOperation($attributes)) {
            $op = new OA\Operation();
            $op->setReflector($reflector);
            $attributes[] = $op;
        }

        return $attributes;
    }
}
```

**Current gap:** `translate()` receives `array $attributes` but not the reflector directly. The reflector is available on any already-instantiated attribute in the array (via `->getReflector()`), but if the array is empty (no attributes at all on this reflector), there's no way to know *which* reflector we're translating for.

**Proposed fix:** Add the reflector as a second parameter:

```php
interface AttributeTranslatorInterface
{
    public function getAttributes(\ReflectionClass|... $reflector): array;

    // Add reflector parameter:
    public function translate(array $attributes, \Reflector $reflector): array;
}
```

This is the critical enabler for "synthesize attributes for bare framework endpoints."

### 6. Attachable as Metadata Carrier — Document the Pattern

`OA\Attachable` is already the right mechanism for downstream metadata. Document the pattern explicitly:

**Pattern: Phase-bridging via Attachables**

Downstream code attaches metadata during collection (via translators or programmatic `Specification` population) that is consumed and resolved by a later augmenter:

```php
// 1. Custom attachable DTO:
class ModelMetadata extends OA\Attachable
{
    public function __construct(
        public readonly string $type,
        public readonly ?array $groups = null,
    ) {
        parent::__construct();
    }
}

// 2. Attached during translation/assembly:
$schema = new OA\Schema(
    attachables: [new ModelMetadata(type: Pet::class, groups: ['read'])]
);

// 3. Consumed by augmenter:
foreach ($payload->schemas as $schema) {
    foreach ($schema->attachables ?? [] as $attachable) {
        if ($attachable instanceof ModelMetadata) {
            // Resolve, then remove from attachables
        }
    }
}
```

**Key properties:**
- Attachables survive the full pipeline (assembly → augmentation → compilation)
- The compiler ignores them (they don't appear in output unless `$x` is used)
- They can carry arbitrary typed data without polluting the spec DTO API
- Custom augmenters should remove consumed attachables to keep things clean

### 7. `Undefined` in Spec Attributes — Minimize and Document

Currently `Undefined::UNDEFINED` is used for `mixed` typed properties where `null` is a valid value (`example`, `default`, `const`). This is correct — "no value" vs "value is null" is a real distinction for these fields.

**For downstream:** Document clearly which properties use `Undefined` and why:

| Property | Why not nullable | Pattern |
|---|---|---|
| `Schema::$example` | `null` is a valid example value | `Undefined::isDefault($schema->example)` |
| `Schema::$default` | `null` is a valid default | same |
| `Schema::$const` | `null` is a valid const | same |
| `Parameter::$example` | same as Schema | same |
| `Header::$example` | same as Schema | same |
| `MediaType::$example` | same as Schema | same |

Everything else is `null` = absent. Downstream code should never need to check `Undefined` except for these specific `mixed` fields.

### 8. Compiler Access Without the Full Builder

Some downstream projects want to run just the augmenters + compiler on a pre-built `Specification` — they handle collection themselves.

**Already possible:**

```php
use OpenApi\Augmenter;
use OpenApi\Compiler;
use OpenApi\Specification;
use OpenApi\Utils\Pipeline;

$specification = new Specification();
// ... populate programmatically or via Assembler::collect() ...

// Build and run augmenter pipeline
$augmenters = new Pipeline(
    pipes: [
        new Augmenter\Names(),
        new Augmenter\Types(),
        new Augmenter\Refs(),
        new CustomDownstreamAugmenter(),
        // ...
    ],
    groups: [Augmenter\Group::Resolve, Augmenter\Group::Reduce, Augmenter\Group::Augment],
    defaultGroup: Augmenter\Group::Augment,
);
$augmenters->process($specification);

// Compile
$compiler = new Compiler\OpenApi31Compiler();
$output = $compiler->compile($specification);
```

**Alternatively via Builder** (simpler, handles defaults):

```php
$builder = new Builder();
$builder->setMode(Mode::SPEC);
$builder->addSource($reflectors);
$builder->withAugmenters(fn ($p) => $p->add(new CustomAugmenter()));
$result = $builder->build();
```

Both are valid. The Builder path is recommended for most integrations; direct pipeline access for projects that need full control.

## Summary of Proposed Changes

| Change | Impact | Effort |
|---|---|---|
| Widen `addSource()` to accept `\ReflectionClass` | Enables framework integrations without file scanning | Small |
| Add reflector param to `AttributeTranslatorInterface::translate()` | Enables synthesizing attributes for bare endpoints | Small (breaking for existing translators) |
| Add `Schema\Ref` class | Convenience for the most common pattern | Small |
| Add `Pipeline::insertAfter()` | Ergonomics for augmenter ordering | Tiny |
| Document programmatic `Specification` population | No code change — just docs | None |
| Document Attachable metadata pattern | No code change — just docs | None |
| Document `Undefined` scope in spec attributes | No code change — just docs | None |

## What NOT to Change

- **Don't widen property types for downstream convenience** — the strong typing is the point. Downstream should use Attachables for metadata, not pollute `$ref: string|object`.
- **Don't add framework-specific code** — swagger-php stays framework-agnostic. The extension points (translators, augmenters, attachables) are the contract.
- **Don't add event/listener patterns** — the pipeline is deterministic and debuggable. Events add non-obvious ordering and make testing harder.
- **Don't expose Assembler internals** — `collect()` is public, that's the contract. The two-pass resolution is an implementation detail.

## Downstream Migration Path

For a project like NelmioApiDocBundle:

### Phase 1: Source feeding (non-breaking)

```php
$builder = new Builder();
$builder->setMode(Mode::SPEC);

// Feed controller classes from Symfony's router
foreach ($routeCollection as $route) {
    $builder->addSource($controllerReflector->getReflectionClass($route));
}

// Feed model classes
foreach ($modelClasses as $class) {
    $builder->addSource(new \ReflectionClass($class));
}
```

### Phase 2: Translator for bare routes

```php
$builder->withAttributeFactory(function (AttributeFactory $factory) {
    $factory->getTranslators()->add(new SymfonyRouteTranslator($this->router));
});
```

### Phase 3: Augmenters for domain logic

```php
$builder->withAugmenters(function (Pipeline $pipeline) {
    $pipeline->insert(new RouteBindingAugmenter($router), before: Types::class);
    $pipeline->insert(new ModelResolutionAugmenter($registry), before: Refs::class);
    $pipeline->insert(new MapQueryStringAugmenter(), before: Refs::class);
    $pipeline->insert(new OperationIdAugmenter($router), before: OperationIds::class);
});
```

### Result

The downstream project becomes a thin assembly of:
- Source feeding (which classes to scan)
- One translator (synthesize operations for bare routes)
- N augmenters (domain-specific enrichment)
- Zero custom scanning, zero annotation tree manipulation, zero `UNDEFINED` juggling