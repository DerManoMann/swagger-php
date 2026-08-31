<?php declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

use OpenApi\Builder;
use OpenApi\Builder\Mode;

$outputDir = sys_get_temp_dir() . '/swagger-php-modebench';
if (!is_dir($outputDir)) {
    mkdir($outputDir . '/Models', 0755, true);
    mkdir($outputDir . '/Controllers', 0755, true);

    $schemaCount = 300;
    $usedCount = (int) round($schemaCount * 0.6);

    file_put_contents($outputDir . '/ApiInfo.php', <<<'P'
<?php declare(strict_types=1);
namespace SwaggerPhpModeBench;
use OpenApi\Attributes as OA;
#[OA\OpenApi(info: new OA\Info(title: 'Bench', version: '1.0.0'))]
class ApiInfo {}
P);

    for ($i = 0; $i < $schemaCount; $i++) {
        $props = "        new OA\Property(property: 'id', type: 'integer'),\n        new OA\Property(property: 'name', type: 'string'),";
        if ($i < $usedCount) {
            $ref = 'PerfSchema' . (($i + 1) % $usedCount);
            $props .= "\n        new OA\Property(property: 'related', ref: '#/components/schemas/{$ref}'),";
        }
        file_put_contents($outputDir . "/Models/PerfSchema{$i}.php", <<<P
<?php declare(strict_types=1);
namespace SwaggerPhpModeBench\Models;
use OpenApi\Attributes as OA;
#[OA\Schema(schema: 'PerfSchema{$i}', type: 'object', properties: [
{$props}
])]
class PerfSchema{$i} {}
P);
    }

    $perController = 10;
    for ($c = 0; $c < (int) ceil($usedCount / $perController); $c++) {
        $methods = [];
        for ($e = 0; $e < $perController; $e++) {
            $idx = $c * $perController + $e;
            if ($idx >= $usedCount) { break; }
            $methods[] = <<<P
    #[OA\Get(path: '/perf/{$idx}', operationId: 'getPerfSchema{$idx}', responses: [
        new OA\Response(response: 200, description: 'OK',
            content: new OA\JsonContent(ref: '#/components/schemas/PerfSchema{$idx}')),
    ])]
    public function getPerfSchema{$idx}(): void {}
P;
        }
        $m = implode("\n\n", $methods);
        file_put_contents($outputDir . "/Controllers/Controller{$c}.php", <<<P
<?php declare(strict_types=1);
namespace SwaggerPhpModeBench\Controllers;
use OpenApi\Attributes as OA;
class Controller{$c} {
{$m}
}
P);
    }
}

spl_autoload_register(function (string $class) use ($outputDir): void {
    $prefix = 'SwaggerPhpModeBench\\';
    if (str_starts_with($class, $prefix)) {
        $file = $outputDir . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (file_exists($file)) { require $file; }
    }
});

$run = fn (Mode $mode) => (new Builder())->setMode($mode)->addSource($outputDir)->build();

foreach ([Mode::CLASSIC, Mode::HYBRID] as $mode) { $run($mode); }   // warmup both

$results = [];
foreach ([Mode::CLASSIC, Mode::HYBRID] as $mode) {
    $times = [];
    for ($i = 0; $i < 5; $i++) {
        $start = microtime(true);
        $result = $run($mode);
        $times[] = (microtime(true) - $start) * 1000;
    }
    sort($times);
    $results[$mode->value] = ['median' => $times[2], 'min' => $times[0], 'paths' => count($result->toJson() ? json_decode($result->toJson(), true)['paths'] ?? [] : [])];
}

foreach ($results as $mode => $r) {
    printf("%-8s median %7.1fms   min %7.1fms   paths %d\n", $mode, $r['median'], $r['min'], $r['paths']);
}
printf("\nhybrid / classic = %.2fx\n", $results['hybrid']['median'] / $results['classic']['median']);
