<?php declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
requireResolver();

use OpenApi\Builder;
use OpenApi\Builder\Mode;
use OpenApi\Tests\Fixtures\Resolver\ProductController;

// Use the project's own fixture, which is known to work via ResolverTest.
$wt = dirname(__DIR__, 3);
require_once $wt . '/tests/Fixtures/Resolver/ProductController.php';
require_once $wt . '/tests/Fixtures/Resolver/Product.php';
require_once $wt . '/tests/Fixtures/Resolver/Manufacturer.php';
require_once $wt . '/tests/Fixtures/Resolver/Weight.php';

foreach (['dir' => $wt . '/tests/Fixtures/Resolver', 'reflector' => new ReflectionClass(ProductController::class)] as $label => $source) {
    $result = (new Builder())->setMode(Mode::SPEC)->addSource($source)->build();
    $doc = json_decode($result->toJson(), true);
    printf("%-10s paths=%d schemas=%s\n", $label,
        count($doc['paths'] ?? []),
        implode(',', array_keys($doc['components']['schemas'] ?? [])) ?: '(none)');
}
