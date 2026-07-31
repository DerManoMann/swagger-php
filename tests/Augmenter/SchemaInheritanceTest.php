<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Augmenter;

use OpenApi\Assembler;
use OpenApi\Augmenter;
use OpenApi\Spec as OA;
use OpenApi\Tests\Concerns\AssemblesSpecification;
use OpenApi\Tests\Concerns\AssertsSchemaStructure;
use OpenApi\Tests\Fixtures\Augmenter\Hierarchy\Spec as Fixtures;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;


final class SchemaInheritanceTest extends TestCase
{
    use AssemblesSpecification;
    use AssertsSchemaStructure;

    public function testAllSchemasMatchExpected(): void
    {
        $assembler = new Assembler();
        $assembler->collect(
            new \ReflectionClass(Fixtures\TraitWithSchema::class),
            new \ReflectionClass(Fixtures\ClassUsingTraitWithSchema::class),
            new \ReflectionClass(Fixtures\PlainTrait::class),
            new \ReflectionClass(Fixtures\ClassUsingPlainTrait::class),
            new \ReflectionClass(Fixtures\ParentWithSchema::class),
            new \ReflectionClass(Fixtures\ChildOfParentWithSchema::class),
            new \ReflectionClass(Fixtures\PlainParent::class),
            new \ReflectionClass(Fixtures\ChildOfPlainParent::class),
            new \ReflectionClass(Fixtures\StandaloneSchema::class),
        );

        $spec = $assembler->getSpecification();
        (new Augmenter\SchemaInheritance())($spec);

        $this->assertSpecificationSchemasMatchFile(
            $spec,
            __DIR__ . '/../Fixtures/Augmenter/Hierarchy/expected.yaml',
        );
    }

    public static function provideDiscoveryFromUnvisitedAncestor(): \Generator
    {
        yield 'plain parent' => [Fixtures\ChildOfPlainParent::class, ['childProp', 'parentProp']];
        yield 'schema parent' => [Fixtures\ChildOfParentWithSchema::class, ['baseProp', 'childProp']];
        yield 'plain trait' => [Fixtures\ClassUsingPlainTrait::class, ['ownProp', 'traitProp']];
        yield 'schema trait' => [Fixtures\ClassUsingTraitWithSchema::class, ['age', 'name']];
    }

    /**
     * @param list<string> $expectedProps
     */
    #[DataProvider('provideDiscoveryFromUnvisitedAncestor')]
    public function testDiscoveryWhenOnlyChildAssembled(string $class, array $expectedProps): void
    {
        $spec = $this->assemble($class);

        $this->assertCount(1, $spec->schemas, 'Only the child schema is assembled');

        (new Augmenter\SchemaInheritance())($spec);

        $schema = $spec->schemas[0];
        $this->assertNull($schema->allOf, 'Unassembled ancestor cannot be referenced via allOf');

        $props = array_map(fn (OA\Property $p): string => $p->property, $schema->properties);
        sort($props);
        $this->assertSame($expectedProps, $props);
    }
}
