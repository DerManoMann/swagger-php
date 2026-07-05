<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

class CompilerContext
{
    public function __construct(
        public readonly string $version,
        public readonly array $parentOutput,
        public readonly ?SourceLocation $location = null,
    ) {
    }
}
