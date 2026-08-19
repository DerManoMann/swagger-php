<?php declare(strict_types=1);

/*
 * @license Apache 2.0
 */

namespace OpenApi\Augmenter;

use OpenApi\Assembler;
use OpenApi\Assembler\AutoSchemaTranslator;
use OpenApi\AttributeInterface;
use OpenApi\Spec as OA;
use OpenApi\Specification;
use OpenApi\Utils\AttributeFactory;
use OpenApi\Utils\PipeInterface;
use OpenApi\Utils\TypedList;

/**
 * Auto annotate unknown refs.
 *
 * Typically, this will happen from processing sources via reflection where not all source files are known upfront.
 */
final class AutoAnnotate implements PipeInterface
{
    protected Assembler $assembler;

    protected array $collected = [];

    public function __construct()
    {
        $attributeFactory = (new AttributeFactory())
            ->withTranslators(
                fn (TypedList $translators): TypedList => $translators
                ->add(new AutoSchemaTranslator())
            );

        $this->assembler = new Assembler(attributeFactory: $attributeFactory);
    }

    public function __invoke(mixed $payload): mixed
    {
        $this->autoAnnotate($payload);

        return null;
    }

    public function group(): \BackedEnum
    {
        return Group::Resolve;
    }

    protected function autoAnnotate(Specification $specification): void
    {
        $specification->getWalker()->eachRef(function (AttributeInterface $attribute) use ($specification): void {
            if (property_exists($attribute, 'ref') && $attribute->ref !== null) {
                $ref = $attribute->ref instanceof OA\Schema\Ref
                    ? $attribute->ref->ref
                    : $attribute->ref;
                $this->collectModel($ref, $specification);
                $attribute->ref = $ref;
            }
        });

        $schemas = $this->assembler->getSpecification()->schemas;
        $specification->add(...$schemas);
        $this->assembler->getSpecification()->schemas = [];
    }

    protected function collectModel(string $fqcn, Specification $specification): void
    {
        if (isset($this->collected[$fqcn])) {
            return;
        }

        $this->collected[$fqcn] = true;

        $this->assembler->collect(new \ReflectionClass($fqcn));
        $this->collectReferencedTypes(new \ReflectionClass($fqcn), $specification);
    }

    protected function collectReferencedTypes(\ReflectionClass $reflector, Specification $specification): void
    {
        foreach ($this->getTypedReflectors($reflector) as $type) {
            if (!$type->isBuiltin()) {
                $this->collectModel($type->getName(), $specification);
            }
        }
    }

    /**
     * @return list<\ReflectionNamedType>
     */
    protected function getTypedReflectors(\ReflectionClass $reflector): array
    {
        $types = [];

        foreach ($reflector->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            if ($prop->getDeclaringClass()->getName() !== $reflector->getName()) {
                continue;
            }
            foreach ($this->extractNamedTypes($prop->getType()) as $type) {
                $types[] = $type;
            }
        }

        if ($constructor = $reflector->getConstructor()) {
            foreach ($constructor->getParameters() as $param) {
                foreach ($this->extractNamedTypes($param->getType()) as $type) {
                    $types[] = $type;
                }
            }
        }

        return $types;
    }

    /**
     * @return list<\ReflectionNamedType>
     */
    private function extractNamedTypes(?\ReflectionType $type): array
    {
        if ($type instanceof \ReflectionNamedType) {
            return [$type];
        }

        if ($type instanceof \ReflectionUnionType || $type instanceof \ReflectionIntersectionType) {
            $named = [];
            foreach ($type->getTypes() as $inner) {
                if ($inner instanceof \ReflectionNamedType) {
                    $named[] = $inner;
                }
            }

            return $named;
        }

        return [];
    }
}
