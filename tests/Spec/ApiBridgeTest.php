<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Spec;

use OpenApi\Analysers\ReflectionAnalyser;
use OpenApi\Generator;
use OpenApi\Spec\SpecAnnotationFactory;
use OpenApi\Tests\OpenApiTestCase;

class ApiBridgeTest extends OpenApiTestCase
{
    public function testApiExample(): void
    {
        $generator = new Generator($this->getTrackingLogger());
        $generator->setAnalyser(new ReflectionAnalyser([new SpecAnnotationFactory()]));
        $generator->setTypeResolver($this->getTypeResolver());

        $openapi = $generator->generate([__DIR__ . '/Fixtures/Api']);

        $this->assertNotFalse($openapi, 'Generator returned false');

        $this->assertSpecEquals(
            $openapi,
            file_get_contents(__DIR__ . '/../../docs/examples/specs/api/api-3.1.0.yaml'),
        );
    }
}
