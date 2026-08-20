<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Builder;

use OpenApi\AttributeInterface;
use OpenApi\ComponentIndex;
use OpenApi\Spec as OA;
use OpenApi\Specification;

/**
 * Discovers FQCNs referenced by the specification that have no corresponding schema.
 *
 * Two sources are inspected:
 * 1. Ref values that are raw FQCNs (not yet rewritten to `#/components/...` paths)
 * 2. Property/parameter type hints on schema class reflectors
 */
class TypeDiscovery
{
    /**
     * @return list<string> FQCNs that are referenced but have no schema in the specification
     */
    public function findUnresolved(Specification $specification): array
    {
        $index = $specification->buildComponentIndex();
        $unresolved = [];

        $this->collectFromRefs($specification, $index, $unresolved);
        $this->collectFromReflectors($specification, $index, $unresolved);

        return array_values(array_unique($unresolved));
    }

    protected function collectFromRefs(Specification $specification, ComponentIndex $index, array &$unresolved): void
    {
        $specification->getWalker()->eachRef(function (AttributeInterface $attribute) use ($index, &$unresolved): void {
            if (!property_exists($attribute, 'ref') || $attribute->ref === null) {
                return;
            }

            $ref = $attribute->ref instanceof OA\Schema\Ref
                ? $attribute->ref->ref
                : $attribute->ref;

            if (str_starts_with($ref, '#/')) {
                return;
            }

            if (!class_exists($ref)) {
                return;
            }

            if ($index->findSchema($ref) === null) {
                $unresolved[] = $ref;
            }
        });
    }

    protected function collectFromReflectors(Specification $specification, ComponentIndex $index, array &$unresolved): void
    {
        foreach ($specification->schemas as $schema) {
            $reflector = $schema->getClassReflector();
            if ($reflector === null) {
                continue;
            }

            foreach ($this->getTypedReflectors($reflector) as $type) {
                if ($type->isBuiltin()) {
                    continue;
                }

                $fqcn = $type->getName();
                if (!class_exists($fqcn) && !interface_exists($fqcn) && !enum_exists($fqcn)) {
                    continue;
                }

                if ($index->findSchema($fqcn) === null) {
                    $unresolved[] = $fqcn;
                }
            }
        }
    }

    /**
     * @return list<\ReflectionNamedType>
     */
    protected function getTypedReflectors(\ReflectionClass $reflector): array
    {
        $types = [];

        foreach ($reflector->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            if ($prop->getDeclaringClass()->getName() !== $reflector->getName()) {
                continue;
            }
            foreach ($this->extractNamedTypes($prop->getType()) as $type) {
                $types[] = $type;
            }
        }

        if ($constructor = $reflector->getConstructor()) {
            foreach ($constructor->getParameters() as $param) {
                foreach ($this->extractNamedTypes($param->getType()) as $type) {
                    $types[] = $type;
                }
            }
        }

        return $types;
    }

    /**
     * @return list<\ReflectionNamedType>
     */
    protected function extractNamedTypes(?\ReflectionType $type): array
    {
        if ($type instanceof \ReflectionNamedType) {
            return [$type];
        }

        if ($type instanceof \ReflectionUnionType || $type instanceof \ReflectionIntersectionType) {
            $named = [];
            foreach ($type->getTypes() as $inner) {
                if ($inner instanceof \ReflectionNamedType) {
                    $named[] = $inner;
                }
            }

            return $named;
        }

        return [];
    }
}
