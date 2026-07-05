<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

class CompilerDiagnostics
{
    /** @var list<Diagnostic> */
    public array $errors = [];

    /** @var list<Diagnostic> */
    public array $warnings = [];

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function isValid(): bool
    {
        return !$this->hasErrors();
    }
}
