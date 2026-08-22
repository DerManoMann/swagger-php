<?php

declare(strict_types=1);

namespace OpenApi;

interface ResolverInterface
{
    public function resolve(string $fqcn, Specification $specification): bool;
}
