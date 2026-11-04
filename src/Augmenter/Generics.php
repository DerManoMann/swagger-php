<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Augmenter;

use OpenApi\Contracts\AttributeInterface;
use OpenApi\Contracts\ComponentNamerInterface;
use OpenApi\Contracts\RefInterface;
use OpenApi\Spec as OA;
use OpenApi\Specification;
use OpenApi\Specification\ComponentIndex;
use OpenApi\Specification\ComponentKey;
use OpenApi\Type\SchemaType;
use OpenApi\Type\TypeArguments;
use OpenApi\Type\TypeResolver;
use OpenApi\Utils\PipeInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\TypeInfo\Type;

/**
 * Adds a schema for each set of type arguments a generic class is used with.
 *
 * A class declaring `@template T` and used as `Envelope<Customer>` would otherwise be referenced
 * as the plain `Envelope`, whose `T` says nothing. Here the class's schema is copied under the key
 * `Envelope` with the type arguments `Customer`, and the augmenters after this one name, fill and
 * reference the copy as they would any schema: `Names` names it, `Types` types its members with
 * `T` as `Customer`, and a use of `Envelope<Customer>` references it.
 *
 * The uses are what properties and parameters are typed with, and a `Schema\Ref` qualified with
 * type arguments. A copy can use another one (`Envelope<Envelope<Customer>>`), so this runs until
 * no use is left without a schema, to a limit of nesting.
 *
 * A copy of a schema with an explicit name is named after that name rather than the class:
 * `Wrapper-of.Customer` for `#[OA\Schema(component: 'Wrapper')]`.
 *
 * A generic class used without arguments, or with `mixed` only, keeps its plain schema. So does a
 * class without a schema of its own: there is nothing to copy.
 *
 * @implements PipeInterface<Specification>
 */
class Generics implements PipeInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /** how deep copies can ask for copies, so a class using itself with growing arguments ends */
    protected const MAX_DEPTH = 10;

    public function __construct(
        protected TypeResolver $typeResolver = new TypeResolver(),
    ) {
    }

    public function __invoke(mixed $payload): mixed
    {
        // keys that cannot be made, not worth another look
        $skipped = [];
        $wanted = $this->wanted($payload);

        for ($depth = 0; $depth <= static::MAX_DEPTH; ++$depth) {
            // one index per round: a copy made in it changes no plain key
            $index = $payload->buildComponentIndex();
            $wanted = array_filter(
                array_diff_key($wanted, $skipped),
                static fn (ComponentKey $key): bool => !$index->hasKey($key),
            );
            if ($wanted === []) {
                return null;
            }

            if ($depth === static::MAX_DEPTH) {
                $this->logger?->warning(sprintf('Generic types nest more than %d deep, so these keep their plain schema: %s', static::MAX_DEPTH, implode(', ', array_keys($wanted))));

                return null;
            }

            // only a copy made in this round can use one that is not there yet
            $copies = [];
            foreach ($wanted as $id => $key) {
                $copy = $this->specialise($payload, $key, $index);
                if ($copy instanceof OA\Schema) {
                    $copies[] = $copy;
                } else {
                    $skipped[$id] = true;
                }
            }
            $wanted = $this->wantedBy($copies);
        }

        return null;
    }

    public function setTypeResolver(TypeResolver $typeResolver): static
    {
        $this->typeResolver = $typeResolver;

        return $this;
    }

    public function group(): string|\BackedEnum
    {
        return Group::Resolve;
    }

    /**
     * Every key with type arguments that a use asks for.
     *
     * @return array<string, ComponentKey> by their string
     */
    protected function wanted(Specification $specification): array
    {
        $keys = [];

        // one walk for all three kinds of use; each walk visits the whole document
        $specification->getWalker()->visit(AttributeInterface::class, function (AttributeInterface $attribute) use (&$keys): void {
            // `Types` types a parameter by its own reflector only, never by its method's
            if ($attribute instanceof OA\Property
                || ($attribute instanceof OA\Parameter && $attribute->getReflector() instanceof \ReflectionParameter)
            ) {
                $this->collect($keys, $attribute->getReflector(), $attribute->schema);
            }

            $key = $attribute instanceof RefInterface ? $attribute->getRefKey() : null;
            if ($key instanceof ComponentKey && is_string($key->qualifiers[ComponentKey::TYPE_ARGUMENTS] ?? null)) {
                $keys[(string) $key] = $key;
            }
        });

        return [...$keys, ...$this->wantedBy($specification->schemas)];
    }

    /**
     * The keys the members of schemas made for type arguments use. A copy's members read its
     * templates, so they may use another copy.
     *
     * @param iterable<OA\Schema> $schemas
     *
     * @return array<string, ComponentKey> by their string
     */
    protected function wantedBy(iterable $schemas): array
    {
        $keys = [];
        foreach ($schemas as $schema) {
            $templates = $this->typeResolver->templates($schema->getComponentKey());
            if ($templates === []) {
                continue;
            }

            foreach ($schema->properties ?? [] as $property) {
                $this->collect($keys, $property->getReflector(), $property->schema, $templates);
            }
        }

        return $keys;
    }

    /**
     * The keys in a member's type, unless its schema references something already or has a type
     * of its own, which `Types` leaves in place.
     *
     * @param array<string, ComponentKey> $keys
     * @param array<string, Type>         $templates
     */
    protected function collect(array &$keys, ?\Reflector $reflector, ?OA\Schema $schema = null, array $templates = []): void
    {
        if ($schema instanceof OA\Schema
            && ($schema->ref !== null || $schema->type !== null || $schema->oneOf !== null || $schema->allOf !== null || $schema->anyOf !== null)
        ) {
            return;
        }

        if ($reflector instanceof \ReflectionProperty || $reflector instanceof \ReflectionParameter || $reflector instanceof \ReflectionMethod) {
            $this->collectFromType($keys, $this->typeResolver->resolveWithTemplates($reflector, $templates));
        }
    }

    /**
     * @param array<string, ComponentKey> $keys
     */
    protected function collectFromType(array &$keys, ?SchemaType $type): void
    {
        if (!$type instanceof SchemaType) {
            return;
        }

        $key = $type->componentKey();
        if ($key instanceof ComponentKey && !$key->isPlain()) {
            $keys[(string) $key] = $key;
        }

        $this->collectFromType($keys, $type->items);
        $this->collectFromType($keys, $type->additionalProperties instanceof SchemaType ? $type->additionalProperties : null);
        foreach ([...$type->oneOf ?? [], ...$type->allOf ?? [], ...$type->anyOf ?? [], ...$type->properties ?? []] as $member) {
            $this->collectFromType($keys, $member);
        }
    }

    /**
     * Copies the class's schema under the key, or returns null where there is nothing to copy.
     * The copy is named by `Names`, from the key. A name the plain schema was given stays with
     * it, and the copy's name builds on it instead of the class's, unless a base name is set
     * already.
     */
    protected function specialise(Specification $specification, ComponentKey $key, ComponentIndex $index): ?OA\Schema
    {
        $plain = $index->findAllByKey(new ComponentKey($key->class), 'schemas');
        if (count($plain) !== 1 || !$plain[0] instanceof OA\Schema) {
            return null;
        }

        // only a written-out ref can ask for arguments that bind nothing; a type cannot
        $arguments = $key->qualifiers[ComponentKey::TYPE_ARGUMENTS] ?? null;
        if (!is_string($arguments)) {
            return null;
        }

        if ($this->typeResolver->templates($key) === []) {
            $this->logger?->warning(sprintf(
                'Type arguments "%s" for %s bind no template, so no schema is made for them; %s',
                $arguments,
                $key->class,
                TypeArguments::parse($key->class, $arguments) === null
                    ? 'write each class with its namespace'
                    : 'the class declares no @template',
            ));

            return null;
        }

        $copy = $plain[0]->copy();
        $copy->setComponentKey($key);
        if ($copy->component !== null && $copy->getMeta(ComponentNamerInterface::class) === null) {
            $copy->setMeta(ComponentNamerInterface::class, $copy->component);
        }
        $copy->component = null;
        $specification->add($copy);

        return $copy;
    }
}
