<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Processors;

use OpenApi\Processors\Concerns\TypesTrait;
use OpenApi\Tests\Fixtures\PHP\DocblockAndTypehintTypes;
use OpenApi\Tests\OpenApiTestCase;

/**
 * @requires PHP 8.1
 */
class TypesTraitTest extends OpenApiTestCase
{
    use TypesTrait;

    public static function propertyCases(): iterable
    {
        yield 'string' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'firstName'),
            [
                'reflection' => ['types' => ['string'], 'name' => 'firstName', 'nullable' => false, 'isArray' => null],
                'docblock' => ['types' => ['string'], 'name' => null, 'nullable' => false, 'isArray' => null],
            ],
        ];

        yield '?string' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'secondName'),
            [
                'reflection' => ['types' => ['string'], 'name' => 'secondName', 'nullable' => true, 'isArray' => null],
                'docblock' => ['types' => ['string'], 'name' => null, 'nullable' => true, 'isArray' => null],
            ],
        ];

        yield 'string[]' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'names'),
            [
                'reflection' => ['types' => [], 'name' => 'names', 'nullable' => null, 'isArray' => true],
                'docblock' => ['types' => ['string'], 'name' => null, 'nullable' => null, 'isArray' => true],
            ],
        ];

        yield 'array<string>' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'moreNames'),
            [
                'reflection' => ['types' => [], 'name' => 'moreNames', 'nullable' => null, 'isArray' => true],
                'docblock' => ['types' => ['string'], 'name' => null, 'nullable' => null, 'isArray' => true],
            ],
        ];

        yield '?array<string>' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'optionalNames'),
            [
                'reflection' => ['types' => [], 'name' => 'optionalNames', 'nullable' => true, 'isArray' => true],
                'docblock' => ['types' => ['string'], 'name' => null, 'nullable' => true, 'isArray' => true],
            ],
        ];

        yield 'array<string>|null' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'moreOptionalNames'),
            [
                'reflection' => ['types' => [], 'name' => 'moreOptionalNames', 'nullable' => true, 'isArray' => true],
                'docblock' => ['types' => ['string'], 'name' => null, 'nullable' => true, 'isArray' => true],
            ],
        ];

        yield 'DocblockAndTypehintTypes' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'class'),
            [
                'reflection' => ['types' => [DocblockAndTypehintTypes::class], 'name' => 'class', 'nullable' => false, 'isArray' => null],
                'docblock' => ['types' => [DocblockAndTypehintTypes::class], 'name' => null, 'nullable' => false, 'isArray' => null],
            ],
        ];

        yield '?DocblockAndTypehintTypes' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'nullableClass'),
            [
                'reflection' => ['types' => [DocblockAndTypehintTypes::class], 'name' => 'nullableClass', 'nullable' => true, 'isArray' => null],
                'docblock' => ['types' => [DocblockAndTypehintTypes::class], 'name' => null, 'nullable' => true, 'isArray' => null],
            ],
        ];

        yield '\\DateTime' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'namespacedGlobalClass'),
            [
                'reflection' => ['types' => [\DateTime::class], 'name' => 'namespacedGlobalClass', 'nullable' => false, 'isArray' => null],
                'docblock' => ['types' => [\DateTime::class], 'name' => null, 'nullable' => false, 'isArray' => null],
            ],
        ];

        yield '\\DateTime|null' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'nullableNamespacedGlobalClass'),
            [
                'reflection' => ['types' => [\DateTime::class], 'name' => 'nullableNamespacedGlobalClass', 'nullable' => true, 'isArray' => null],
                'docblock' => ['types' => [\DateTime::class], 'name' => null, 'nullable' => true, 'isArray' => null],
            ],
        ];

        yield 'null|\\DateTime' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'alsoNullableNamespacedGlobalClass'),
            [
                'reflection' => ['types' => [\DateTime::class], 'name' => 'alsoNullableNamespacedGlobalClass', 'nullable' => true, 'isArray' => null],
                'docblock' => ['types' => [\DateTime::class], 'name' => null, 'nullable' => true, 'isArray' => null],
            ],
        ];

        yield 'int<min,10>' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'maxRangeInt'),
            [
                'reflection' => ['types' => ['int'], 'name' => 'maxRangeInt', 'nullable' => false, 'isArray' => null],
                'docblock' => ['types' => ['int'], 'name' => null, 'nullable' => false, 'isArray' => null],
            ],
        ];

        yield 'positive-int' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'positiveInt'),
            [
                'reflection' => ['types' => ['int'], 'name' => 'positiveInt', 'nullable' => false, 'isArray' => null],
                'docblock' => ['types' => ['int'], 'name' => null, 'nullable' => false, 'isArray' => null],
            ],
        ];
    }

    /**
     * @dataProvider propertyCases
     */
    public function testGetTypeDetailsFromReflector(\Reflector $reflector, array $expected): void
    {
        $this->assertEquals((object) $expected['reflection'], $this->getTypeDetailsFromReflector($reflector));
    }

    /**
     * @dataProvider propertyCases
     */
    public function testGetTypeDetailsFromDocblock(\Reflector $reflector, array $expected): void
    {
        $this->assertEquals((object) $expected['docblock'], $this->getTypeDetailsFromDocblock($reflector));
    }
}
