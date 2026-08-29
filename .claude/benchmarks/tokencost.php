<?php declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/phasegen.php';

use OpenApi\Utils\SourceScanner;
use OpenApi\Utils\TokenScanner;
use Psr\Log\NullLogger;

printf("%-10s%-20s%-14s%s\n", 'files', 'tokenize all ms', 'us/file', 'fixture');
foreach ([[100, 0], [400, 0], [400, 1600]] as [$api, $noise]) {
    [$dir, $ns, $cc] = gen($api, $noise);

    $scanner = new SourceScanner(new NullLogger());
    $scanner->scan([$dir]);
    $files = iterator_to_array($scanner->getFiles());
    $n = count($files);

    // prime the OS page cache without priming the TokenScanner cache
    foreach ($files as $f) { file_get_contents((string) $f); }

    $t = [];
    for ($r = 0; $r < 3; $r++) {
        $ts = new TokenScanner();          // fresh: no memoised results
        $s = microtime(true);
        foreach ($files as $f) { $ts->scanFile($f); }
        $t[] = (microtime(true) - $s) * 1000;
    }
    sort($t);
    printf("%-10d%-20.1f%-14.1f%s\n", $n, $t[1], $t[1] * 1000 / $n, "{$api} API + {$noise} non-API");
}
