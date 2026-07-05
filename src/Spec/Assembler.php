<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

class Assembler
{
    public function __construct(
        protected Specification $specification = new Specification(),
    ) {
    }

    public function getSpecification(): Specification
    {
        return $this->specification;
    }

    /**
     * Collect all OpenAPI attributes from the given reflectors into the specification.
     */
    public function collect(\Reflector ...$reflectors): static
    {
        foreach ($reflectors as $reflector) {
            $this->collectFromReflector($reflector);
        }

        return $this;
    }

    /**
     * Instantiate OpenAPI attributes from a single reflector and return them.
     *
     * @return list<OpenApiAttributeInterface>
     */
    public function instantiate(\Reflector $reflector): array
    {
        $instances = [];

        foreach ($this->readAttributes($reflector) as $instance) {
            $instances[] = $instance;
        }

        if ($reflector instanceof \ReflectionMethod) {
            foreach ($reflector->getParameters() as $parameter) {
                foreach ($this->readAttributes($parameter) as $instance) {
                    $instances[] = $instance;
                }
            }
        }

        return $instances;
    }

    protected function collectFromReflector(\Reflector $reflector): void
    {
        foreach ($this->readAttributes($reflector) as $instance) {
            $this->specification->add($instance);
        }

        if ($reflector instanceof \ReflectionClass) {
            $this->collectFromClass($reflector);
        } elseif ($reflector instanceof \ReflectionMethod) {
            $this->collectFromParameters($reflector);
        }
    }

    protected function collectFromClass(\ReflectionClass $class): void
    {
        foreach ($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $this->collectFromReflector($method);
        }

        foreach ($class->getProperties() as $property) {
            if ($property->isPromoted()) {
                continue;
            }
            $this->collectFromReflector($property);
        }
    }

    protected function collectFromParameters(\ReflectionMethod $method): void
    {
        foreach ($method->getParameters() as $parameter) {
            foreach ($this->readAttributes($parameter) as $instance) {
                $this->specification->add($instance);
            }
        }
    }

    /**
     * @return \Generator<OpenApiAttributeInterface>
     */
    protected function readAttributes(\Reflector $reflector): \Generator
    {
        $attributes = $reflector->getAttributes(
            OpenApiAttributeInterface::class,
            \ReflectionAttribute::IS_INSTANCEOF,
        );

        foreach ($attributes as $attribute) {
            if (!class_exists($attribute->getName())) {
                continue;
            }

            $instance = $attribute->newInstance();

            if ($instance instanceof AbstractAttribute) {
                $instance->sourceLocation = SourceLocation::fromReflector($reflector);
                $instance->reflector = $reflector;
            }

            yield $instance;
        }
    }
}
