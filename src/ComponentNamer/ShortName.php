<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\ComponentNamer;

use OpenApi\Contracts\AttributeInterface;
use OpenApi\Contracts\ComponentNamerInterface;
use OpenApi\Spec as OA;
use OpenApi\Specification\ComponentKey;
use OpenApi\Type\Generics\TypeArguments;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ArrayShapeType;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\TypeIdentifier;

/**
 * Names a component after the class or holder member it is declared on.
 *
 * - declared on a class: the class's short name, so a `$ref` given as the class resolves to it
 * - declared on a member of a class carrying `#[OA\Components]`: the member's own name,
 *   whatever kind of member it is
 * - anywhere else: null. The declaring class's name belongs to that class's own component,
 *   so the component stays inline
 *
 * A component whose key has qualifiers adds them, so each description of a class gets a name of
 * its own: `-` before each qualifier, `.` between its name and its values, `_` between values.
 * `Author` with groups `admin` and `summary` is `Author-groups.admin_summary`.
 *
 * A component whose key has type arguments adds them last, after `-of.` and with `_` between
 * them, each written as its own component would be named: `Envelope<Customer>` is
 * `Envelope-of.Customer` and `Pair<string, Customer>` is `Pair-of.string_Customer`. An argument
 * can be a class, `int`, `float`, `string`, `bool` or `mixed`, a list of one of these, or a class
 * with type arguments of its own: `Envelope<list<Customer>>` is `Envelope-of.list-of.Customer`.
 * A component with any other argument gets no name. Two different sets of arguments can come out
 * as the same name, as two classes with the same short name do.
 */
class ShortName implements ComponentNamerInterface
{
    public function name(AttributeInterface $component): ?string
    {
        $name = $this->baseName($component);
        $key = $component->getComponentKey();

        if ($name === null || !$key instanceof ComponentKey || $key->isPlain()) {
            return $name;
        }

        $name .= $this->qualifiers($key);
        if (!$key->typeArguments instanceof TypeArguments) {
            return $name;
        }

        $typeArguments = $this->typeArguments($key->typeArguments->types);

        return $typeArguments === null ? null : $name . $typeArguments;
    }

    protected function baseName(AttributeInterface $component): ?string
    {
        $reflector = $component->getReflector();

        if ($reflector instanceof \ReflectionClass) {
            return $this->className($reflector);
        }

        $holder = $component->getClassReflector()?->getAttributes(OA\Components::class) ?? [];
        if ($holder !== [] && ($reflector instanceof \ReflectionMethod || $reflector instanceof \ReflectionProperty || $reflector instanceof \ReflectionClassConstant || $reflector instanceof \ReflectionParameter)) {
            return $reflector->getName();
        }

        return null;
    }

    /**
     * The qualifiers in the key's order, each with its name. The key sorts names and values as
     * given, so the order does not change with how they were added, and a value named here can
     * sort differently from the value itself.
     */
    protected function qualifiers(ComponentKey $key): string
    {
        $suffix = '';
        foreach ($key->qualifiers as $name => $values) {
            $values = array_map($this->qualifierValue(...), (array) $values);
            $suffix .= '-' . $this->clean($name) . ($values === [] ? '' : '.' . implode('_', $values));
        }

        return $suffix;
    }

    /**
     * The type arguments after `-of.`, or null where one of them cannot be written.
     *
     * @param list<Type> $types
     */
    protected function typeArguments(array $types): ?string
    {
        $names = array_map($this->typeArgument(...), $types);

        return in_array(null, $names, true) ? null : '-of.' . implode('_', $names);
    }

    /**
     * One type argument as its own component would be named, or null for a type that is not
     * written yet, such as a union or an array shape.
     */
    protected function typeArgument(Type $type): ?string
    {
        if ($type instanceof CollectionType) {
            $items = $type->isList() && !$type instanceof ArrayShapeType ? $this->typeArguments([$type->getCollectionValueType()]) : null;

            return $items === null ? null : 'list' . $items;
        }

        if ($type instanceof GenericType) {
            $class = $this->typeArgument($type->getWrappedType());
            $typeArguments = $type->getWrappedType() instanceof ObjectType ? $this->typeArguments(array_values($type->getVariableTypes())) : null;

            return $class === null || $typeArguments === null ? null : $class . $typeArguments;
        }

        if ($type instanceof ObjectType) {
            $class = $type->getClassName();

            return class_exists($class) || interface_exists($class) ? $this->className(new \ReflectionClass($class)) : null;
        }

        $scalars = [TypeIdentifier::INT, TypeIdentifier::FLOAT, TypeIdentifier::STRING, TypeIdentifier::BOOL, TypeIdentifier::MIXED];
        if ($type instanceof BuiltinType && in_array($type->getTypeIdentifier(), $scalars, true)) {
            return $type->getTypeIdentifier()->value;
        }

        return null;
    }

    /**
     * A class is named like any other, so it reads the same as its own component.
     */
    protected function qualifierValue(bool|float|int|string $value): string
    {
        if (is_string($value) && (class_exists($value) || interface_exists($value))) {
            return $this->className(new \ReflectionClass($value));
        }

        return $this->clean(is_bool($value) ? var_export($value, true) : (string) $value);
    }

    /**
     * Anything a component name does not allow becomes `_`.
     */
    protected function clean(string $value): string
    {
        return (string) preg_replace('/[^a-zA-Z0-9._-]/', '_', $value);
    }

    /**
     * @param \ReflectionClass<object> $class
     */
    protected function className(\ReflectionClass $class): string
    {
        return $class->getShortName();
    }
}
