<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Spec;

use OpenApi\Generator;
use OpenApi\Spec\OpenApi31Compiler;
use OpenApi\Spec\SpecificationConverter;
use OpenApi\Tests\Concerns\UsesExamples;
use OpenApi\Tests\OpenApiTestCase;

class SpecificationConverterTest extends OpenApiTestCase
{
    use UsesExamples;

    public function testApiExample(): void
    {
        $this->registerExampleClassloader('api', 'attributes');

        $generator = new Generator($this->getTrackingLogger());
        $generator->setTypeResolver($this->getTypeResolver());
        $generator->setVersion('3.1.0');

        $openapi = $generator->generate([self::examplePath('api/attributes')]);

        $this->assertNotFalse($openapi, 'Generator returned false');

        $converter = new SpecificationConverter();
        $specification = $converter->convert($openapi);

        $compiler = new OpenApi31Compiler();
        $compiled = $compiler->compile($specification);

        $this->assertSpecEquals(
            $compiled,
            file_get_contents(__DIR__ . '/../../docs/examples/specs/api/api-3.1.0.yaml'),
        );
    }
}
