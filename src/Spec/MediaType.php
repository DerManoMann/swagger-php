<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class MediaType extends AbstractAttribute
{
    /**
     * @param array<string,Encoding>|null $encoding
     * @param array<string,mixed>|null    $x
     * @param list<object>|null           $attachables
     */
    public function __construct(
        public ?string $mediaType = null,
        public ?Schema $schema = null,
        public mixed $example = null,
        public ?array $examples = null,
        public ?array $encoding = null,
        ?array $x = null,
        array $attachables = [],
    ) {
        parent::__construct(attachables: $attachables, x: $x);
    }

    public function allowedParents(): ?array
    {
        return [Response::class, RequestBody::class];
    }
}
