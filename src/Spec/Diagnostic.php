<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

class Diagnostic
{
    public function __construct(
        public readonly string $message,
        public readonly ?SourceLocation $location = null,
        public readonly ?string $path = null,
    ) {
    }
}
