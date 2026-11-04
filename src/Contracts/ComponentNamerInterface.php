<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Contracts;

/**
 * Names a component that is not named explicitly.
 *
 * A name can be asked for before it is set, to reference the component, and again when it is
 * set. Both have to agree, so the name depends on the component alone, not on when or how often it
 * is asked.
 *
 * A component can carry the name a derived name builds on, as a string under the meta key
 * `ComponentNamerInterface::class`. The built-in namers start from it rather than from the class,
 * and add the key's qualifiers to it. `Augmenter\Generics` sets it on the schemas it makes for a
 * generic class's type arguments, to the name the class's own schema was given, unless one is set
 * already.
 */
interface ComponentNamerInterface
{
    /**
     * The name a component without one is filed under, or null to leave it inline.
     */
    public function name(AttributeInterface $component): ?string;
}
