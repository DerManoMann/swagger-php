<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Type\Generics;

use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ArrayShapeType;
use Symfony\Component\TypeInfo\Type\CompositeTypeInterface;
use Symfony\Component\TypeInfo\Type\GenericType;
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
}
