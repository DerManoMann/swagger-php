<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

/**
 * With PHP 8.4+ property hooks this interface would become:
 *
 *     interface OpenApiAttributeInterface
 *     {
 *         public ?array $x { get; }
 *         public array $attachables { get; }
 *         public ?\Reflector $reflector { get; set; }
 *         public ?SourceLocation $sourceLocation { get; set; }
 *
 *         public function allowedParents(): ?array;
 *     }
 */
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
