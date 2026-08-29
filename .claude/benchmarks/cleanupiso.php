<?php declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/gensrc.php';

use OpenApi\Analysis;
use OpenApi\Context;
use OpenApi\Generator;
use OpenApi\Processors\CleanUnusedComponents;
use OpenApi\Utils\SourceFinder;

$sizes = [200, 400, 800, 1600, 3200];

echo "CLASSIC  CleanUnusedComponents (processor timed in isolation)\n";
printf("%-10s%-12s%-12s%s\n", 'schemas', 'ms', 'us/schema', 'growth');
$prev = null;
foreach ($sizes as $n) {
    $dir = genClassicSource($n);
    registerAutoload($dir, $n);

    $buildAnalysis = function () use ($dir): Analysis {
        $generator = new Generator();
        $pipeline = $generator->getProcessorPipeline();
        $pipeline->remove(CleanUnusedComponents::class);
        $generator->setProcessorPipeline($pipeline);
        $analysis = new Analysis([], new Context(['version' => $generator->getVersion()]));
        $generator->generate(new SourceFinder($dir), $analysis, validate: false);
        return $analysis;
    };

    $buildAnalysis();   // warmup (autoload + opcache)
    $t = [];
    for ($r = 0; $r < 3; $r++) {
        $analysis = $buildAnalysis();
        $proc = new CleanUnusedComponents(true);
        $start = microtime(true);
        $proc($analysis);
        $t[] = (microtime(true) - $start) * 1000;
    }
    sort($t); $ms = $t[1];
    printf("%-10d%-12.2f%-12.2f%s\n", $n, $ms, $ms * 1000 / $n, $prev ? sprintf('%.2fx for 2x n', $ms / $prev) : '');
    $prev = $ms;
}
