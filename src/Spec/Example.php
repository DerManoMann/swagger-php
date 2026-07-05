<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Example extends AbstractAttribute
{
    /**
     * @param array<string,mixed>|null $x
     * @param list<object>|null        $attachables
     */
    public function __construct(
        public ?string $example = null,
        public ?string $ref = null,
        public ?string $summary = null,
        public ?string $description = null,
        public mixed $value = null,
        public ?string $externalValue = null,
        ?array $x = null,
        array $attachables = [],
    ) {
        parent::__construct(attachables: $attachables, x: $x);
    }

    public function allowedParents(): ?array
    {
        return [Parameter::class, MediaType::class];
    }
}
