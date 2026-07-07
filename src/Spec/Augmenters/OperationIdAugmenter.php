<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec\Augmenters;

use OpenApi\Spec\AbstractAugmenter;
use OpenApi\Spec\Operation;
use OpenApi\Spec\Specification;

class OperationIdAugmenter extends AbstractAugmenter
{
    public function __construct(
        protected bool $hash = true,
    ) {
    }

    public function augment(Specification $specification): void
    {
        foreach ($specification->operations as $operation) {
            if ($operation->operationId !== null) {
                continue;
            }

            $operationId = $this->generateId($operation);
            if ($operationId !== null) {
                $operation->operationId = $this->hash ? md5($operationId) : $operationId;
            }
        }
    }

    protected function generateId(Operation $operation): ?string
    {
        $reflector = $operation->getReflector();

        $source = null;
        if ($reflector instanceof \ReflectionMethod) {
            $class = $reflector->getDeclaringClass()->getName();
            $source = $class . '::' . $reflector->getName();
        } elseif ($reflector instanceof \ReflectionFunction) {
            $source = $reflector->getName();
        }

        if ($source === null) {
            return null;
        }

        $method = strtoupper($operation->method ?? 'GET');
        $path = $operation->path ?? '';

        return $method . '::' . $path . '::' . $source;
    }
}
