<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Type;

use Radebatz\TypeInfoExtras\TypeResolver\StringTypeResolver;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\GenericType;

/**
 * The type arguments of a generic class as one string, the form a `ComponentKey` holds them in.
 *
 * Each type is written as type-info writes it, with no leading `\`, and `, ` goes between them, so
 * one set of arguments has one spelling wherever it is written.
 */
final class TypeArguments
{
    /** @var array<string, list<Type>|null> what each generic type parsed to, by the type */
    private static array $parsed = [];

    public static function spell(Type ...$arguments): string
    {
        return implode(', ', array_map(static fn (Type $argument): string => ltrim((string) $argument, '\\'), $arguments));
    }

    /**
     * The arguments as types, or null where type-info cannot read them, such as a class that does
     * not exist. They are read as `TypeResolver` reads a docblock, so `int<0, 100>` keeps its range.
     *
     * @return list<Type>|null
     */
    public static function parse(string $class, string $arguments): ?array
    {
        $type = '\\' . ltrim($class, '\\') . '<' . $arguments . '>';
        if (!array_key_exists($type, self::$parsed)) {
            try {
                $generic = (new StringTypeResolver())->resolve($type);
                self::$parsed[$type] = $generic instanceof GenericType ? array_values($generic->getVariableTypes()) : null;
            } catch (\Throwable) {
                self::$parsed[$type] = null;
            }
        }

        return self::$parsed[$type];
    }

    /**
     * The arguments in the one spelling, or as given where type-info cannot read them.
     */
    public static function normalise(string $class, string $arguments): string
    {
        $types = self::parse($class, $arguments);

        return $types === null ? $arguments : self::spell(...$types);
    }
}
