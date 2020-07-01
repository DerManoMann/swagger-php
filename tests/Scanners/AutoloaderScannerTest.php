<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApiTests\Scanners;

use Composer\Autoload\ClassLoader;
use OpenApi\Scanners\AutoloaderScanner;
use OpenApiTests\OpenApiTestCase;

class AutoloaderScannerTest extends OpenApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $classMap = [
            'OpenApiTests\\Scanners\\Foo' => __FILE__,
            'OpenApiTests\\Scanners\\Bar' => __FILE__,
            'Other\\Duh' => __FILE__,
        ];
        $mockClassloader  = new ClassLoader();
        $mockClassloader->addClassMap($classMap);
        spl_autoload_register(array($mockClassloader, 'findFile'), true, true);
    }

    public function testComposerClassloader()
    {
        $expected = [
            'OpenApiTests\\Scanners\\Foo',
            'OpenApiTests\\Scanners\\Bar',
        ];
        $result = (new AutoloaderScanner())->scan('OpenApiTests');
        $this->assertEquals($expected, $result);
    }
}
