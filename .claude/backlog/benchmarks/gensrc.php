<?php declare(strict_types=1);

function genClassicSource(int $count): string {
    $dir = sys_get_temp_dir() . '/sp-scale-' . $count;
    if (is_dir($dir)) { return $dir; }
    mkdir($dir . '/Models', 0755, true); mkdir($dir . '/Controllers', 0755, true);
    $used = (int) round($count * 0.6);
    $ns = 'SpScale' . $count;
    file_put_contents($dir . '/ApiInfo.php', "<?php declare(strict_types=1);\nnamespace {$ns};\nuse OpenApi\\Attributes as OA;\n#[OA\\OpenApi(info: new OA\\Info(title: 'B', version: '1.0.0'))]\nclass ApiInfo {}\n");
    for ($i = 0; $i < $count; $i++) {
        $p = "        new OA\\Property(property: 'id', type: 'integer'),\n        new OA\\Property(property: 'name', type: 'string'),";
        if ($i < $used) { $p .= "\n        new OA\\Property(property: 'related', ref: '#/components/schemas/S" . (($i + 1) % $used) . "'),"; }
        file_put_contents($dir . "/Models/S{$i}.php", "<?php declare(strict_types=1);\nnamespace {$ns}\\Models;\nuse OpenApi\\Attributes as OA;\n#[OA\\Schema(schema: 'S{$i}', type: 'object', properties: [\n{$p}\n])]\nclass S{$i} {}\n");
    }
    for ($c = 0; $c < (int) ceil($used / 10); $c++) {
        $m = [];
        for ($e = 0; $e < 10; $e++) {
            $idx = $c * 10 + $e;
            if ($idx >= $used) { break; }
            $m[] = "    #[OA\\Get(path: '/p/{$idx}', operationId: 'get{$idx}', responses: [\n        new OA\\Response(response: 200, description: 'OK', content: new OA\\JsonContent(ref: '#/components/schemas/S{$idx}')),\n    ])]\n    public function get{$idx}(): void {}";
        }
        file_put_contents($dir . "/Controllers/C{$c}.php", "<?php declare(strict_types=1);\nnamespace {$ns}\\Controllers;\nuse OpenApi\\Attributes as OA;\nclass C{$c} {\n" . implode("\n\n", $m) . "\n}\n");
    }
    return $dir;
}

function registerAutoload(string $dir, int $n): void {
    spl_autoload_register(function (string $cls) use ($dir, $n): void {
        $prefix = 'SpScale' . $n . '\\';
        if (str_starts_with($cls, $prefix)) {
            $f = $dir . '/' . str_replace('\\', '/', substr($cls, strlen($prefix))) . '.php';
            if (file_exists($f)) { require $f; }
        }
    });
}
