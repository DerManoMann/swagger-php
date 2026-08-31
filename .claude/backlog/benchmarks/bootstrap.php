<?php declare(strict_types=1);

/**
 * Shared bootstrap for the benchmark scripts.
 *
 * They run against whatever is checked out, so a benchmark that needs the resolver needs a
 * branch that has it.
 */
require dirname(__DIR__, 3) . '/vendor/autoload.php';

function requireResolver(): void
{
    if (!class_exists(\OpenApi\Resolver::class)) {
        fwrite(STDERR, "This benchmark needs OpenApi\\Resolver, which is not on the checked out branch.\n"
            . "Check out the branch that introduces the resolver step (#2130) and run it again.\n");
        exit(1);
    }
}
