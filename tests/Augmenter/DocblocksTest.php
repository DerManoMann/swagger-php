<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Augmenter;

use OpenApi\Augmenter;
use OpenApi\Builder;
use OpenApi\Builder\Mode;
use OpenApi\Spec as OA;
use OpenApi\Specification;
use OpenApi\Tests\Concerns\AssemblesSpecification;
use OpenApi\Tests\Concerns\GeneratesTestMatrix;
use OpenApi\Tests\Fixtures;
use OpenApi\Undefined;
use OpenApi\Utils\DocBlockParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DocblocksTest extends TestCase
{
    use AssemblesSpecification;
    use GeneratesTestMatrix;

    public function testAugmentsOperationSummaryAndDescription(): void
    {
        $spec = $this->assemble(Fixtures\Augmenter\DocblockController::class);

        (new Augmenter\Docblocks())($spec);

        $this->assertSame('Get a thing.', $spec->operations[0]->summary);
        $this->assertSame('Returns the thing by ID.', $spec->operations[0]->description);
    }

    public function testAugmentsDeprecated(): void
    {
        $spec = $this->assemble(Fixtures\Augmenter\DocblockController::class);

        (new Augmenter\Docblocks())($spec);

        $this->assertTrue($spec->operations[0]->deprecated);
    }

    public function testAugmentsSchemaDescription(): void
    {
        $spec = $this->assemble(Fixtures\Augmenter\DocblockSchema::class);

        (new Augmenter\Docblocks())($spec);

        $this->assertSame('A documented schema.', $spec->schemas[0]->description);
        $this->assertTrue($spec->schemas[0]->deprecated);
    }

    public function testAugmentsParameterDescription(): void
    {
        $spec = $this->assemble(Fixtures\Augmenter\DocblockController::class);

        (new Augmenter\Docblocks())($spec);

        $this->assertSame('the thing identifier', $spec->operations[0]->parameters[0]->description);
    }

    /**
     * @return iterable<string, array{\Reflector, OA\Schema, string|null, mixed, bool|null}>
     */
    public static function propertyParameterCases(): iterable
    {
        $query = Fixtures\Augmenter\DocblockQuery::class;

        yield 'free text and example' => [new \ReflectionProperty($query, 'page'), new OA\Schema(type: 'integer'), 'The page to return.', 2, null];
        yield '@var description' => [new \ReflectionProperty($query, 'term'), new OA\Schema(type: 'string'), 'the search term', Undefined::UNDEFINED, null];
        yield 'deprecated' => [new \ReflectionProperty($query, 'offset'), new OA\Schema(type: 'integer'), null, Undefined::UNDEFINED, true];
        yield 'promoted constructor parameter' => [new \ReflectionParameter([$query, '__construct'], 'sort'), new OA\Schema(type: 'string'), 'The sort order.', 'desc', null];
    }

    #[DataProvider('propertyParameterCases')]
    public function testAugmentsAParameterBackedByAProperty(\Reflector $reflector, OA\Schema $schema, ?string $description, mixed $example, ?bool $deprecated): void
    {
        $spec = new Specification();
        $spec->parameters[] = $parameter = (new OA\Parameter(name: 'p', in: 'query', schema: $schema))->setReflector($reflector);

        (new Augmenter\Docblocks())($spec);

        $this->assertSame($description ?? Undefined::UNDEFINED, $parameter->description);
        $this->assertSame($example, $parameter->example);
        $this->assertSame($deprecated, $parameter->deprecated);
    }

    public function testKeepsWhatAPropertyBackedParameterSetsItself(): void
    {
        $reflector = new \ReflectionProperty(Fixtures\Augmenter\DocblockQuery::class, 'limit');
        $spec = new Specification();
        $spec->parameters[] = $explicit = (new OA\Parameter(name: 'limit', in: 'query', description: 'Set here.', schema: new OA\Schema(type: 'integer'), example: 25))->setReflector($reflector);
        $spec->parameters[] = $examples = (new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer'), examples: [new OA\Example(value: 5)]))->setReflector($reflector);

        (new Augmenter\Docblocks())($spec);

        $this->assertSame('Set here.', $explicit->description);
        $this->assertSame(25, $explicit->example);
        $this->assertSame('The page size.', $examples->description);
        $this->assertSame(Undefined::UNDEFINED, $examples->example);
    }

    public function testAugmentsAPropertyBackedParameterOfAnOperation(): void
    {
        $spec = new Specification();
        $spec->operations[] = new OA\Operation\Get(path: '/things', parameters: [
            $parameter = (new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')))
                ->setReflector(new \ReflectionProperty(Fixtures\Augmenter\DocblockQuery::class, 'page')),
        ]);

        (new Augmenter\Docblocks())($spec);

        $this->assertSame('The page to return.', $parameter->description);
        $this->assertSame(2, $parameter->example);
    }

    /**
     * `null` is an explicit "no description", not an absent one — the attribute wins over
     * the docblock. Only `Undefined::UNDEFINED` invites inference.
     */
    public function testExplicitNullSuppressesInferredSchemaDescription(): void
    {
        $spec = $this->assemble(Fixtures\Augmenter\SuppressedSchema::class);

        (new Augmenter\Docblocks())($spec);

        $this->assertNull($spec->schemas[0]->description);
    }

    public function testExplicitNullSuppressesInferredOperationSummaryAndDescription(): void
    {
        $spec = $this->assemble(Fixtures\Augmenter\SuppressedController::class);

        (new Augmenter\Docblocks())($spec);

        $this->assertNull($spec->operations[0]->summary);
        $this->assertNull($spec->operations[0]->description);
    }

    public function testExplicitNullSuppressesInferredParameterDescription(): void
    {
        $spec = $this->assemble(Fixtures\Augmenter\SuppressedController::class);

        (new Augmenter\Docblocks())($spec);

        $this->assertNull($spec->operations[0]->parameters[0]->description);
    }

    /**
     * @return iterable<string, array{string, Mode}>
     */
    public static function buildExampleCases(): iterable
    {
        foreach (self::matrixCombinations([
            'version' => self::versions(),
            'mode' => [Mode::SPEC, Mode::HYBRID],
        ]) as $combo) {
            yield self::matrixKey([$combo['version'], $combo['mode']->value]) => [$combo['version'], $combo['mode']];
        }
    }

    #[DataProvider('buildExampleCases')]
    public function testBuildsTypedExamplesFromDocblocks(string $version, Mode $mode): void
    {
        $result = (new Builder())
            ->setMode($mode)
            ->setVersion($version)
            ->addSource([
                new \ReflectionClass(Fixtures\Augmenter\DocblockExample::class),
                new \ReflectionClass(Fixtures\Augmenter\DocblockExampleController::class),
            ])
            ->build();

        $this->assertTrue($result->isValid());
        $document = json_decode($result->toJson(), flags: JSON_THROW_ON_ERROR);
        $properties = $document->components->schemas->DocblockExample->properties;

        foreach ([
            'value' => '42',
            'enabled' => 'false',
            'extra' => '{"id":1}',
            'items' => '[1,2]',
            'emptyObject' => '{}',
            'emptyArray' => '[]',
            'nullable' => 'null',
            'code' => '"00123"',
            'flag' => '"false"',
            'numericCode' => '"123"',
            'explicit' => '9',
        ] as $property => $expected) {
            $this->assertTrue(property_exists($properties->$property, 'example'), $property);
            $this->assertSame($expected, json_encode($properties->$property->example, JSON_THROW_ON_ERROR), $property);
        }
    }

    /**
     * @return iterable<string, array{string|list<string>|null, ?string, string, bool}>
     */
    public static function exampleCases(): iterable
    {
        yield 'integer' => ['integer', '42', '42', false];
        yield 'zero' => ['integer', '0', '0', false];
        yield 'number' => ['number', '1.5', '1.5', false];
        yield 'integer as number' => ['number', '42', '42', false];
        yield 'integral decimal' => ['integer', '42.0', '42', false];
        yield 'integral exponent' => ['integer', '1e3', '1000', false];
        yield 'false' => ['boolean', 'false', 'false', false];
        yield 'true' => ['boolean', 'true', 'true', false];
        yield 'object' => ['object', '{"id":1}', '{"id":1}', false];
        yield 'empty object' => ['object', '{}', '{}', false];
        yield 'array' => ['array', '[1,2]', '[1,2]', false];
        yield 'empty array' => ['array', '[]', '[]', false];
        yield 'nullable type' => [['integer', 'null'], 'null', 'null', false];
        yield 'nullable flag' => ['integer', 'null', 'null', true];
        yield 'non-nullable' => ['integer', 'null', '"null"', false];
        yield 'string' => ['string', '00123', '"00123"', false];
        yield 'string with JSON value' => ['string', 'false', '"false"', false];
        yield 'union containing string' => [['integer', 'string'], '42', '"42"', false];
        yield 'union without string' => [['object', 'array'], '[]', '[]', false];
        yield 'unknown type' => [null, '42', '"42"', false];
        yield 'invalid JSON' => ['object', '{invalid}', '"{invalid}"', false];
        yield 'wrong JSON type' => ['object', '[]', '"[]"', false];
        yield 'fractional integer' => ['integer', '1.5', '"1.5"', false];
        yield 'integer overflow' => ['integer', '9223372036854775808', '"9223372036854775808"', false];
        yield 'nested integer overflow in object' => ['object', '{"id":9223372036854775808}', '"{\"id\":9223372036854775808}"', false];
        yield 'nested integer overflow in array' => ['array', '[9223372036854775808]', '"[9223372036854775808]"', false];
        yield 'quoted large integer' => ['object', '{"id":"9223372036854775808"}', '{"id":"9223372036854775808"}', false];
        yield 'non-finite number' => ['number', '1e400', '"1e400"', false];
        yield 'nested non-finite number' => ['array', '[1e400]', '"[1e400]"', false];
        yield 'missing example' => ['integer', null, json_encode(Undefined::UNDEFINED, JSON_THROW_ON_ERROR), false];
    }

    /**
     * @param string|list<string>|null $type
     */
    #[DataProvider('exampleCases')]
    public function testExampleMatchesSchemaType(string|array|null $type, ?string $example, string $expected, bool $nullable): void
    {
        $spec = $this->assemble(Fixtures\Augmenter\DocblockExample::class);
        $schema = new OA\Schema(type: $type, nullable: $nullable);
        $spec->schemas[0]->properties[0]->schema = $schema;
        $parser = new class ($example) extends DocBlockParser {
            public function __construct(protected ?string $example)
            {
            }

            public function extractExample(string $docblock): ?string
            {
                return $this->example;
            }
        };

        (new Augmenter\Docblocks())->setParser($parser)($spec);

        $this->assertSame($expected, json_encode($schema->example, JSON_THROW_ON_ERROR));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function explicitExamples(): iterable
    {
        yield 'null' => [null];
        yield 'zero' => [0];
        yield 'false' => [false];
        yield 'empty string' => [''];
        yield 'numeric string' => ['42'];
        yield 'empty array' => [[]];
    }

    #[DataProvider('explicitExamples')]
    public function testKeepsExplicitExample(mixed $example): void
    {
        $spec = $this->assemble(Fixtures\Augmenter\DocblockExample::class);
        $schema = new OA\Schema(type: 'integer', example: $example);
        $spec->schemas[0]->properties[0]->schema = $schema;

        (new Augmenter\Docblocks())($spec);

        $this->assertSame($example, $schema->example);
    }
}
