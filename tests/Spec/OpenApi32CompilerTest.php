<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Spec;

use OpenApi\Spec\Assembler;
use OpenApi\Spec\Info;
use OpenApi\Spec\OpenApi;
use OpenApi\Spec\OpenApi32Compiler;
use OpenApi\Spec\Operation;
use OpenApi\Spec\Response;
use OpenApi\Spec\Schema;
use OpenApi\Spec\Specification;
use OpenApi\Tests\Spec\Fixtures\PetStore;
use PHPUnit\Framework\TestCase;

class OpenApi32CompilerTest extends TestCase
{
    public function testVersion(): void
    {
        $compiler = new OpenApi32Compiler();
        $this->assertSame('3.2.0', $compiler->getVersion());
    }

    public function testSupportsKnownVersions(): void
    {
        $compiler = new OpenApi32Compiler();
        $this->assertTrue($compiler->supports('3.2.0'));
        $this->assertFalse($compiler->supports('3.2.99'));
        $this->assertFalse($compiler->supports('3.1.0'));
        $this->assertFalse($compiler->supports('3.0.3'));
    }

    public function testOutputVersion(): void
    {
        $spec = new Specification();
        $spec->openapi = new OpenApi(version: '3.2.0');
        $spec->info = new Info(title: 'Test', version: '1.0.0');

        $compiler = new OpenApi32Compiler();
        $output = $compiler->compile($spec);

        $this->assertSame('3.2.0', $output['openapi']);
    }

    public function testPetStoreProducesValidOutput(): void
    {
        $assembler = new Assembler();
        $assembler->collect(new \ReflectionClass(PetStore::class));

        $spec = $assembler->getSpecification();
        $spec->openapi->version = '3.2.0';

        $compiler = new OpenApi32Compiler();
        $output = $compiler->compile($spec);

        $this->assertSame('3.2.0', $output['openapi']);
        $this->assertSame('Pet Store', $output['info']['title']);
        $this->assertArrayHasKey('paths', $output);
        $this->assertArrayHasKey('components', $output);
    }

    public function testNullableTypeArray(): void
    {
        $spec = new Specification();
        $spec->openapi = new OpenApi(version: '3.2.0');
        $spec->info = new Info(title: 'Test', version: '1.0.0');
        $spec->schemas[] = new Schema(schema: 'Name', type: ['string', 'null']);

        $compiler = new OpenApi32Compiler();
        $output = $compiler->compile($spec);

        $schema = $output['components']['schemas']['Name'];
        $this->assertSame(['string', 'null'], $schema['type']);
        $this->assertArrayNotHasKey('nullable', $schema);
    }

    public function testWebhooksSupported(): void
    {
        $spec = new Specification();
        $spec->openapi = new OpenApi(version: '3.2.0');
        $spec->info = new Info(title: 'Test', version: '1.0.0');
        $spec->operations[] = new Operation(
            webhook: 'onEvent',
            method: 'post',
            operationId: 'onEvent',
            responses: [new Response(response: 200, description: 'OK')],
        );

        $compiler = new OpenApi32Compiler();
        $output = $compiler->compile($spec);

        $this->assertArrayHasKey('webhooks', $output);
        $this->assertArrayHasKey('onEvent', $output['webhooks']);
    }

    public function testQueryMethodInPaths(): void
    {
        $spec = new Specification();
        $spec->openapi = new OpenApi(version: '3.2.0');
        $spec->info = new Info(title: 'Test', version: '1.0.0');
        $spec->operations[] = new Operation(
            path: '/search',
            method: 'query',
            operationId: 'searchQuery',
            responses: [new Response(response: 200, description: 'Results')],
        );

        $compiler = new OpenApi32Compiler();
        $output = $compiler->compile($spec);

        $this->assertArrayHasKey('/search', $output['paths']);
        $this->assertArrayHasKey('query', $output['paths']['/search']);
    }
}
