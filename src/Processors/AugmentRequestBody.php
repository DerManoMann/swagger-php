<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Processors;

use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Generator;

/**
 * Use the RequestBody context to extract useful information and inject that into the annotation.
 */
class AugmentRequestBody
{
    public function __invoke(Analysis $analysis): void
    {
        $requestBodies = $analysis->getAnnotationsOfType(OA\RequestBody::class);

        $this->augmentRequestBody($requestBodies);
    }

    /**
     * @param array<OA\RequestBody> $requestBodies
     */
    protected function augmentRequestBody(array $requestBodies): void
    {
        foreach ($requestBodies as $requestBody) {
            if (!$requestBody->isRoot(OA\RequestBody::class)) {
                continue;
            }

            $context = $requestBody->_context;
            if (Generator::isDefault($requestBody->request)) {
                if ($context->is('class')) {
                    $requestBody->request = $requestBody->_context->class;
                } elseif ($context->is('interface')) {
                    $requestBody->request = $requestBody->_context->interface;
                } elseif ($context->is('trait')) {
                    $requestBody->request = $requestBody->_context->trait;
                } elseif ($context->is('enum')) {
                    $requestBody->request = $requestBody->_context->enum;
                }
            }

            if ($context->reflector instanceof \ReflectionParameter) {
                // todo: use type resolver
                $rnt = $context->reflector->getType();
                $nullable = $rnt ? $rnt->allowsNull() : true;
                $requestBody->required = !$nullable;
            }
        }
    }
}
