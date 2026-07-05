<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
#[AllowedParents(MediaType::class)]
class Encoding extends AbstractAttribute
{
    /**
     * @param array<string,Header>|null $headers
     * @param array<string,mixed>|null  $x
     * @param list<object>|null         $attachables
     */
    public function __construct(
        public ?string $encoding = null,
        public ?string $contentType = null,
        public ?array $headers = null,
        public ?string $style = null,
        public ?bool $explode = null,
        public ?bool $allowReserved = null,
        ?array $x = null,
        array $attachables = [],
    ) {
        parent::__construct(attachables: $attachables, x: $x);
    }
}
