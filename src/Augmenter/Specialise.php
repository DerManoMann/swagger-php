<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Augmenter;

use OpenApi\ComponentNamer;
use OpenApi\Contracts\AttributeInterface;
use OpenApi\Contracts\ComponentNamerInterface;
use OpenApi\Spec as OA;
use OpenApi\Specification;
use OpenApi\Specification\ComponentKey;
use OpenApi\Type\TypeResolver;
use OpenApi\Utils\PipeInterface;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\NullableType;
use Symfony\Component\TypeInfo\Type\TemplateType;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver;

/**
 * SPIKE: makes the specialisation of a generic class schema that a use asks for by key.
 *
 * A use of `Envelope<Customer>` is a ref to `Envelope` qualified with `types`. Where no schema is
 * filed under that key, the plain `Envelope` schema is copied, keyed and named, and each property
 * whose PHP type mentions a template is typed again with the template replaced by its argument.
 * A specialisation can ask for another one (`Envelope<Envelope<Customer>>`), so it runs until no
 * use is left without a schema.
 *
 * @implements PipeInterface<Specification>
 */
class Specialise implements PipeInterface
{
    public function __construct(
        protected Types $types = new Types(),
        protected TypeResolver $typeResolver = new TypeResolver(),
        protected ComponentNamerInterface $namer = new ComponentNamer\ShortName(),
    ) {
    }

    public function setNamer(ComponentNamerInterface $namer): static
    {
        $this->namer = $namer;

        return $this;
    }

    public function setTypes(Types $types): static
    {
        $this->types = $types;

        return $this;
    }

    public function group(): string|\BackedEnum
    {
        return Group::Resolve;
    }

    public function __invoke(mixed $payload): mixed
    {
        do {
            $wanted = $this->wanted($payload);
            foreach ($wanted as $key) {
                $this->specialise($payload, $key);
            }
        } while ($wanted !== []);

        return null;
    }

    /**
     * Every key a use asks for with `types` that no schema is filed under yet.
     *
     * @return array<string, ComponentKey>
     */
    protected function wanted(Specification $specification): array
    {
        $index = $specification->buildComponentIndex();
        $wanted = [];

        $specification->getWalker()->eachRef(function (AttributeInterface $attribute) use ($index, &$wanted): void {
            if (!property_exists($attribute, 'ref') || !$attribute->ref instanceof OA\Schema\Ref) {
                return;
            }

            $key = $attribute->ref->getRefKey();
            if ($key instanceof ComponentKey && isset($key->qualifiers['types']) && $index->findAllByKey($key, 'schemas') === []) {
                $wanted[(string) $key] = $key;
            }
        });

        return $wanted;
    }

    protected function specialise(Specification $specification, ComponentKey $key): void
    {
        $plain = $specification->buildComponentIndex()->findByKey(new ComponentKey($key->class), 'schemas');
        if (!$plain instanceof OA\Schema) {
            return;
        }

        $arguments = (new StringTypeResolver())->resolve($key->class . '<' . $key->qualifiers['types'] . '>');
        $templates = array_keys((new TypeContextFactory(new StringTypeResolver()))->createFromClassName($key->class)->templates);
        $bindings = $arguments instanceof GenericType
            ? array_combine(array_slice($templates, 0, count($arguments->getVariableTypes())), array_slice($arguments->getVariableTypes(), 0, count($templates)))
            : [];

        $copy = $plain->copy();
        $copy->setComponentKey($key);
        $copy->component = $this->namer->name($copy);

        foreach ($copy->properties ?? [] as $property) {
            $reflector = $property->getReflector();
            $type = $reflector instanceof \ReflectionProperty ? $this->typeResolver->getDocblockType($reflector) : null;
            if (!$type instanceof Type) {
                continue;
            }

            $bound = $this->bind($type, $bindings);
            if ($bound !== $type) {
                $property->schema = $this->types->schemaTypeToSchema($this->typeResolver->resolveType($bound));
            }
        }

        $specification->add($copy);
    }

    /**
     * The type with each template replaced by its argument, or the type itself when it mentions none.
     *
     * @param array<string, Type> $bindings
     */
    protected function bind(Type $type, array $bindings): Type
    {
        return match (true) {
            $type instanceof TemplateType => $bindings[$type->getName()] ?? $type,
            $type instanceof NullableType => $this->rebuilt($type, $type->getWrappedType(), $bindings, fn (Type $inner): Type => new NullableType($inner)),
            $type instanceof CollectionType => $this->rebuilt($type, $type->getWrappedType(), $bindings, fn (Type $inner): Type => new CollectionType($inner, $type->isList())),
            $type instanceof GenericType => $this->rebuiltGeneric($type, $bindings),
            $type instanceof UnionType => $this->rebuiltUnion($type, $bindings),
            default => $type,
        };
    }

    /**
     * @param array<string, Type>  $bindings
     * @param callable(Type): Type $wrap
     */
    protected function rebuilt(Type $type, Type $inner, array $bindings, callable $wrap): Type
    {
        $bound = $this->bind($inner, $bindings);

        return $bound === $inner ? $type : $wrap($bound);
    }

    /**
     * @param array<string, Type> $bindings
     */
    protected function rebuiltGeneric(GenericType $type, array $bindings): Type
    {
        $variables = $type->getVariableTypes();
        $bound = array_map(fn (Type $variable): Type => $this->bind($variable, $bindings), $variables);

        return $bound === $variables ? $type : new GenericType($type->getWrappedType(), ...$bound);
    }

    /**
     * @param array<string, Type> $bindings
     */
    protected function rebuiltUnion(UnionType $type, array $bindings): Type
    {
        $members = $type->getTypes();
        $bound = array_map(fn (Type $member): Type => $this->bind($member, $bindings), $members);

        return $bound === $members ? $type : new UnionType(...$bound);
    }
}
