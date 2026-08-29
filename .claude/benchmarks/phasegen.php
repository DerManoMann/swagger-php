<?php declare(strict_types=1);
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
