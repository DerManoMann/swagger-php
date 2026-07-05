<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Spec;

use OpenApi\Analysers\ReflectionAnalyser;
use OpenApi\Generator;
use OpenApi\Spec\SpecAnnotationFactory;
use OpenApi\Tests\OpenApiTestCase;

class SpecAnnotationFactoryTest extends OpenApiTestCase
{
    public function testPetStore(): void
    {
        $generator = new Generator($this->getTrackingLogger());
        $generator->setAnalyser(new ReflectionAnalyser([new SpecAnnotationFactory()]));
        $generator->setTypeResolver($this->getTypeResolver());

        $openapi = $generator->generate([__DIR__ . '/Fixtures/PetStore.php']);

        $this->assertSpecEquals(
            file_get_contents(__DIR__ . '/Fixtures/petstore.yaml'),
            $openapi,
        );
    }
}
