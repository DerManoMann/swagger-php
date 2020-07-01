<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApiTests\Analysers;

use OpenApi\Scanners\FileScanner;
use OpenApi\Util;
use OpenApiTests\OpenApiTestCase;

class ReflectionAnalyserTest extends OpenApiTestCase
{
    public function testPHP7()
    {
        $scanner = new FileScanner();
        $units = $scanner->scan( Util::finder($this->fixtures('StaticAnalyser/php7.php')));

    }
}
