<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

abstract class AbstractAttribute implements OpenApiAttributeInterface
{
    /** @internal */
    public ?SourceLocation $sourceLocation = null;

    /** @internal */
    public ?\Reflector $reflector = null;

    /**
     * @param list<Attachable> $attachables
     * @param array<string,mixed>|null $x
     */
    public function __construct(
        public array $attachables = [],
        public ?array $x = null,
    ) {
    }

    /**
     * @return list<class-string<AbstractAttribute>>|null null = unrestricted
     */
    public function allowedParents(): ?array
    {
        return null;
    }
}
