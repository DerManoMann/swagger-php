<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Scanners;

use OpenApi\Scanners\FileScanner;
use OpenApi\Tests\OpenApiTestCase;
use OpenApi\Util;

class FileScannerTest extends OpenApiTestCase
{
    public function testBasic()
    {
        $fixtures = $this->fixtures('Apis/basic.php');
        $expected = [
            'classes' => [
                'OpenApi\\Tests\\Fixtures\\Apis\\Api' => $fixtures[0],
                'OpenApi\\Tests\\Fixtures\\Apis\\Product' => $fixtures[0],
                'OpenApi\\Tests\\Fixtures\\Apis\\ProductController' => $fixtures[0],
            ],
            'interfaces' => [
                'OpenApi\\Tests\\Fixtures\\Apis\\ProductInterface' => $fixtures[0],
            ],
            'traits' => [
                'OpenApi\\Tests\\Fixtures\\Apis\\NameTrait' => $fixtures[0],
            ],
        ];
        $result = (new FileScanner())->scan(Util::finder($fixtures));
        $this->assertEquals($expected, $result);
    }

    public function testSyntaxPhp7()
    {
        $expected = [
            'classes' => [],
            'interfaces' => [],
            'traits' => [],
        ];
        $result = (new FileScanner())->scan(Util::finder($this->fixtures('Analysers/php7.php')));
        $this->assertEquals($expected, $result);
    }
}
