<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

interface CompilerExtension
{
    /**
     * Which Attachable class(es) this extension handles.
     *
     * @return list<class-string<Attachable>>
     */
    public function handles(): array;

    /**
     * Compile the Attachable into spec output.
     *
     * Returns key-value pairs merged into the parent's output object.
     *
     * @return array<string, mixed>
     */
    public function compile(object $attachable, CompilerContext $ctx): array;
}
