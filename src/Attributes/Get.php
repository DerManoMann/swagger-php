<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Attributes;

use OpenApi\Annotations as OA;

#[\Attribute(\Attribute::TARGET_METHOD)]
class Get extends OA\Get
{
    // trait?
    public function __toString(): string
    {
        return '['.$this::class . ': tags='.implode(',', $this->tags).', path='.$this->path.']';
    }
}
