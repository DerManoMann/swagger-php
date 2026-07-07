<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Spec;

use OpenApi\Generator;
use OpenApi\Pipeline;
use OpenApi\Processors;
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

    /**
     * Verify that running only structural processors produces a tree
     * the SpecificationConverter can work with.
     */
    public function testStructuralProcessorsOnly(): void
    {
        $this->registerExampleClassloader('api', 'attributes');

        $generator = new Generator($this->getTrackingLogger());
        $generator->setVersion('3.1.0');

        $structuralPipeline = new Pipeline([
            new Processors\MergeIntoOpenApi(),
            new Processors\MergeIntoComponents(),
            new Processors\BuildPaths(),
            new Processors\MergeJsonContent(),
            new Processors\MergeXmlContent(),
        ]);
        $generator->setProcessorPipeline($structuralPipeline);

        $openapi = $generator->generate([self::examplePath('api/attributes')], validate: false);

        $this->assertNotNull($openapi, 'Generator returned null with structural processors only');

        // Tree must have paths and components populated
        $this->assertNotEmpty($openapi->paths);
        $this->assertNotEmpty($openapi->components->schemas ?? []);

        // Converter must not throw
        $converter = new SpecificationConverter();
        $specification = $converter->convert($openapi);

        // Basic structure checks
        $this->assertNotNull($specification->info);
        $this->assertSame('Basic single file API', $specification->info->title);
        $this->assertNotEmpty($specification->operations);
        $this->assertNotEmpty($specification->schemas);
        $this->assertNotEmpty($specification->securitySchemes);

        // Operations have paths and methods
        foreach ($specification->operations as $operation) {
            $this->assertNotNull($operation->path, 'Operation missing path');
            $this->assertNotNull($operation->method, 'Operation missing method');
        }
    }
}
