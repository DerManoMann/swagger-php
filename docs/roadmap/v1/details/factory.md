# Factory + AttributeStack — Detail

## Role

The factory is the entry point of the new pipeline. It reads PHP elements
(classes, methods, properties), collects their attributes, assigns metadata,
runs enrichers, and produces `AttributeStack` objects for the assembler.

## Flow

```
PHP element (via ReflectionClass/Method/Property)
    ↓
Factory reads all attributes on the element
    ├─ OA\Spec\* attrs → AttributeStack directly
    └─ non-OA attrs → registered AttributeEnrichers → may modify stack
    ↓
Factory assigns SourceLocation + Reflector to each DTO in the stack
    ↓
AttributeStack returned (ready for assembler)
```

## `AttributeStack`

A simple container representing all DTOs discovered on a single PHP element:

```php
class AttributeStack
{
    public readonly SourceLocation $location;
    public readonly ?\Reflector $reflector;

    /** @var list<object> OA DTOs found (or created by enrichers) */
    private array $dtos = [];

    public function add(object $dto): void { ... }

    /** @return list<object> */
    public function all(): array { ... }

    /** Find first DTO of given type. */
    public function find(string $class): ?object { ... }

    /** Find structural attr (Operation, Property, Parameter, Schema, etc.) */
    public function findStructural(): ?object { ... }

    /** Get or create a DTO of given type (used by enrichers). */
    public function getOrCreate(string $class): object { ... }

    /** Mark this element as required (used by validation enrichers). */
    public function markRequired(): void { ... }
}
```

An `AttributeStack` maps 1:1 to a PHP element. It carries the element's
`SourceLocation` and `Reflector`, plus the collected DTOs.

## Factory sketch

```php
class AttributeFactory
{
    /** @var list<AttributeEnricher> */
    private array $enrichers = [];

    public function addEnricher(AttributeEnricher $enricher): void { ... }

    /**
     * Discover attributes on a PHP element and produce an AttributeStack.
     */
    public function createStack(\Reflector $reflector): AttributeStack
    {
        $location = SourceLocation::fromReflector($reflector);
        $stack = new AttributeStack($location, $reflector);

        // Read all PHP attributes on this element
        $attributes = $reflector->getAttributes();

        foreach ($attributes as $attr) {
            $instance = $attr->newInstance();

            if ($this->isOaAttribute($instance)) {
                // OA DTO — add directly, assign metadata
                $instance->_sourceLocation = $location;
                $instance->_reflector = $reflector;
                $stack->add($instance);
            } else {
                // Non-OA — route to enrichers
                $this->enrich($instance, $stack);
            }
        }

        return $stack;
    }

    private function enrich(object $attribute, AttributeStack $stack): void
    {
        foreach ($this->enrichers as $enricher) {
            if (in_array(get_class($attribute), $enricher->handles(), true)) {
                $enricher->enrich($attribute, $stack);
            }
        }
    }

    private function isOaAttribute(object $instance): bool
    {
        return str_starts_with(get_class($instance), 'OpenApi\\Spec\\');
    }
}
```

## Namespace routing

The factory routes by namespace:
- `OpenApi\Spec\*` → new pipeline (AttributeStack → assembler → Specification)
- `OpenApi\Attributes\*` / `OpenApi\Annotations\*` → classic pipeline (existing)

A single class can only use one namespace — mixing within a class is a diagnostic
error. Different classes in the same file or project can use different namespaces.

## Relationship to current `AttributeAnnotationFactory`

The current `AttributeAnnotationFactory` does similar work for the classic path:
sets `Generator::$context`, calls `newInstance()`, detects parent-child via
`$_nested`, runs merge. The new `AttributeFactory` is simpler because:
- No `Generator::$context` (metadata assigned after construction)
- No merge (assembler handles it)
- No `$_nested` detection (DTOs know their structure via constructor args)
- Enrichers replace the ad-hoc `$isParent` closure logic

## Integration with Generator

This sketch shows the **end-state (v7)** where `Specification` is primary and
processors use `ProcessorInterface::process(Specification)`. In Phase 1 (v6.x),
the factory feeds converters into the classic pipeline instead — see
[v6-architecture.md](../v6-architecture.md) for the phased evolution.

```php
// End-state (v7):
class Generator
{
    public function generate(array $sources): Specification
    {
        $factory = new AttributeFactory();
        foreach ($this->enrichers as $e) $factory->addEnricher($e);

        $spec = new Specification();
        $assembler = new Assembler();

        foreach ($this->scan($sources) as $reflector) {
            $stack = $factory->createStack($reflector);
            $dtos = $assembler->assemble($stack);
            foreach ($dtos as $dto) {
                $spec->add($dto);
            }
        }

        foreach ($this->processors as $processor) {
            $processor->process($spec);
        }

        return $spec;
    }
}
```
