<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Spec;

use OpenApi\Spec\Assembler;
use OpenApi\Spec\OpenApi31Compiler;
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
}
