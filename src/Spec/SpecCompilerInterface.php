<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

interface SpecCompilerInterface
{
    public function getVersion(): string;

    /**
     * Compile a Specification into a structured OpenAPI document array.
     *
     * @return array<string, mixed>
     */
    public function compile(Specification $specification): array;

    public function validate(Specification $specification): CompilerDiagnostics;
}
