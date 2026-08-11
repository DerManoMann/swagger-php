# Swagger-PHP Spec Pipeline Integration Plan

## Current State

NelmioApiDocBundle currently integrates with swagger-php via the **classic pipeline**:

- Extends `OpenApi\Generator` (`OpenApiGenerator`)
- Uses `OpenApi\Analysis` as the working data model
- Injects custom processors via `nelmio_api_doc.swagger.processor` tag
- Relies heavily on `Generator::UNDEFINED` sentinel values
- Uses `OpenApi\Annotations\*` / `OpenApi\Attributes\*` as the object model
- Uses mutable `Context` objects for metadata passing between processors

**Key integration points:**
- `src/ApiDocGenerator.php` — orchestrates: describers → ModelRegister → ModelRegistry → processor pipeline
- `src/OpenApiPhp/Util.php` — compatibility bridge (get-or-create pattern on annotation objects)
- `src/OpenApiPhp/ModelRegister.php` — resolves `#[Model]` attachables into schema refs
- `src/OpenApiGenerator.php` — extends `Generator` to inject processors
- `src/Processor/MapQueryStringProcessor.php` — classic processor example
- `src/Processor/NullablePropertyProcessor.php` — classic processor example
- `src/Describer/OpenApiPhpDescriber.php` — reads OA\* attributes from controllers
- `src/ModelDescriber/Annotations/OpenApiAnnotationsReader.php` — reads OA\Schema/Property from models

## swagger-php v6 Spec Pipeline Architecture

The new spec pipeline (`Builder::Mode::SPEC`) introduces:

```
Source files
    ↓
Assembler (collect OA\Spec\* attributes via AttributeFactory)
    ↓
Specification (flat container: schemas[], operations[], parameters[], etc.)
    ↓
Augmenter Pipeline (grouped: Resolve → Reduce → Augment)
    ↓
Compiler (version-specific: 3.0 / 3.1 / 3.2)
    ↓
array output (JSON/YAML)
```

**Key concepts:**
- `OpenApi\Spec\*` namespace — new typed DTOs (vs `OpenApi\Annotations\*`)
- `Specification` — flat bucket container (no nested tree to navigate)
- `PipeInterface` — declares `group()` and `__invoke(Specification)`
- `AttributeInterface` — declares `isRoot()`, `merge()`, `contains()`
- `AttributeTranslatorInterface` — pluggable attribute discovery/transformation
- `SpecificationWalker` — traversal helpers for augmenters
- Limited `Generator::UNDEFINED` — nulls are used where possible
- No `Context` object — `Reflector` is stored directly on attributes
- No `Analysis` — replaced by `Specification`

## Typing Differences (Breaking for NelmioApiDocBundle)

The `OA\Spec\*` attributes are **strongly typed** with native PHP types:

| Classic (`Annotations\*`) | Spec (`Spec\*`) |
|---|---|
| `$property->type` is `string\|UNDEFINED` | `$schema->type` is `string\|array\|null` |
| `$property->nullable` is `bool\|UNDEFINED` | `$schema->nullable` is `?bool` |
| `$property->properties` is `Property[]\|UNDEFINED` | `$schema->properties` is `?array` |
| `$schema->ref` is `string\|class-string\|UNDEFINED` | `$schema->ref` is `?string` |
| Properties carry their own type inline | `Property` has a `$schema` slot (separate Schema object) |
| `OA\Items` extends `OA\Schema` | `Schema\Items` extends `Schema` (shorthand) |
| `OA\JsonContent`/`OA\XmlContent` pseudo-annotations | `MediaType\Json`/`MediaType\Xml` proper attributes |

**Critical structural change:** In spec attributes, `Property` no longer carries type info directly — it has a `$schema: ?Schema` slot. So code like `$property->type === 'array'` becomes `$property->schema?->type === 'array'`.

## Upgrade Strategy

### Phase 1: Hybrid Mode (Low Risk, Immediate Value)

Use swagger-php's `Mode::HYBRID` — it runs the classic scanner, then `HybridBridge` converts the `Analysis` into a `Specification`, which flows through augmenters + compiler.

**What this gives NelmioApiDocBundle:**
- Keep existing describer architecture unchanged
- Keep `OA\Annotations\*` as working model during generation
- Gain version-aware compilation (3.0/3.1/3.2)
- Gain the augmenter pipeline benefits (better type inference, inheritance)

**Required changes:**
1. `ApiDocGenerator` produces an `Analysis` as before, but instead of calling `$analysis->validate()`, hands it to the `HybridBridge` + augmenters + compiler
2. Output becomes `array` (from compiler) rather than `OpenApi` annotation object
3. Caching stores the compiled array instead of the annotation tree

### Phase 2: Port Processors to Augmenters

Convert existing `nelmio_api_doc.swagger.processor` callables to `PipeInterface` augmenters.

**Pattern mapping:**

| Classic Processor | Spec Augmenter |
|---|---|
| `function(Analysis $analysis)` | `class implements PipeInterface { __invoke(Specification $spec) }` |
| `$analysis->getAnnotationsOfType(...)` | `$spec->operations` / `$spec->schemas` / walker |
| `Generator::UNDEFINED` checks | `null` checks |
| `$annotation->_context->customKey` | Attachable on the attribute |

### Phase 3: Native Spec Mode

Full switch to `Mode::SPEC` — the bundle would:
- Use `Assembler` + `AttributeFactory` directly
- Implement `AttributeTranslatorInterface` for the `#[Model]` concept
- Write augmenters instead of processors
- Possibly implement a custom `Compiler` or just use the built-in ones

## Changes Needed in swagger-php

### 1. `AttributeTranslatorInterface` for downstream custom attributes

NelmioApiDocBundle's `#[Model]` attribute is currently an `Attachable` that is read and consumed during the `ModelRegister` phase. In spec mode, this needs to survive attribute collection and be available to augmenters.

**Current approach works:** `Attachable` in spec mode is already rooted into `Specification::$attachables`. The `AttributeTranslatorInterface` can also synthesize/transform attributes during collection.

**Potential enhancement:** Allow `AttributeTranslatorInterface::translate()` to return non-`AttributeInterface` objects that are stored as metadata without erroring. Or better: `Spec\Attachable` already does this.

### 2. Parameter typing for downstream flexibility

The spec attributes doc mentions: "Adjust attribute parameter types to aid downstream projects?"

Specific cases where NelmioApiDocBundle needs flexibility:

- **`Schema::$ref`** — currently `?string`. NelmioApiDocBundle wants to temporarily store a `Model` object here (later resolved to a `#/components/schemas/...` string). Options:
  - (a) Widen to `string|object|null` (ugly, breaks typing purpose)
  - (b) Use an attachable + separate resolution pass (cleaner)
  - (c) Introduce a `Ref` value object that can carry metadata

- **`Schema::$type`** — currently `string|array|null`. This is fine for NelmioApiDocBundle.

**Recommendation:** Option (b) — keep `$ref` as `?string`, use `Attachable` for model metadata, resolve in an augmenter. This aligns with the spec pipeline's design intent.

### 3. Builder hooks for external assembly

NelmioApiDocBundle doesn't scan source files — it builds the spec programmatically from Symfony's router + reflection. The `Builder` currently requires source paths.

**Needed:** A way to feed a pre-built `Specification` into the augmenter/compiler pipeline without going through the file scanner. Options:
- `Builder::setSpecification(Specification $spec)` — skip assembly, go straight to augmenters
- Direct access to the augmenter pipeline + compiler (already possible: `$builder->getAugmenters()->process($spec)`)

**This already works** with the existing API:
```php
$spec = new Specification();
// ... populate programmatically ...
$builder->getAugmenters()->process($spec);
$compiler = new OpenApi31Compiler();
$output = $compiler->compile($spec);
```

### 4. Augmenter group ordering for external pipes

NelmioApiDocBundle's processors need to run at specific points. The `Group` enum (`Resolve`, `Reduce`, `Augment`) may not be granular enough.

**Options:**
- Allow custom group values (the Pipeline already supports string groups)
- Insert at specific positions within a group (Pipeline has `insert()`)
- Add an `after()` registration method

### 5. SpecificationWalker extensions

NelmioApiDocBundle's `MapQueryStringProcessor` needs to walk operations and find associated schemas. The `SpecificationWalker` provides `eachSchema()`, `eachRef()`, `visit()` — verify these cover all traversal needs.

## Example: MapQueryStringProcessor as a Spec Augmenter

### Current (classic processor):

```php
// src/Processor/MapQueryStringProcessor.php
final class MapQueryStringProcessor
{
    public function __invoke(Analysis $analysis): void
    {
        $operations = $analysis->getAnnotationsOfType(OA\Operation::class);
        foreach ($operations as $operation) {
            // Read custom context key set during route description
            $contexts = $operation->_context->{SymfonyMapQueryStringDescriber::CONTEXT_KEY};
            foreach ($contexts as $ctx) {
                $schemaModel = Util::getSchema($analysis->openapi, $modelName);
                foreach ($schemaModel->properties as $property) {
                    $param = Util::getOperationParameter($operation, $name, 'query');
                    // Copy property schema to parameter
                }
            }
        }
    }
}
```

### New (spec augmenter):

```php
namespace Nelmio\ApiDocBundle\Augmenter;

use OpenApi\Augmenter\Group;
use OpenApi\Spec as OA;
use OpenApi\Specification;
use OpenApi\Utils\PipeInterface;

/**
 * Expands MapQueryString-annotated arguments into individual query parameters.
 *
 * Reads MapQueryStringModel attachables placed during route description,
 * finds the referenced schema in the specification, and creates one
 * OA\Parameter per property on the operation.
 *
 * @implements PipeInterface<Specification>
 */
final class MapQueryStringAugmenter implements PipeInterface
{
    public function group(): string|\BackedEnum
    {
        // Run after Types (which infers property schemas) but before Cleanup
        return Group::Reduce;
    }

    public function __invoke(mixed $payload): mixed
    {
        /** @var Specification $payload */
        foreach ($payload->operations as $operation) {
            $mapQueryStringModels = $this->getMapQueryStringModels($operation);
            if ($mapQueryStringModels === []) {
                continue;
            }

            foreach ($mapQueryStringModels as $model) {
                $this->expandToParameters($payload, $operation, $model);
            }
        }

        return null;
    }

    /**
     * @return list<MapQueryStringModel>
     */
    private function getMapQueryStringModels(OA\Operation $operation): array
    {
        $models = [];
        foreach ($operation->attachables ?? [] as $attachable) {
            if ($attachable instanceof MapQueryStringModel) {
                $models[] = $attachable;
            }
        }
        return $models;
    }

    private function expandToParameters(
        Specification $specification,
        OA\Operation $operation,
        MapQueryStringModel $model,
    ): void {
        // Find the referenced schema in the specification
        $schema = $this->findSchema($specification, $model->schemaName);
        if ($schema === null || $schema->properties === null) {
            return;
        }

        $operation->parameters ??= [];

        foreach ($schema->properties as $property) {
            $name = $property->property;
            if ($property->schema?->type === 'array') {
                $name .= '[]';
            }

            $parameter = new OA\Parameter(
                name: $name,
                in: OA\ParameterIn::Query->value,
                description: $property->schema?->description,
                required: $model->isOptional
                    ? false
                    : in_array($property->property, $schema->required ?? [], true),
                deprecated: $property->schema?->deprecated,
                schema: $property->schema,
            );

            $operation->parameters[] = $parameter;
        }
    }

    private function findSchema(Specification $specification, string $name): ?OA\Schema
    {
        foreach ($specification->schemas as $schema) {
            if ($schema->schema === $name) {
                return $schema;
            }
        }
        return null;
    }
}
```

### Custom Attachable for passing data:

```php
namespace Nelmio\ApiDocBundle\Spec;

use OpenApi\Spec\Attachable;

/**
 * Carries MapQueryString metadata from route description to the augmenter.
 *
 * Attached to operations during the describe phase; consumed and removed
 * by MapQueryStringAugmenter.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class MapQueryStringModel extends Attachable
{
    public function __construct(
        public readonly string $schemaName,
        public readonly bool $isOptional = false,
    ) {
        parent::__construct();
    }
}
```

## How This Showcases Extending the Spec Pipeline

The `MapQueryStringAugmenter` example demonstrates:

1. **`PipeInterface` contract** — declare group, receive `Specification`, mutate and return null
2. **Attachables as metadata** — pass data between phases without abusing `Context` or `$_nested`
3. **Flat specification access** — direct iteration over `$spec->operations`, `$spec->schemas`
4. **Strongly typed construction** — create `OA\Parameter` with named params, null = absent
5. **No UNDEFINED dance** — just use `null` checks and `??=`
6. **Group ordering** — choose when in the pipeline this runs

### Registration in swagger-php's Builder:

```php
$builder = new \OpenApi\Builder();
$builder->getAugmenters()->add(new MapQueryStringAugmenter());
// Or with the hook:
$builder->withAugmenters(function (Pipeline $pipeline) {
    $pipeline->add(new MapQueryStringAugmenter());
});
```

### Registration in NelmioApiDocBundle (DI):

```yaml
services:
    Nelmio\ApiDocBundle\Augmenter\MapQueryStringAugmenter:
        tags:
            - { name: nelmio_api_doc.swagger.augmenter }
```

## Summary of Required swagger-php Changes

| Change | Priority | Rationale |
|--------|----------|-----------|
| Verify `Attachable` works as metadata carrier in spec mode | High | NelmioApiDocBundle's `#[Model]` pattern |
| Allow external `Specification` injection into Builder | Medium | NelmioApiDocBundle doesn't scan files |
| Consider `Pipeline::insertBefore()/insertAfter()` by class | Medium | Processor ordering needs |
| Document `AttributeTranslatorInterface` for custom attributes | Medium | Downstream extension point |
| Ensure `SpecificationWalker` covers operation→schema traversal | Low | Convenience for augmenters |
| Consider a `pre-augment` or `post-augment` group hook | Low | Fine-grained ordering |

## Migration Timeline Alignment

| swagger-php | NelmioApiDocBundle | Mode |
|-------------|-------------------|------|
| v6 (current) | Next major | Hybrid (classic scanner → spec compiler) |
| v7 (planned) | Following major | Spec (native, drop classic dependencies) |
| v8 (planned) | — | Spec only (no migration needed) |
