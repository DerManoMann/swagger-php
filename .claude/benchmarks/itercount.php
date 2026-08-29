<?php declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
requireResolver();

use OpenApi\Assembler;
use OpenApi\Resolver;
use OpenApi\Specification;

class CountingResolver extends Resolver {
    public int $iterations = 0;
    public int $unresolvedSeen = 0;
    public float $findUnresolvedMs = 0.0;
    protected function findUnresolved(Specification $specification): array {
        $this->iterations++;
        $s = microtime(true);
        $r = parent::findUnresolved($specification);
        $this->findUnresolvedMs += (microtime(true) - $s) * 1000;
        $this->unresolvedSeen += count($r);
        return $r;
    }
}

require __DIR__ . '/phasegen.php';

printf("%-10s%-14s%-20s%-22s%s\n", 'schemas', 'iterations', 'findUnresolved ms', 'total unresolved seen', 'resolve ms');
foreach ([100, 200, 400, 800] as $api) {
    [$dir, $ns, $cc] = gen($api, 0);
    $b = new OpenApi\Builder();
    $rc = new ReflectionClass($b);
    $af = $rc->getMethod('getAttributeFactory'); $af->setAccessible(true);
    $assembler = new Assembler(attributeFactory: $af->invoke($b));
    $assembler->collect(new ReflectionClass($ns . '\\ApiInfo'));
    for ($c = 0; $c < $cc; $c++) { $assembler->collect(new ReflectionClass($ns . '\\Controllers\\C' . $c)); }

    $r = new CountingResolver();
    $s = microtime(true);
    $r->resolve($assembler);
    $ms = (microtime(true) - $s) * 1000;
    printf("%-10d%-14d%-20.1f%-22d%.1f\n", $api, $r->iterations, $r->findUnresolvedMs, $r->unresolvedSeen, $ms);
}
