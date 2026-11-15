<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Type\Generics;

use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ArrayShapeType;
use Symfony\Component\TypeInfo\Type\CompositeTypeInterface;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\WrappingTypeInterface;

/**
 * The types directly inside a type, such as `Box` and `Customer` in `Box<Customer>`.
 *
 * Type-info's own `traverse()` does not reach a generic type's arguments. Code that looks for
 * something anywhere in a type recurses over these, and asks its own question of each.
 */
class InnerTypes
{
    /**
     * @return list<Type>
     */
    public function of(Type $type): array
    {
        return match (true) {
            // a shape before a collection, which it is too: its members are what is inside
            $type instanceof ArrayShapeType => [
                ...array_column($type->getShape(), 'type'),
                ...array_filter([$type->getExtraValueType()]),
            ],
            $type instanceof GenericType => [$type->getWrappedType(), ...$type->getVariableTypes()],
            // `?T`, and `list<int>`, a collection around `array<int, int>`
            $type instanceof WrappingTypeInterface => [$type->getWrappedType()],
            $type instanceof CompositeTypeInterface => array_values($type->getTypes()),
            default => [],
        };
    }
}
