<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
#[AllowedParents]
class Tag extends AbstractAttribute
{
    /**
     * @param array<string,mixed>|null $x
     * @param list<Attachable>         $attachables
     */
    public function __construct(
        public ?string $name = null,
        public ?string $description = null,
        public ?ExternalDocumentation $externalDocs = null,
        ?array $x = null,
        array $attachables = [],
    ) {
        parent::__construct(attachables: $attachables, x: $x);
    }
}
