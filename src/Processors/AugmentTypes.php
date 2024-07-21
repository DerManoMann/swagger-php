<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Processors;

use OpenApi\Analysis;
use OpenApi\Annotations as OA;
use OpenApi\Generator;
use OpenApi\Processors\Concerns\DocblockTrait;

/**
 * Augments types.
 */
class AugmentTypes implements ProcessorInterface
{
    public function __invoke(Analysis $analysis)
    {
        foreach ($analysis->annotations as $annotation) {
            if ($annotation->_context->with('types')) {
                foreach($annotation->_context->types as $type) {

                }
            }
        }
    }
}
