<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApiTests\Scanners;

use OpenApi\Scanners\FileScanner;
use OpenApi\Util;
use OpenApiTests\OpenApiTestCase;

class FileScannerTest extends OpenApiTestCase
{
    public function testBasic()
    {
        $expected = [
            'classes' => [
                'OpenApiTests\\Fixtures\\Apis\\Api',
                'OpenApiTests\\Fixtures\\Apis\\Product',
                'OpenApiTests\\Fixtures\\Apis\\ProductController',
            ],
            'interfaces' => [
                'OpenApiTests\\Fixtures\\Apis\\ProductInterface',
            ],
            'traits' => [
                'OpenApiTests\\Fixtures\\Apis\\NameTrait',
            ],
        ];
        $result = (new FileScanner())->scan(Util::finder($this->fixtures('Apis/basic.php')));
        $this->assertEquals($expected, $result);
    }

    public function testPHP7()
    {
        $expected = [
            'classes' => [],
            'interfaces' => [],
            'traits' => [],
        ];
        $result = (new FileScanner())->scan(Util::finder($this->fixtures('StaticAnalyser/php7.php')));
        $this->assertEquals($expected, $result);
    }
}
