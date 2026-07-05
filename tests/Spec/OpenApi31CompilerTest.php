<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Spec;

use OpenApi\Spec\Assembler;
use OpenApi\Spec\Info;
use OpenApi\Spec\License;
use OpenApi\Spec\OpenApi31Compiler;
use OpenApi\Spec\Operation;
use OpenApi\Spec\Response;
use OpenApi\Spec\Schema;
use OpenApi\Spec\Specification;
use OpenApi\Tests\Spec\Fixtures\PetStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class OpenApi31CompilerTest extends TestCase
{
    public function testSupportsMatchesPatchVersions(): void
    {
        $compiler = new OpenApi31Compiler();
        $this->assertTrue($compiler->supports('3.1.0'));
        $this->assertTrue($compiler->supports('3.1.1'));
        $this->assertTrue($compiler->supports('3.1.2'));
        $this->assertFalse($compiler->supports('3.0.0'));
        $this->assertFalse($compiler->supports('3.0.3'));
        $this->assertFalse($compiler->supports('3.2.0'));
    }

    public function testPetStoreCompile(): void
    {
        $assembler = new Assembler();
        $assembler->collect(new \ReflectionClass(PetStore::class));

        $compiler = new OpenApi31Compiler();
        $output = $compiler->compile($assembler->getSpecification());

        $expected = Yaml::parseFile(__DIR__ . '/Fixtures/petstore-compiled.yaml');

        $this->assertSame($expected, $output);
    }

    public function testValidate(): void
    {
        $assembler = new Assembler();
        $assembler->collect(new \ReflectionClass(PetStore::class));

        $compiler = new OpenApi31Compiler();
        $diagnostics = $compiler->validate($assembler->getSpecification());

        $this->assertTrue($diagnostics->isValid());
        $this->assertEmpty($diagnostics->errors);
        $this->assertEmpty($diagnostics->warnings);
    }

    public function testValidateMissingInfo(): void
    {
        $compiler = new OpenApi31Compiler();
        $spec = new Specification();

        $diagnostics = $compiler->validate($spec);

        $this->assertTrue($diagnostics->hasErrors());
        $this->assertSame('info is required', $diagnostics->errors[0]->message);
    }

    public function testValidateNoPaths(): void
    {
        $compiler = new OpenApi31Compiler();
        $spec = new Specification();
        $spec->info = new Info(title: 'Test', version: '1.0.0');

        $diagnostics = $compiler->validate($spec);

        $this->assertNotEmpty($diagnostics->warnings);
        $this->assertStringContainsString('at least one of', strtolower($diagnostics->warnings[0]->message));
    }

    public function testValidateWebhooksOnly(): void
    {
        $compiler = new OpenApi31Compiler();
        $spec = new Specification();
        $spec->info = new Info(title: 'Test', version: '1.0.0');
        $spec->operations[] = new Operation(webhook: 'onEvent', method: 'post', responses: [new Response(response: 200, description: 'OK')]);

        $diagnostics = $compiler->validate($spec);

        $this->assertEmpty($diagnostics->warnings);
    }

    public function testValidateLicenseMutualExclusion(): void
    {
        $compiler = new OpenApi31Compiler();
        $spec = new Specification();
        $spec->info = new Info(
            title: 'Test',
            version: '1.0.0',
            license: new License(name: 'MIT', identifier: 'MIT', url: 'https://example.com'),
        );
        $spec->operations[] = new Operation(path: '/test', method: 'get', responses: [new Response(response: 200, description: 'OK')]);

        $diagnostics = $compiler->validate($spec);

        $this->assertNotEmpty($diagnostics->warnings);
        $this->assertStringContainsString('mutually exclusive', $diagnostics->warnings[0]->message);
    }

    public function testValidateArrayWithoutItems(): void
    {
        $compiler = new OpenApi31Compiler();
        $spec = new Specification();
        $spec->info = new Info(title: 'Test', version: '1.0.0');
        $spec->schemas[] = new Schema(schema: 'List', type: 'array');

        $diagnostics = $compiler->validate($spec);

        $this->assertNotEmpty($diagnostics->warnings);
        $this->assertStringContainsString('items', $diagnostics->warnings[0]->message);
    }

    public function testValidateArrayTypeInArrayForm(): void
    {
        $compiler = new OpenApi31Compiler();
        $spec = new Specification();
        $spec->info = new Info(title: 'Test', version: '1.0.0');
        $spec->schemas[] = new Schema(schema: 'NullableList', type: ['array', 'null']);

        $diagnostics = $compiler->validate($spec);

        $warnings = array_filter($diagnostics->warnings, fn ($d) => str_contains($d->message, 'items'));
        $this->assertNotEmpty($warnings);
    }
}
