<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Attributes;

use OpenApi\Annotations as OA;

<<\Attribute(\Attribute::TARGET_CLASS)>>
class Schema extends OA\Schema
{
    // trait?
    public function __toString(): string
    {
        return '['.$this::class . ': title='.$this->title.', schema='.$this->schema.']';
    }
}
