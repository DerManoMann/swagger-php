<?php declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
requireResolver();

use OpenApi\Assembler;
use OpenApi\Builder;
use OpenApi\Builder\Mode;
use OpenApi\Contracts\ResolverInterface;
use OpenApi\Resolver;
use OpenApi\Resolver\Reflection;
use OpenApi\Tests\Fixtures\Resolver\ProductController;
use OpenApi\Utils\TypedList;

$wt = dirname(__DIR__, 2);
foreach (['ProductController', 'Product', 'Manufacturer', 'Weight'] as $c) {
    require_once $wt . "/tests/Fixtures/Resolver/{$c}.php";
}

$tracer = new class implements ResolverInterface {
    public array $seen = [];
    private Reflection $inner;
    public function __construct() { $this->inner = new Reflection(); }
    public function resolve(string $fqcn, Assembler $assembler): bool {
        $ok = $this->inner->resolve($fqcn, $assembler);
        $this->seen[] = $fqcn . ($ok ? ' [resolved]' : ' [failed]');
        return $ok;
    }
};

$result = (new Builder())
    ->setMode(Mode::SPEC)
    ->addSource(new ReflectionClass(ProductController::class))
    ->withResolver(fn (Resolver $r) => $r->setResolvers(new TypedList([$tracer])))
    ->build();

echo "resolver saw:\n  " . (implode("\n  ", $tracer->seen) ?: '(nothing)') . "\n";
$doc = json_decode($result->toJson(), true);
echo "schemas: " . (implode(',', array_keys($doc['components']['schemas'] ?? [])) ?: '(none)') . "\n";
