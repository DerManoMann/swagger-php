<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

abstract class AbstractAttribute implements OpenApiAttributeInterface
{
    protected ?SourceLocation $sourceLocation = null;

    protected ?\Reflector $reflector = null;

    /**
     * @param list<Attachable>         $attachables
     * @param array<string,mixed>|null $x
     */
    public function __construct(
        public array $attachables = [],
        public ?array $x = null,
    ) {
    }

    public function allowedParents(): ?array
    {
        return null;
    }

    public function getReflector(): ?\Reflector
    {
        return $this->reflector;
    }

    public function setReflector(?\Reflector $reflector): static
    {
        $this->reflector = $reflector;

        return $this;
    }

    public function getSourceLocation(): ?SourceLocation
    {
        return $this->sourceLocation;
    }

    public function setSourceLocation(?SourceLocation $sourceLocation): static
    {
        $this->sourceLocation = $sourceLocation;

        return $this;
    }

    public function getExtensions(): ?array
    {
        return $this->x;
    }

    public function getAttachables(): array
    {
        return $this->attachables;
    }
}
