<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Type\Generics;

use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ArrayShapeType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\CompositeTypeInterface;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\NullableType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\WrappingTypeInterface;

/**
 * The type arguments a generic class is used with, such as `Customer` in `Envelope<Customer>`.
 *
 * They are kept as types. As a string each type is written as type-info writes it, every class
 * name without a leading `\`, and `, ` between them, so one set of arguments has one spelling.
 */
readonly class TypeArguments implements \Stringable
{
    /** @var non-empty-list<Type> */
    public array $types;

    public function __construct(Type $type, Type ...$types)
    {
        $this->types = [$type, ...array_values($types)];
    }

    public function __toString(): string
    {
        // nested ones too, so `list<\App\Customer>` and `list<App\Customer>` are one spelling
        return (string) preg_replace('/(?<![\w\\\\])\\\\/', '', implode(', ', array_map(strval(...), $this->types)));
    }

    /**
     * How deep the arguments nest: each type that takes types of its own adds a level. `Customer`
     * and `int` are 0, `list<Customer>` and `Box<int>` are 1, `Box<list<int>>` is 2. The deepest
     * argument counts.
     */
    public function depth(): int
    {
        return $this->deepest($this->types);
    }

    /**
     * The classes named anywhere in the arguments, each without a leading `\`.
     *
     * @return list<string>
     */
    public function classes(): array
    {
        return array_values(array_unique($this->classesIn(...$this->types)));
    }

    /**
     * Type-info's own `traverse()` does not reach a generic type's arguments.
     *
     * @return list<string>
     */
    protected function classesIn(Type ...$types): array
    {
        $classes = [];
        foreach ($types as $type) {
            $classes = [...$classes, ...match (true) {
                $type instanceof ArrayShapeType => $this->classesIn(
                    ...array_column($type->getShape(), 'type'),
                    ...array_filter([$type->getExtraValueType()]),
                ),
                $type instanceof GenericType => $this->classesIn($type->getWrappedType(), ...$type->getVariableTypes()),
                $type instanceof WrappingTypeInterface => $this->classesIn($type->getWrappedType()),
                $type instanceof CompositeTypeInterface => $this->classesIn(...$type->getTypes()),
                $type instanceof ObjectType => [ltrim($type->getClassName(), '\\')],
                default => [],
            }];
        }

        return $classes;
    }

    /**
     * @param array<Type> $types
     */
    protected function deepest(array $types): int
    {
        return $types === [] ? 0 : max(array_map($this->depthOf(...), $types));
    }

    protected function depthOf(Type $type): int
    {
        return match (true) {
            // a shape before a collection, which it is too: its members are the level below
            $type instanceof ArrayShapeType => 1 + $this->deepest([
                ...array_column($type->getShape(), 'type'),
                ...array_filter([$type->getExtraValueType()]),
            ]),
            // `list<int>` is a collection around `array<int, int>`: one level, read off the key and value
            $type instanceof CollectionType => 1 + $this->deepest([$type->getCollectionKeyType(), $type->getCollectionValueType()]),
            $type instanceof GenericType => 1 + $this->deepest($type->getVariableTypes()),
            // `?T` and `A|B` add no level of their own
            $type instanceof NullableType => $this->depthOf($type->getWrappedType()),
            $type instanceof CompositeTypeInterface => $this->deepest($type->getTypes()),
            default => 0,
        };
    }
}
