<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

abstract class AbstractAugmenter implements SpecAugmenterInterface
{
    public function __invoke(Specification $specification): void
    {
        $this->augment($specification);
    }
}
