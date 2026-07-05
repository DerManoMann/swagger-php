<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Spec;

use OpenApi\Pipeline;
use OpenApi\Spec\BuildResult;
use OpenApi\Spec\CompilerDiagnostics;
use OpenApi\Spec\OpenApiBuilder;
use OpenApi\Spec\OpenApi31Compiler;
use OpenApi\Spec\SpecAugmenter;
use OpenApi\Spec\Specification;
use OpenApi\Tests\Spec\Fixtures\PetStore;
use PHPUnit\Framework\TestCase;

class OpenApiBuilderTest extends TestCase
{
    public function testBuildSpecMode(): void
    {
        $result = (new OpenApiBuilder())
            ->addSource((new \ReflectionClass(PetStore::class))->getFileName())
            ->setClassic(false)
            ->setVersion('3.1.0')
            ->build();

        $this->assertInstanceOf(BuildResult::class, $result);
        $this->assertNotNull($result->specification());
        $this->assertNotNull($result->compiler());
        $this->assertInstanceOf(CompilerDiagnostics::class, $result->diagnostics());
        $this->assertNotEmpty($result->toArray());
        $this->assertSame('3.1.0', $result->toArray()['openapi']);
    }

    public function testBuildClassicMode(): void
    {
        $result = (new OpenApiBuilder())
            ->addSource((new \ReflectionClass(PetStore::class))->getFileName())
            ->setClassic(true)
            ->setVersion('3.1.0')
            ->build();

        $this->assertInstanceOf(BuildResult::class, $result);
        $this->assertNull($result->specification());
        $this->assertNull($result->compiler());
        $this->assertNotEmpty($result->toArray());
        $this->assertSame('3.1.0', $result->toArray()['openapi']);
    }

    public function testBuildResultOutputFormats(): void
    {
        $result = (new OpenApiBuilder())
            ->addSource((new \ReflectionClass(PetStore::class))->getFileName())
            ->setClassic(false)
            ->setVersion('3.1.0')
            ->build();

        $json = $result->toJson();
        $this->assertJson($json);
        $this->assertStringContainsString('openapi', $json);

        $yaml = $result->toYaml();
        $this->assertStringContainsString('openapi:', $yaml);
    }

    public function testWithAugmenterPipelineAdd(): void
    {
        $called = false;

        $augmenter = new class ($called) extends SpecAugmenter {
            public function __construct(private bool &$called)
            {
            }

            public function augment(Specification $specification): void
            {
                $this->called = true;
            }
        };

        (new OpenApiBuilder())
            ->addSource((new \ReflectionClass(PetStore::class))->getFileName())
            ->setClassic(false)
            ->withAugmenterPipeline(fn (Pipeline $p) => $p->add($augmenter))
            ->build();

        $this->assertTrue($called);
    }

    public function testWithAugmenterPipelineInsert(): void
    {
        $order = [];

        $first = new class ($order) extends SpecAugmenter {
            public function __construct(private array &$order)
            {
            }

            public function augment(Specification $specification): void
            {
                $this->order[] = 'first';
            }
        };

        $second = new class ($order) extends SpecAugmenter {
            public function __construct(private array &$order)
            {
            }

            public function augment(Specification $specification): void
            {
                $this->order[] = 'second';
            }
        };

        (new OpenApiBuilder())
            ->addSource((new \ReflectionClass(PetStore::class))->getFileName())
            ->setClassic(false)
            ->withAugmenterPipeline(function (Pipeline $p) use ($first, $second) {
                $p->add($second);
                $p->insert($first, $second::class);
            })
            ->build();

        $this->assertSame(['first', 'second'], $order);
    }

    public function testRemoveAugmenter(): void
    {
        $augmenter = new class extends SpecAugmenter {
            public bool $called = false;

            public function augment(Specification $specification): void
            {
                $this->called = true;
            }
        };

        (new OpenApiBuilder())
            ->addSource((new \ReflectionClass(PetStore::class))->getFileName())
            ->setClassic(false)
            ->withAugmenterPipeline(function (Pipeline $pipeline) use ($augmenter) {
                $pipeline->add($augmenter);
                $pipeline->remove($augmenter::class);
            })
            ->build();

        $this->assertFalse($augmenter->called);
    }

    public function testStepByStepControl(): void
    {
        $builder = (new OpenApiBuilder())
            ->addSource((new \ReflectionClass(PetStore::class))->getFileName())
            ->setVersion('3.1.0');

        $files = $builder->scan();
        $this->assertNotEmpty($files);

        $classes = $builder->discoverClasses($files);
        $this->assertNotEmpty($classes);

        $spec = $builder->assemble($classes);
        $this->assertInstanceOf(Specification::class, $spec);
        $this->assertNotEmpty($spec->operations);

        $diagnostics = $builder->validate($spec);
        $this->assertInstanceOf(CompilerDiagnostics::class, $diagnostics);

        $output = $builder->compile($spec);
        $this->assertArrayHasKey('openapi', $output);
        $this->assertArrayHasKey('paths', $output);
    }

    public function testCompilerAutoSelection(): void
    {
        $result = (new OpenApiBuilder())
            ->addSource((new \ReflectionClass(PetStore::class))->getFileName())
            ->setClassic(false)
            ->setVersion('3.1.0')
            ->build();

        $this->assertInstanceOf(OpenApi31Compiler::class, $result->compiler());
    }

    public function testExplicitCompiler(): void
    {
        $compiler = new OpenApi31Compiler();

        $result = (new OpenApiBuilder())
            ->addSource((new \ReflectionClass(PetStore::class))->getFileName())
            ->setClassic(false)
            ->setCompiler($compiler)
            ->build();

        $this->assertSame($compiler, $result->compiler());
    }
}
