<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

interface OpenApiAttributeInterface
{
    public function allowedParents(): ?array;

    public function getReflector(): ?\Reflector;

    public function setReflector(?\Reflector $reflector): static;

    public function getSourceLocation(): ?SourceLocation;

    public function setSourceLocation(?SourceLocation $sourceLocation): static;

    /** @return array<string,mixed>|null */
    public function getExtensions(): ?array;

    /** @return list<Attachable> */
    public function getAttachables(): array;
}
