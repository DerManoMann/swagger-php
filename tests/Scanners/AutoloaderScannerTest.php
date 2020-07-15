<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Scanners;

use Composer\Autoload\ClassLoader;
use OpenApi\Scanners\AutoloaderScanner;
use OpenApi\Tests\OpenApiTestCase;

class AutoloaderScannerTest extends OpenApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $classMap = [
            'OpenApi\Tests\\Scanners\\Foo' => __FILE__,
            'OpenApi\Tests\\Scanners\\Bar' => __FILE__,
            'Other\\Duh' => __FILE__,
        ];
        $mockClassloader  = new ClassLoader();
        $mockClassloader->addClassMap($classMap);
        spl_autoload_register([$mockClassloader, 'findFile'], true, true);
    }

    public function testComposerClassloader()
    {
        $expected = [
            'OpenApi\Tests\\Scanners\\Foo',
            'OpenApi\Tests\\Scanners\\Bar',
        ];
        $result = (new AutoloaderScanner())->scan('OpenApi\Tests');
        $this->assertEquals($expected, $result);
    }
}
