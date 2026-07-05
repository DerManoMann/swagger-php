<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

#[\Attribute(\Attribute::TARGET_CLASS)]
class OpenApi implements OpenApiAttributeInterface
{
    public function __construct(
        public ?string $version = null,
    ) {
    }
}
