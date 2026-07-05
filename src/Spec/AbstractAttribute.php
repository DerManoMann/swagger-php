<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

abstract class AbstractAttribute implements OpenApiAttributeInterface
{
    /** @internal */
    public readonly ?SourceLocation $sourceLocation;

    /** @internal */
    public readonly ?\Reflector $reflector;

    /**
     * @param list<Attachable> $attachables
     * @param array<string,mixed>|null $x
     */
    public function __construct(
        public array $attachables = [],
        public ?array $x = null,
    ) {
    }
}
