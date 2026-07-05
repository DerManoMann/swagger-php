<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Property extends AbstractAttribute
{
    /**
     * @param array<string,mixed>|null $x
     * @param list<Attachable>         $attachables
     */
    public function __construct(
        public ?string $property = null,
        public ?Schema $schema = null,
        array $attachables = [],
        ?array $x = null,
    ) {
        parent::__construct(attachables: $attachables, x: $x);
    }
}
