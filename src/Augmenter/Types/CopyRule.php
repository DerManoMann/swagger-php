<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Augmenter\Types;

use OpenApi\Contracts\ComponentNamerInterface;
use OpenApi\Spec as OA;
use OpenApi\Specification\ComponentKey;
use OpenApi\Type\Generics\TemplateBinder;
use OpenApi\Type\Generics\TemplateReader;
use OpenApi\Type\Generics\TypeArguments;
use Symfony\Component\TypeInfo\Type;

/**
 * Says whether a generic class used with type arguments gets a copy of its schema, and what the
 * copy is named.
 *
 * Asked in two steps. `refusal()` reads the class and the arguments only, so it can be asked for a
 * class without a schema. `name()` needs the class's own schema, the one a copy is made from. Each
 * gives a `Refusal` where the use keeps the plain schema.
 *
 * A name written on the class by hand wins over the namer: `#[OA\Schema(component: 'Parcel')]` on
 * `Box` makes `Box<Customer>` the copy `Parcel-of.Customer`.
 */
class CopyRule
{
    /**
     * How deep type arguments can nest before a use is refused, as `TypeArguments::depth()` counts.
     * A class using itself with a growing argument, as `Node` with a member `Node<list<T>>` does,
     * would otherwise ask for a deeper copy for ever.
     */
    public const MAX_DEPTH = 5;

    protected TemplateBinder $templateBinder;

    public function __construct(
        protected TemplateReader $templateReader,
        protected ComponentNamerInterface $namer,
    ) {
        $this->templateBinder = new TemplateBinder();
    }

    /**
     * Why the class and the arguments in the key give no copy, or null where they can.
     */
    public function refusal(ComponentKey $key): ?Refusal
    {
        $typeArguments = $key->typeArguments;
        if (!$typeArguments instanceof TypeArguments) {
            // a plain key is the plain schema
            return Refusal::SaysNothing;
        }

        // most likely a wrong docblock, where the author expects a schema named for the arguments
        if ($this->templateReader->templateNames($key->class) === []) {
            return Refusal::NoTemplates;
        }

        // `Box<int, string>` for a class with one template
        if (!$this->templateReader->accepts($key->class, $typeArguments)) {
            return Refusal::Mismatch;
        }

        if ($this->saysNothing($typeArguments)) {
            return Refusal::SaysNothing;
        }

        // the limit is on the key, not on how many copies were made before it, so the same use is
        // refused however it was reached
        if ($typeArguments->depth() > static::MAX_DEPTH) {
            return Refusal::TooDeep;
        }

        return null;
    }

    /**
     * The name of the copy of `$plain` for the key, or why there is none.
     *
     * @param OA\Schema $plain the class's own schema, as named by `Names` or by hand
     */
    public function name(ComponentKey $key, OA\Schema $plain): string|Refusal
    {
        $name = $this->namersName($plain, $key);
        if ($name === null) {
            return Refusal::Unnamed;
        }

        // a hand-written name on the class: the namer's name for the class is swapped for it, and
        // what the namer adds for the arguments is kept
        if ($plain->isAuthored('component') && is_string($plain->component)) {
            $namersPlainName = $this->namersName($plain, $plain->getComponentKey());
            // a namer that does not start the copy's name with the class's leaves it as it is
            if ($namersPlainName !== null && str_starts_with($name, $namersPlainName)) {
                $name = $plain->component . substr($name, strlen($namersPlainName));
            }
        }

        // The plain schema's name is the one it has, or the one `Names` will give it. Two schemas
        // with one name would be merged, and the copy kept in place of the plain schema.
        $plainName = $plain->component ?? $this->namersName($plain, $plain->getComponentKey());
        if ($name === $plainName) {
            return Refusal::SameName;
        }

        return $name;
    }

    /**
     * What the namer calls the schema with the given key. A namer reads the key off the component
     * it is given, and names one that has no name yet, so it is asked about a shallow clone with
     * that key and no name. The schema itself is left as it is.
     */
    protected function namersName(OA\Schema $schema, ?ComponentKey $key): ?string
    {
        $unnamed = clone $schema;
        // the namer names a component that has no name yet
        $unnamed->component = null;
        $unnamed->setComponentKey($key);

        return $this->namer->name($unnamed);
    }

    /**
     * Whether the arguments say nothing the class's templates do not: all are `mixed`, or one
     * still holds a template.
     */
    protected function saysNothing(TypeArguments $typeArguments): bool
    {
        // `Pair<mixed, int>` does say something, so only all of them count
        $mixed = array_filter($typeArguments->types, static fn (Type $argument): bool => (string) $argument === 'mixed');
        $templates = array_filter($typeArguments->types, $this->templateBinder->hasTemplate(...));

        return count($mixed) === count($typeArguments->types) || $templates !== [];
    }
}
