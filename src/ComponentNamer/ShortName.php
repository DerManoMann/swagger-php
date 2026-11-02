<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\ComponentNamer;

use OpenApi\Contracts\AttributeInterface;
use OpenApi\Contracts\ComponentNamerInterface;
use OpenApi\Spec as OA;

/**
 * Names a component after the class or holder member it is declared on.
 *
 * - declared on a class: the class's short name, so a `$ref` given as the class resolves to it
 * - declared on a member of a class carrying `#[OA\Components]`: the member's own name,
 *   whatever kind of member it is
 * - anywhere else: null. The declaring class's name belongs to that class's own component,
 *   so the component stays inline
 */
class ShortName implements ComponentNamerInterface
{
    public function name(AttributeInterface $component): ?string
    {
        $reflector = $component->getReflector();

        if ($reflector instanceof \ReflectionClass) {
            // SPIKE: a specialisation is named after its type arguments too
            $types = $component->getComponentKey()?->qualifiers['types'] ?? null;

            return $this->className($reflector) . (is_string($types) ? '_' . $this->typeName($types) : '');
        }

        $holder = $component->getClassReflector()?->getAttributes(OA\Components::class) ?? [];
        if ($holder !== [] && ($reflector instanceof \ReflectionMethod || $reflector instanceof \ReflectionProperty || $reflector instanceof \ReflectionClassConstant || $reflector instanceof \ReflectionParameter)) {
            return $reflector->getName();
        }

        return null;
    }

    /**
     * SPIKE: type arguments as name segments, `App\Envelope<App\Customer>, int` as `Envelope_Customer_int`.
     */
    protected function typeName(string $types): string
    {
        preg_match_all('/[A-Za-z_][A-Za-z0-9_\\\\]*/', $types, $matches);

        return implode('_', array_map(
            fn (string $name): string => class_exists($name) || interface_exists($name) ? $this->className(new \ReflectionClass($name)) : $name,
            $matches[0],
        ));
    }

    /**
     * @param \ReflectionClass<object> $class
     */
    protected function className(\ReflectionClass $class): string
    {
        return $class->getShortName();
    }
}
