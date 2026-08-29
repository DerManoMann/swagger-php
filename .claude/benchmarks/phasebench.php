<?php declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
requireResolver();

use OpenApi\Assembler;
use OpenApi\Builder;
use OpenApi\Resolver;
use OpenApi\Utils\SourceScanner;
use Psr\Log\NullLogger;

// Same fixture generator as noisebench, no non-API noise: every file is API.
function gen(int $api, int $noise): array {
    $dir = sys_get_temp_dir() . '/sp-phase-' . $api . '-' . $noise;
    $ns = 'SpPhase' . $api . 'x' . $noise;
    if (!is_dir($dir)) {
        mkdir($dir . '/Models', 0755, true); mkdir($dir . '/Controllers', 0755, true); mkdir($dir . '/App', 0755, true);
        file_put_contents($dir . '/ApiInfo.php', "<?php declare(strict_types=1);\nnamespace {$ns};\nuse OpenApi\\Spec as OA;\n#[OA\\Info(title: 'B', version: '1.0.0')]\nclass ApiInfo {}\n");
        for ($i = 0; $i < $api; $i++) {
            file_put_contents($dir . "/Models/S{$i}.php", "<?php declare(strict_types=1);\nnamespace {$ns}\\Models;\nuse OpenApi\\Spec as OA;\n#[OA\\Schema(schema: 'S{$i}')]\nclass S{$i} {\n    #[OA\\Property(property: 'id')]\n    public int \$id;\n    #[OA\\Property(property: 'name')]\n    public string \$name;\n}\n");
        }
        $cc = (int) ceil($api / 10);
        for ($c = 0; $c < $cc; $c++) {
            $m = [];
            for ($e = 0; $e < 10; $e++) {
                $idx = $c * 10 + $e;
                if ($idx >= $api) { break; }
                $m[] = "    #[OA\\Operation\\Get(path: '/p/{$idx}', operationId: 'get{$idx}')]\n    #[OA\\Response(response: 200, description: 'OK', content: [\n        new OA\\MediaType(mediaType: 'application/json', schema: new OA\\Schema(ref: \\{$ns}\\Models\\S{$idx}::class)),\n    ])]\n    public function get{$idx}() {}";
            }
            file_put_contents($dir . "/Controllers/C{$c}.php", "<?php declare(strict_types=1);\nnamespace {$ns}\\Controllers;\nuse OpenApi\\Spec as OA;\nclass C{$c} {\n" . implode("\n\n", $m) . "\n}\n");
        }
        for ($i = 0; $i < $noise; $i++) {
            file_put_contents($dir . "/App/N{$i}.php", "<?php declare(strict_types=1);\nnamespace {$ns}\\App;\nclass N{$i} {\n    private array \$state = [];\n    public function handle(string \$a, int \$b): string { return \$a . \$b; }\n}\n");
        }
    }
    spl_autoload_register(function (string $cls) use ($dir, $ns): void {
        if (str_starts_with($cls, $ns . '\\')) {
            $f = $dir . '/' . str_replace('\\', '/', substr($cls, strlen($ns) + 1)) . '.php';
            if (file_exists($f)) { require $f; }
        }
    });
    return [$dir, $ns, (int) ceil($api / 10)];
}

function phases(string $mode, string $dir, string $ns, int $cc): array {
    $b = new Builder();
    $rc = new ReflectionClass($b);
    $af = $rc->getMethod('getAttributeFactory'); $af->setAccessible(true);
    $attributeFactory = $af->invoke($b);
    $assembler = new Assembler(attributeFactory: $attributeFactory);

    $sources = $mode === 'scan'
        ? [$dir]
        : array_merge([new ReflectionClass($ns . '\\ApiInfo')],
            array_map(fn ($c) => new ReflectionClass($ns . '\\Controllers\\C' . $c), range(0, $cc - 1)));

    $t = [];
    $s = microtime(true);
    $scanner = new SourceScanner(new NullLogger());
    $scanner->scan($sources);
    $t['scan'] = (microtime(true) - $s) * 1000;

    $tokenScanner = $attributeFactory->getTokenScanner();
    $s = microtime(true);
    foreach ($scanner->getFiles() as $file) {
        foreach (array_keys($tokenScanner->scanFile($file)) as $class) {
            if (class_exists($class) || interface_exists($class) || enum_exists($class) || trait_exists($class)) {
                $assembler->collect(new \ReflectionClass($class));
            }
        }
    }
    foreach ($scanner->getReflectors() as $reflector) {
        if ($reflector instanceof \ReflectionClass) { $assembler->collect($reflector); }
    }
    $t['tokenize+collect'] = (microtime(true) - $s) * 1000;

    $spec = $assembler->getSpecification();

    $s = microtime(true);
    (new Resolver())->resolve($assembler);
    $t['resolve'] = (microtime(true) - $s) * 1000;

    $s = microtime(true);
    $ag = $rc->getMethod('getAugmenters'); $ag->setAccessible(true);
    $ag->invoke($b)->process($spec);
    $t['augment'] = (microtime(true) - $s) * 1000;

    $t['schemas'] = count($spec->schemas);
    return $t;
}

foreach ([[100, 0], [400, 0], [400, 1600]] as [$api, $noise]) {
    [$dir, $ns, $cc] = gen($api, $noise);
    phases('scan', $dir, $ns, $cc); phases('reflector', $dir, $ns, $cc);   // warmup

    echo "\n=== {$api} API schemas, {$noise} non-API files ===\n";
    printf("%-12s%-10s%-20s%-12s%-12s%s\n", 'mode', 'scan', 'tokenize+collect', 'resolve', 'augment', 'schemas');
    foreach (['scan', 'reflector'] as $mode) {
        $runs = [];
        for ($i = 0; $i < 3; $i++) { $runs[] = phases($mode, $dir, $ns, $cc); }
        usort($runs, fn ($a, $b) => ($a['scan'] + $a['tokenize+collect'] + $a['resolve'] + $a['augment'])
                                <=> ($b['scan'] + $b['tokenize+collect'] + $b['resolve'] + $b['augment']));
        $r = $runs[1];
        printf("%-12s%-10.1f%-20.1f%-12.1f%-12.1f%d\n", $mode, $r['scan'], $r['tokenize+collect'], $r['resolve'], $r['augment'], $r['schemas']);
    }
}
