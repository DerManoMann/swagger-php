<?php declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
requireResolver();

use OpenApi\Builder;
use OpenApi\Builder\Mode;

// Fixed API surface: 100 models, 10 controllers. Growing amount of ordinary,
// non-API application code in the same tree.
function gen(int $noise): array {
    $api = 100;
    $dir = sys_get_temp_dir() . '/sp-noise-' . $noise;
    $ns = 'SpNoise' . $noise;

    if (!is_dir($dir)) {
        mkdir($dir . '/Models', 0755, true);
        mkdir($dir . '/Controllers', 0755, true);
        mkdir($dir . '/App', 0755, true);
        file_put_contents($dir . '/ApiInfo.php',
            "<?php declare(strict_types=1);\nnamespace {$ns};\nuse OpenApi\\Spec as OA;\n#[OA\\Info(title: 'B', version: '1.0.0')]\nclass ApiInfo {}\n");

        for ($i = 0; $i < $api; $i++) {
            file_put_contents($dir . "/Models/S{$i}.php",
                "<?php declare(strict_types=1);\nnamespace {$ns}\\Models;\nuse OpenApi\\Spec as OA;\n#[OA\\Schema(schema: 'S{$i}')]\nclass S{$i} {\n    #[OA\\Property(property: 'id')]\n    public int \$id;\n    #[OA\\Property(property: 'name')]\n    public string \$name;\n}\n");
        }
        for ($c = 0; $c < 10; $c++) {
            $m = [];
            for ($e = 0; $e < 10; $e++) {
                $idx = $c * 10 + $e;
                $m[] = "    #[OA\\Operation\\Get(path: '/p/{$idx}', operationId: 'get{$idx}')]\n"
                     . "    #[OA\\Response(response: 200, description: 'OK', content: [\n"
                     . "        new OA\\MediaType(mediaType: 'application/json', schema: new OA\\Schema(ref: \\{$ns}\\Models\\S{$idx}::class)),\n"
                     . "    ])]\n    public function get{$idx}() {}";
            }
            file_put_contents($dir . "/Controllers/C{$c}.php",
                "<?php declare(strict_types=1);\nnamespace {$ns}\\Controllers;\nuse OpenApi\\Spec as OA;\nclass C{$c} {\n" . implode("\n\n", $m) . "\n}\n");
        }
        // ordinary application code — services, jobs, whatever; no OpenAPI attributes
        for ($i = 0; $i < $noise; $i++) {
            file_put_contents($dir . "/App/N{$i}.php",
                "<?php declare(strict_types=1);\nnamespace {$ns}\\App;\nclass N{$i} {\n    private array \$state = [];\n    public function handle(string \$a, int \$b): string { return \$a . \$b; }\n    public function other(): void {}\n}\n");
        }
    }

    spl_autoload_register(function (string $cls) use ($dir, $ns): void {
        if (str_starts_with($cls, $ns . '\\')) {
            $f = $dir . '/' . str_replace('\\', '/', substr($cls, strlen($ns) + 1)) . '.php';
            if (file_exists($f)) { require $f; }
        }
    });

    return [$dir, $ns];
}

printf("%-16s%-14s%-14s%-10s%s\n", 'non-API files', 'scan dir ms', 'reflectors ms', 'speedup', 'output');
foreach ([0, 200, 800, 3200] as $noise) {
    [$dir, $ns] = gen($noise);

    $reflectors = [new \ReflectionClass($ns . '\\ApiInfo')];
    for ($c = 0; $c < 10; $c++) { $reflectors[] = new \ReflectionClass($ns . '\\Controllers\\C' . $c); }

    $byScan = fn () => (new Builder())->setMode(Mode::SPEC)->addSource($dir)->build();
    $byRefl = function () use ($reflectors) {
        $b = (new Builder())->setMode(Mode::SPEC);
        foreach ($reflectors as $r) { $b->addSource($r); }
        return $b->build();
    };
    $byScan(); $byRefl();

    $ts = []; $tr = [];
    for ($i = 0; $i < 3; $i++) {
        $s = microtime(true); $rs = $byScan(); $ts[] = (microtime(true) - $s) * 1000;
        $s = microtime(true); $rr = $byRefl(); $tr[] = (microtime(true) - $s) * 1000;
    }
    sort($ts); sort($tr);
    $a = json_decode($rs->toJson(), true); $b = json_decode($rr->toJson(), true);
    printf("%-16d%-14.1f%-14.1f%-10.2fx%s\n", $noise, $ts[1], $tr[1], $ts[1] / $tr[1],
        $a == $b ? 'IDENTICAL' : 'DIFFER');
}
