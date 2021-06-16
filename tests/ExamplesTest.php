<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests;

use OpenApi\Generator;

class ExamplesTest extends OpenApiTestCase
{
    public function exampleMappings()
    {
        return [
            'misc' => [
                'misc',
                ['3.0.0' => 'misc.yaml'],
            ],
            'openapi-spec' => [
                'openapi-spec',
                ['3.0.0' => 'openapi-spec.yaml'],
            ],
            'petstore.swagger.io' => [
                'petstore.swagger.io',
                ['3.0.0' => 'petstore.swagger.io.yaml'],
            ],
            'petstore-3.0' => [
                'petstore-3.0',
                ['3.0.0' => 'petstore-3.0.yaml'],
            ],
            'swagger-spec/petstore' => [
                'swagger-spec/petstore',
                ['3.0.0' => 'petstore.yaml'],
            ],
            'swagger-spec/petstore-simple' => [
                'swagger-spec/petstore-simple',
                ['3.0.0' => 'petstore-simple.yaml'],
            ],
            'swagger-spec/petstore-with-external-docs' => [
                'swagger-spec/petstore-with-external-docs',
                ['3.0.0' => 'petstore-with-external-docs.yaml'],
            ],
            'using-refs' => [
                'using-refs',
                [
                    '3.0.0' => 'using-refs.yaml',
                    '3.1.0' => 'using-refs.3.1.0.yaml',
                ],
            ],
            'example-object' => [
                'example-object',
                ['3.0.0' => 'example-object.yaml'],
            ],
            'using-interfaces' => [
                'using-interfaces',
                ['3.0.0' => 'using-interfaces.yaml'],
            ],
            'using-traits' => [
                'using-traits',
                ['3.0.0' => 'using-traits.yaml'],
            ],
        ];
    }

    /**
     * Validate openapi definitions of the included examples.
     *
     * @dataProvider exampleMappings
     */
    public function testExamples(string $example, array $specs)
    {
        $path = __DIR__.'/../Examples/'.$example;

        foreach ($specs as $version => $spec) {
            $openapi = Generator::scan([$path], ['version' => $version]);
            file_put_contents($path.'/'.$spec, $openapi->toYaml());
            $this->assertSpecEquals(file_get_contents($path.'/'.$spec), $openapi, 'Examples/'.$example.'/'.$spec);
        }
    }
}
