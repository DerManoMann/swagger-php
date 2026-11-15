<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Type\Generics;

use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ArrayShapeType;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\ObjectType;

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
     * @return list<string>
     */
    protected function classesIn(Type ...$types): array
    {
        $inner = new InnerTypes();
        $classes = [];
        foreach ($types as $type) {
            if ($type instanceof ObjectType) {
                $classes[] = ltrim($type->getClassName(), '\\');
            }
            $classes = [...$classes, ...$this->classesIn(...$inner->of($type))];
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
        // a shape and a type with arguments are a level, and what is inside them the level below.
        // `list<int>` is a collection around `array<int, int>`, so it is one level, not two
        $level = $type instanceof ArrayShapeType || $type instanceof GenericType ? 1 : 0;

        return $level + $this->deepest((new InnerTypes())->of($type));
    }
}
