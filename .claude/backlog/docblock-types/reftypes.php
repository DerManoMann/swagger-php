<?php declare(strict_types=1);

/**
 * Reproduction for PR 38 — a docblock type resolves differently from the native type.
 *
 * Three properties of the same class type, differing only in their docblock, run through all
 * three modes. Two of the nine cells are wrong, and they are wrong in different pipelines,
 * which is the point: classic gets `@var Target` right and spec does not.
 *
 *   XDEBUG_MODE=off php .claude/backlog/docblock-types/reftypes.php
 */

require dirname(__DIR__, 3) . '/vendor/autoload.php';

// The fixtures are not in composer's autoload map; both pipelines reflect on the classes,
// so they have to be loadable rather than merely scanned.
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'OpenApi\\Backlog\\DocblockTypes\\')) {
        return;
    }

    require_once str_ends_with($class, 'Spec') ? __DIR__ . '/Fixture-spec.php' : __DIR__ . '/Fixture.php';
});

use OpenApi\Builder;
use OpenApi\Builder\Mode;

$fixtures = [
    Mode::CLASSIC->value => __DIR__ . '/Fixture.php',
    Mode::HYBRID->value => __DIR__ . '/Fixture.php',
    Mode::SPEC->value => __DIR__ . '/Fixture-spec.php',
];

$properties = ['native', 'generic', 'docblock'];
$results = [];

foreach ($fixtures as $mode => $source) {
    $compiled = (new Builder())
        ->setMode($mode)
        ->setVersion('3.1.0')
        ->addSource($source)
        ->build()
        ->toArray();

    $schema = (array) ($compiled['components']['schemas']['Holder']['properties'] ?? []);

    foreach ($properties as $property) {
        $value = (array) ($schema[$property] ?? []);
        $ref = $value['$ref'] ?? null;

        $results[$property][$mode] = match (true) {
            $ref === null && $value === [] => '{} nothing',
            $ref === null => 'no $ref',
            str_starts_with($ref, '#/components/') => 'resolves',
            default => $ref,
        };
    }
}

$modes = array_keys($fixtures);

printf("%-10s  %-14s  %-14s  %-14s\n", 'property', ...$modes);
printf("%-10s  %-14s  %-14s  %-14s\n", str_repeat('-', 10), ...array_fill(0, 3, str_repeat('-', 14)));

foreach ($properties as $property) {
    printf("%-10s  %-14s  %-14s  %-14s\n", $property, ...array_map(
        fn (string $mode): string => $results[$property][$mode],
        $modes,
    ));
}

echo "\nEvery cell should read \"resolves\". Two do not, and only one of the two is spec-only.\n";
