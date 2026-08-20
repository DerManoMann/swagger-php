<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Augmenter;

use OpenApi\Augmenter;
use OpenApi\Builder;
use OpenApi\Spec as OA;
use OpenApi\Specification;
use OpenApi\Tests\Fixtures;
use OpenApi\Utils\Pipeline;
use PHPUnit\Framework\TestCase;

final class AutoAnnotateTest extends TestCase
{
    public function testCollectsUnannotatedRefTarget(): void
    {
        $spec = $this->buildWithAutoAnnotate(Fixtures\Augmenter\AutoAnnotateController::class);

        $schemaNames = array_map(fn (OA\Schema $s) => $s->schema, $spec->schemas);
        $this->assertContains('UnannotatedProduct', $schemaNames);
    }

    public function testCollectsTransitiveRefs(): void
    {
        $spec = $this->buildWithAutoAnnotate(Fixtures\Augmenter\AutoAnnotateController::class);

        $schemaNames = array_map(fn (OA\Schema $s) => $s->schema, $spec->schemas);
        $this->assertContains('UnannotatedAddress', $schemaNames);
    }

    public function testSkipsAlreadyAnnotatedSchemas(): void
    {
        $spec = $this->buildWithAutoAnnotate(
            Fixtures\Augmenter\RefTarget::class,
            Fixtures\Augmenter\RefController::class,
        );

        $refTargetSchemas = array_filter($spec->schemas, fn (OA\Schema $s) => $s->schema === 'RefTarget');
        $this->assertCount(1, $refTargetSchemas);
    }

    public function testIgnoresAlreadyResolvedRefs(): void
    {
        $spec = new Specification();
        $operation = new OA\Operation(path: '/test', method: 'get');
        $response = new OA\Response(response: 200, description: 'OK', content: [
            new OA\MediaType(mediaType: 'application/json', schema: new OA\Schema(ref: '#/components/schemas/Foo')),
        ]);
        $operation->responses = [$response];
        $spec->operations[] = $operation;

        (new Augmenter\AutoAnnotate())($spec);

        $this->assertSame('#/components/schemas/Foo', $response->content[0]->schema->ref);
        $this->assertEmpty($spec->schemas);
    }

    public function testGeneratesPropertiesForPublicProps(): void
    {
        $spec = $this->buildWithAutoAnnotate(Fixtures\Augmenter\AutoAnnotateController::class);

        $addressSchema = null;
        foreach ($spec->schemas as $schema) {
            if ($schema->schema === 'UnannotatedAddress') {
                $addressSchema = $schema;
                break;
            }
        }

        $this->assertInstanceOf(OA\Schema::class, $addressSchema);
        $propertyNames = array_map(fn (OA\Property $p) => $p->property, $addressSchema->properties ?? []);
        $this->assertContains('street', $propertyNames);
        $this->assertContains('city', $propertyNames);
    }

    public function testGeneratesPropertiesForPromotedParams(): void
    {
        $spec = $this->buildWithAutoAnnotate(Fixtures\Augmenter\AutoAnnotateController::class);

        $productSchema = null;
        foreach ($spec->schemas as $schema) {
            if ($schema->schema === 'UnannotatedProduct') {
                $productSchema = $schema;
                break;
            }
        }

        $this->assertInstanceOf(OA\Schema::class, $productSchema);
        $propertyNames = array_map(fn (OA\Property $p) => $p->property, $productSchema->properties ?? []);
        $this->assertContains('name', $propertyNames);
        $this->assertContains('price', $propertyNames);
        $this->assertContains('address', $propertyNames);
    }

    protected function buildWithAutoAnnotate(string ...$classes): Specification
    {
        $builder = new Builder();
        $builder->setMode(Builder\Mode::SPEC);
        foreach ($classes as $class) {
            $builder->addSource(new \ReflectionClass($class));
        }
        $builder->withAugmenters(function (Pipeline $augmenters) {
            $augmenters->insert(new Augmenter\AutoAnnotate(), Augmenter\Refs::class);
        });

        return $builder->build()->specification();
    }
}
