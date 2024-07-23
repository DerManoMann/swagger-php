<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Processors;

use OpenApi\Analysis;
use OpenApi\Annotations as OA;

/**
 * Augments types.
 */
class AugmentTypes implements ProcessorInterface
{
    public function __invoke(Analysis $analysis)
    {
        foreach ($analysis->annotations as $annotation) {
            if ($annotation instanceof OA\AbstractAnnotation && $annotation->_context->with('types')) {
                foreach ($annotation->_context->types as $type) {
                    echo get_class($type) . ' :: ' . get_class($annotation) . PHP_EOL;
                }
            }
        }
    }
}
