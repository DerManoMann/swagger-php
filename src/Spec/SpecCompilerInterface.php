<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

interface SpecCompilerInterface
{
    public function getVersion(): string;

    /**
     * Whether this compiler handles the given version string (including patch versions).
     */
    public function supports(string $version): bool;

    /**
     * Compile a Specification into a structured OpenAPI document array.
     *
     * @return array<string, mixed>
     */
    public function compile(Specification $specification): array;

    public function validate(Specification $specification): CompilerDiagnostics;
}
