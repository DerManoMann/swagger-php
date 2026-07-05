<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Spec;

use OpenApi\Spec\Info;
use OpenApi\Spec\License;
use OpenApi\Spec\MediaType;
use OpenApi\Spec\OpenApi;
use OpenApi\Spec\OpenApi30Compiler;
use OpenApi\Spec\Operation;
use OpenApi\Spec\Parameter;
use OpenApi\Spec\Property;
use OpenApi\Spec\Response;
use OpenApi\Spec\Schema;
use OpenApi\Spec\Specification;
use OpenApi\Spec\Tag;
use PHPUnit\Framework\TestCase;

class OpenApi30CompilerTest extends TestCase
{
    public function testVersion(): void
    {
        $compiler = new OpenApi30Compiler();
        $this->assertSame('3.0.3', $compiler->getVersion());
    }

    public function testNullableTypeArray(): void
    {
        $spec = $this->buildSpec([
            new Schema(schema: 'User', type: ['string', 'null']),
        ]);

        $compiler = new OpenApi30Compiler();
        $output = $compiler->compile($spec);

        $schema = $output['components']['schemas']['User'];
        $this->assertSame('string', $schema['type']);
        $this->assertTrue($schema['nullable']);
    }

    public function testNullableBoolean(): void
    {
        $spec = $this->buildSpec([
            new Schema(schema: 'Name', type: 'string', nullable: true),
        ]);

        $compiler = new OpenApi30Compiler();
        $output = $compiler->compile($spec);

        $schema = $output['components']['schemas']['Name'];
        $this->assertSame('string', $schema['type']);
        $this->assertTrue($schema['nullable']);
    }

    public function testExclusiveMinimumNumeric(): void
    {
        $spec = $this->buildSpec([
            new Schema(schema: 'Age', type: 'integer', exclusiveMinimum: 0, exclusiveMaximum: 150),
        ]);

        $compiler = new OpenApi30Compiler();
        $output = $compiler->compile($spec);

        $schema = $output['components']['schemas']['Age'];
        $this->assertSame(0, $schema['minimum']);
        $this->assertTrue($schema['exclusiveMinimum']);
        $this->assertSame(150, $schema['maximum']);
        $this->assertTrue($schema['exclusiveMaximum']);
    }

    public function testExclusiveMinimumBoolean(): void
    {
        $spec = $this->buildSpec([
            new Schema(schema: 'Score', type: 'integer', minimum: 0, exclusiveMinimum: true),
        ]);

        $compiler = new OpenApi30Compiler();
        $output = $compiler->compile($spec);

        $schema = $output['components']['schemas']['Score'];
        $this->assertSame(0, $schema['minimum']);
        $this->assertTrue($schema['exclusiveMinimum']);
    }

    public function testConstBecomesEnum(): void
    {
        $spec = $this->buildSpec([
            new Schema(schema: 'Status', type: 'string', const: 'active'),
        ]);

        $compiler = new OpenApi30Compiler();
        $output = $compiler->compile($spec);

        $schema = $output['components']['schemas']['Status'];
        $this->assertSame(['active'], $schema['enum']);
        $this->assertArrayNotHasKey('const', $schema);
    }

    public function testExamplesArrayBecomesSingleExample(): void
    {
        $spec = $this->buildSpec([
            new Schema(schema: 'Color', type: 'string', examples: ['red', 'blue']),
        ]);

        $compiler = new OpenApi30Compiler();
        $output = $compiler->compile($spec);

        $schema = $output['components']['schemas']['Color'];
        $this->assertSame('red', $schema['example']);
        $this->assertArrayNotHasKey('examples', $schema);
    }

    public function testRefStripsDescription(): void
    {
        $spec = $this->buildSpec([
            new Schema(schema: 'Pet', type: 'object', properties: [
                new Property(property: 'colour', schema: new Schema(ref: '#/components/schemas/Colour', description: 'The colour')),
            ]),
        ]);

        $compiler = new OpenApi30Compiler();
        $output = $compiler->compile($spec);

        $property = $output['components']['schemas']['Pet']['properties']['colour'];
        $this->assertSame('#/components/schemas/Colour', $property['$ref']);
        $this->assertArrayNotHasKey('description', $property);
    }

    public function testNoWebhooks(): void
    {
        $spec = new Specification();
        $spec->openapi = new OpenApi(version: '3.0.3');
        $spec->info = new Info(title: 'Test', version: '1.0.0');
        $spec->operations[] = new Operation(
            webhook: 'onEvent',
            method: 'post',
            operationId: 'onEvent',
            responses: [new Response(response: 200, description: 'OK')],
        );

        $compiler = new OpenApi30Compiler();
        $output = $compiler->compile($spec);

        $this->assertArrayNotHasKey('webhooks', $output);
        $diagnostics = $compiler->getDiagnostics();
        $this->assertNotEmpty($diagnostics->warnings);
    }

    public function testStrips31OnlySchemaKeywords(): void
    {
        $spec = $this->buildSpec([
            new Schema(
                schema: 'Advanced',
                type: 'object',
                prefixItems: [new Schema(type: 'string')],
                unevaluatedProperties: false,
                if: new Schema(properties: [new Property(property: 'kind', schema: new Schema(const: 'a'))]),
                then: new Schema(required: ['extra']),
            ),
        ]);

        $compiler = new OpenApi30Compiler();
        $output = $compiler->compile($spec);

        $schema = $output['components']['schemas']['Advanced'];
        $this->assertArrayNotHasKey('prefixItems', $schema);
        $this->assertArrayNotHasKey('unevaluatedProperties', $schema);
        $this->assertArrayNotHasKey('if', $schema);
        $this->assertArrayNotHasKey('then', $schema);
        $this->assertArrayNotHasKey('else', $schema);
    }

    public function testDefaultNull(): void
    {
        $spec = $this->buildSpec([
            new Schema(schema: 'Brand', type: 'string', nullable: true, default: null),
        ]);

        $compiler = new OpenApi30Compiler();
        $output = $compiler->compile($spec);

        $schema = $output['components']['schemas']['Brand'];
        $this->assertArrayHasKey('default', $schema);
        $this->assertNull($schema['default']);
    }

    public function testLicenseNoIdentifier(): void
    {
        $spec = new Specification();
        $spec->openapi = new OpenApi(version: '3.0.3');
        $spec->info = new Info(
            title: 'Test',
            version: '1.0.0',
            license: new License(name: 'MIT', identifier: 'MIT', url: 'https://opensource.org/licenses/MIT'),
        );

        $compiler = new OpenApi30Compiler();
        $output = $compiler->compile($spec);

        $this->assertArrayNotHasKey('identifier', $output['info']['license']);
        $this->assertSame('MIT', $output['info']['license']['name']);
    }

    public function testPetStoreDowngrade(): void
    {
        $spec = new Specification();
        $spec->openapi = new OpenApi(version: '3.0.3');
        $spec->info = new Info(title: 'Pet Store', version: '1.0.0');
        $spec->tags[] = new Tag(name: 'pets', description: 'Pet operations');
        $spec->operations[] = new Operation(
            path: '/pets',
            method: 'get',
            operationId: 'listPets',
            tags: ['pets'],
            summary: 'List all pets',
            parameters: [
                new Parameter(name: 'limit', in: 'query', schema: new Schema(type: 'integer', format: 'int32')),
            ],
            responses: [
                new Response(response: 200, description: 'A list of pets', content: [
                    new MediaType(mediaType: 'application/json', schema: new Schema(type: 'array', items: new Schema(ref: '#/components/schemas/Pet'))),
                ]),
            ],
        );
        $spec->schemas[] = new Schema(
            schema: 'Pet',
            type: 'object',
            required: ['id', 'name'],
            properties: [
                new Property(property: 'id', schema: new Schema(type: 'integer', format: 'int64')),
                new Property(property: 'name', schema: new Schema(type: ['string', 'null'])),
            ],
        );

        $compiler = new OpenApi30Compiler();
        $output = $compiler->compile($spec);

        $this->assertSame('3.0.3', $output['openapi']);
        $nameSchema = $output['components']['schemas']['Pet']['properties']['name'];
        $this->assertSame('string', $nameSchema['type']);
        $this->assertTrue($nameSchema['nullable']);
    }

    private function buildSpec(array $schemas): Specification
    {
        $spec = new Specification();
        $spec->openapi = new OpenApi(version: '3.0.3');
        $spec->info = new Info(title: 'Test', version: '1.0.0');
        $spec->schemas = $schemas;

        return $spec;
    }
}
