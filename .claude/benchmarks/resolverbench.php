<?php declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
requireResolver();

use OpenApi\Builder;
use OpenApi\Builder\Mode;

function genSpecSource(int $count): array {
    $dir = sys_get_temp_dir() . '/sp-res-' . $count;
    $ns = 'SpRes' . $count;
    $used = (int) round($count * 0.6);
    $controllerCount = (int) ceil($used / 10);

    if (!is_dir($dir)) {
        mkdir($dir . '/Models', 0755, true);
        mkdir($dir . '/Controllers', 0755, true);
        file_put_contents($dir . '/ApiInfo.php',
            "<?php declare(strict_types=1);\nnamespace {$ns};\nuse OpenApi\\Spec as OA;\n#[OA\\Info(title: 'B', version: '1.0.0')]\nclass ApiInfo {}\n");

        for ($i = 0; $i < $count; $i++) {
            // each used model also points at the next used model via a typed property
            $extra = '';
            if ($i < $used) {
                $next = 'S' . (($i + 1) % $used);
                $extra = "\n    #[OA\\Property(property: 'related')]\n    public {$next} \$related;\n";
            }
            file_put_contents($dir . "/Models/S{$i}.php",
                "<?php declare(strict_types=1);\nnamespace {$ns}\\Models;\nuse OpenApi\\Spec as OA;\n#[OA\\Schema(schema: 'S{$i}')]\nclass S{$i} {\n    #[OA\\Property(property: 'id')]\n    public int \$id;\n    #[OA\\Property(property: 'name')]\n    public string \$name;\n{$extra}}\n");
        }

        for ($c = 0; $c < $controllerCount; $c++) {
            $m = [];
            for ($e = 0; $e < 10; $e++) {
                $idx = $c * 10 + $e;
                if ($idx >= $used) { break; }
                $m[] = "    #[OA\\Operation\\Get(path: '/p/{$idx}', operationId: 'get{$idx}')]\n"
                     . "    #[OA\\Response(response: 200, description: 'OK', content: [\n"
                     . "        new OA\\MediaType(mediaType: 'application/json', schema: new OA\\Schema(ref: \\{$ns}\\Models\\S{$idx}::class)),\n"
                     . "    ])]\n    public function get{$idx}() {}";
            }
            file_put_contents($dir . "/Controllers/C{$c}.php",
                "<?php declare(strict_types=1);\nnamespace {$ns}\\Controllers;\nuse OpenApi\\Spec as OA;\nclass C{$c} {\n" . implode("\n\n", $m) . "\n}\n");
        }
    }

    spl_autoload_register(function (string $cls) use ($dir, $ns): void {
        if (str_starts_with($cls, $ns . '\\')) {
            $f = $dir . '/' . str_replace('\\', '/', substr($cls, strlen($ns) + 1)) . '.php';
            if (file_exists($f)) { require $f; }
        }
    });

    return [$dir, $ns, $controllerCount];
}

$sizes = [200, 400, 800, 1600];

printf("%-10s%-14s%-14s%-10s%s\n", 'models', 'scan dir ms', 'reflectors ms', 'speedup', 'paths/schemas match');
foreach ($sizes as $n) {
    [$dir, $ns, $controllerCount] = genSpecSource($n);

    // "the app already knows its controllers" — no discovery cost attributed to either side
    $reflectors = [];
    for ($c = 0; $c < $controllerCount; $c++) {
        $reflectors[] = new \ReflectionClass($ns . '\\Controllers\\C' . $c);
    }
    $infoReflector = new \ReflectionClass($ns . '\\ApiInfo');

    $byScan = fn () => (new Builder())->setMode(Mode::SPEC)->addSource($dir)->build();
    $byRefl = function () use ($reflectors, $infoReflector) {
        $b = (new Builder())->setMode(Mode::SPEC)->addSource($infoReflector);
        foreach ($reflectors as $r) { $b->addSource($r); }
        return $b->build();
    };

    $byScan(); $byRefl();   // warmup

    $ts = []; $tr = [];
    for ($i = 0; $i < 3; $i++) {
        $s = microtime(true); $rs = $byScan(); $ts[] = (microtime(true) - $s) * 1000;
        $s = microtime(true); $rr = $byRefl(); $tr[] = (microtime(true) - $s) * 1000;
    }
    sort($ts); sort($tr);

    $a = json_decode($rs->toJson(), true);
    $b = json_decode($rr->toJson(), true);
    $match = sprintf('%d/%d vs %d/%d %s',
        count($a['paths'] ?? []), count($a['components']['schemas'] ?? []),
        count($b['paths'] ?? []), count($b['components']['schemas'] ?? []),
        ($a['paths'] ?? null) == ($b['paths'] ?? null) && ($a['components'] ?? null) == ($b['components'] ?? null) ? 'IDENTICAL' : 'DIFFER');

    printf("%-10d%-14.1f%-14.1f%-10.2fx%s\n", $n, $ts[1], $tr[1], $ts[1] / $tr[1], $match);
}
