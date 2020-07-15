<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Attributes;

use OpenApi\Annotations as OA;

<<\Attribute(\Attribute::TARGET_METHOD|\Attribute::IS_REPEATABLE)>>
class Response extends OA\Response
{
    // trait?
    public function __toString(): string
    {
        return '['.$this::class . ':  response='.$this->response.']';
    }
}
