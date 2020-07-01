<?php

namespace OpenApiTests\Fixtures\StaticAnalyser;

use OpenApi\Analysers\ReflectionAnalyser;
use OpenApi\Analysis;
use OpenApi\Annotations\OpenApi;
use OpenApi\Scanners\AutoloaderScanner;
use OpenApi\Scanners\FileScanner;
use OpenApi\Util;

require './vendor/autoload.php';

/**
 * Analyser using reflection ......
 * - how to scan?
 *   * by namespace?
 *   * what is in a file?  * class/interface/trait?
 *   * what is the name?
 *   * multiple entities per file, non matching names...
 *
 * - attributes are always attached to code, so no attribute only files...
 * - interogate registered classloader to find composer class loader to access class
 *   for namespace scanning
 * - parse files just for class/interface/trait detection, then use reflection
 * - DockBlockAnalyser vs. Scanner that provides class/etc + docblock details (either the current static
 *   or reflection)
 * - intermediate level between analyser and analysis
 * - rename a few things :)
 * - warning on unattached dockblocks?
 * - attributes as wrapper around annotations or standalone?
 * - limited context without line info
 *
 * - testing: minimal  single file api to compare; PoC reflection analyser + abstraction (/docblock/attributes)
 */

$filename = __DIR__ . '/../Apis/basic.php';

echo \OpenApi\scan($filename)->toYaml();
return;
$scanner = new FileScanner();
$units = $scanner->scan( Util::finder($filename));
var_dump($units);


//$scanner = new AutoloaderScanner();
//$units = [
    //'mixed' => $scanner->scan(['OpenApiTests\\Fixtures\\Apis']),
//];
//var_dump($units);


//return;

// test api is not psr-4 compatible...
//require_once $filename;

ECHO '==================================================================================='.PHP_EOL;
$ra = new ReflectionAnalyser();
foreach ($units as $type => $tunits) {
    foreach ($tunits as $tn) {
        $analysis = $ra->analyse($tn);
    }
}

$analysis->process();
$analysis->validate();
echo $analysis->openapi->toYaml();
