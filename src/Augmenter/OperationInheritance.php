<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Augmenter;

use OpenApi\Spec as OA;
use OpenApi\Specification;
use OpenApi\Utils\AttributeFactory;
use OpenApi\Utils\PipeInterface;
use OpenApi\Utils\TokenScanner;

/**
 * Clones operations from ancestor classes into concrete child controllers.
 *
 * When an abstract (or non-annotated) parent class declares operations, those operations
 * are collected during assembly with the parent as their class context. Child classes
 * with a PathItem expect to inherit those operations so that prefix/tag/security
 * composition can apply.
 *
 * This augmenter walks each PathItem class's ancestors: if an ancestor has no PathItem
 * of its own, its operations are cloned and re-associated with the child class. The
 * original operations (associated with the abstract parent) are removed since they
 * have no PathItem context of their own.
 *
 * Must run before PathItems so that inherited operations are available for prefix resolution.
 *
 * @implements PipeInterface<Specification>
 */
class OperationInheritance implements PipeInterface
{
    public function __construct(
        protected AttributeFactory $attributeFactory = new AttributeFactory(),
        protected TokenScanner $tokenScanner = new TokenScanner(),
    ) {
    }

    public function setAttributeFactory(AttributeFactory $attributeFactory): static
    {
        $this->attributeFactory = $attributeFactory;

        return $this;
    }

    public function setTokenScanner(TokenScanner $tokenScanner): static
    {
        $this->tokenScanner = $tokenScanner;

        return $this;
    }

    public function group(): string|\BackedEnum
    {
        return Group::Resolve;
    }

    public function __invoke(mixed $payload): mixed
    {
        $pathItemClasses = $this->indexPathItemClasses($payload);

        if ($pathItemClasses === []) {
            return null;
        }

        $ancestorOperations = $this->findInheritableOperations($payload, $pathItemClasses);

        if ($ancestorOperations === []) {
            return null;
        }

        $this->cloneToChildren($payload, $pathItemClasses, $ancestorOperations);

        return null;
    }

    /**
     * @return array<class-string, true>
     */
    protected function indexPathItemClasses(Specification $specification): array
    {
        $map = [];

        foreach ($specification->pathItems as $pathItem) {
            $className = $pathItem->getClassName();
            if ($className !== null) {
                $map[$className] = true;
            }
        }

        return $map;
    }

    /**
     * Find operations whose owning class has no PathItem — these are inheritable.
     *
     * @param  array<class-string, true>               $pathItemClasses
     * @return array<class-string, list<OA\Operation>> Grouped by declaring class
     */
    protected function findInheritableOperations(Specification $specification, array $pathItemClasses): array
    {
        $grouped = [];

        foreach ($specification->operations as $operation) {
            $className = $operation->getClassName();
            if ($className === null || isset($pathItemClasses[$className])) {
                continue;
            }

            $grouped[$className][] = $operation;
        }

        return $grouped;
    }

    /**
     * For each PathItem class, walk ancestors and clone inheritable operations.
     *
     * @param array<class-string, true>               $pathItemClasses
     * @param array<class-string, list<OA\Operation>> $ancestorOperations
     */
    protected function cloneToChildren(Specification $specification, array $pathItemClasses, array $ancestorOperations): void
    {
        $toRemove = [];

        foreach (array_keys($pathItemClasses) as $className) {
            $reflector = new \ReflectionClass($className);

            $parent = $reflector->getParentClass();
            while ($parent !== false) {
                $parentName = $parent->getName();

                if (isset($pathItemClasses[$parentName])) {
                    break;
                }

                if (isset($ancestorOperations[$parentName])) {
                    foreach ($ancestorOperations[$parentName] as $operation) {
                        $clone = clone $operation;
                        $clone->setReflector($reflector);
                        $specification->add($clone);
                        $toRemove[spl_object_id($operation)] = true;
                    }
                }

                $parent = $parent->getParentClass();
            }
        }

        if ($toRemove !== []) {
            $specification->operations = array_values(array_filter(
                $specification->operations,
                static fn (OA\Operation $op): bool => !isset($toRemove[spl_object_id($op)]),
            ));
        }
    }
}
