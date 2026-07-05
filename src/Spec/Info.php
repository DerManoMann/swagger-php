<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Info extends AbstractAttribute
{
    /**
     * @param array<string,mixed>|null $x
     * @param list<Attachable>         $attachables
     */
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?string $version = null,
        public ?string $termsOfService = null,
        public ?Contact $contact = null,
        public ?License $license = null,
        ?array $x = null,
        array $attachables = [],
    ) {
        parent::__construct(attachables: $attachables, x: $x);
    }

    public function allowedParents(): ?array
    {
        return [];
    }
}
