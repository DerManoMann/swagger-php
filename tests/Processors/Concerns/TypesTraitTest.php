<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Processors\Concerns;

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
        yield 'nothing' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'nothing'),
            [
                'reflection' => ['types' => [], 'name' => null, 'nullable' => true, 'isArray' => null],
                'docblock' => ['types' => [], 'name' => null, 'nullable' => true, 'isArray' => null],
            ],
        ];
        yield 'string' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'firstName'),
            [
                'reflection' => ['types' => ['string'], 'name' => 'firstName', 'nullable' => false, 'isArray' => null],
                'docblock' => ['types' => ['string'], 'name' => 'firstName', 'nullable' => false, 'isArray' => null],
            ],
        ];

        yield '?string' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'secondName'),
            [
                'reflection' => ['types' => ['string'], 'name' => 'secondName', 'nullable' => true, 'isArray' => null],
                'docblock' => ['types' => ['string'], 'name' => 'secondName', 'nullable' => true, 'isArray' => null],
            ],
        ];

        yield 'string[]' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'names'),
            [
                'reflection' => ['types' => ['mixed'], 'name' => 'names', 'nullable' => false, 'isArray' => true],
                'docblock' => ['types' => ['string'], 'name' => 'names', 'nullable' => false, 'isArray' => true],
            ],
        ];

        yield 'array<string>' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'moreNames'),
            [
                'reflection' => ['types' => ['mixed'], 'name' => 'moreNames', 'nullable' => false, 'isArray' => true],
                'docblock' => ['types' => ['string'], 'name' => 'moreNames', 'nullable' => false, 'isArray' => true],
            ],
        ];

        yield '?array<string>' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'optionalNames'),
            [
                'reflection' => ['types' => ['mixed'], 'name' => 'optionalNames', 'nullable' => true, 'isArray' => true],
                'docblock' => ['types' => ['string'], 'name' => 'optionalNames', 'nullable' => true, 'isArray' => true],
            ],
        ];

        yield 'array<string>|null' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'moreOptionalNames'),
            [
                'reflection' => ['types' => ['mixed'], 'name' => 'moreOptionalNames', 'nullable' => true, 'isArray' => true],
                'docblock' => ['types' => ['string'], 'name' => 'moreOptionalNames', 'nullable' => true, 'isArray' => true],
            ],
        ];

        yield 'DocblockAndTypehintTypes' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'class'),
            [
                'reflection' => ['types' => [DocblockAndTypehintTypes::class], 'name' => 'class', 'nullable' => false, 'isArray' => null],
                'docblock' => ['types' => [DocblockAndTypehintTypes::class], 'name' => 'class', 'nullable' => false, 'isArray' => null],
            ],
        ];

        yield '?DocblockAndTypehintTypes' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'nullableClass'),
            [
                'reflection' => ['types' => [DocblockAndTypehintTypes::class], 'name' => 'nullableClass', 'nullable' => true, 'isArray' => null],
                'docblock' => ['types' => [DocblockAndTypehintTypes::class], 'name' => 'nullableClass', 'nullable' => true, 'isArray' => null],
            ],
        ];

        yield '\\DateTime' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'namespacedGlobalClass'),
            [
                'reflection' => ['types' => [\DateTime::class], 'name' => 'namespacedGlobalClass', 'nullable' => false, 'isArray' => null],
                'docblock' => ['types' => [\DateTime::class], 'name' => 'namespacedGlobalClass', 'nullable' => false, 'isArray' => null],
            ],
        ];

        yield '\\DateTime|null' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'nullableNamespacedGlobalClass'),
            [
                'reflection' => ['types' => [\DateTime::class], 'name' => 'nullableNamespacedGlobalClass', 'nullable' => true, 'isArray' => null],
                'docblock' => ['types' => [\DateTime::class], 'name' => 'nullableNamespacedGlobalClass', 'nullable' => true, 'isArray' => null],
            ],
        ];

        yield 'null|\\DateTime' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'alsoNullableNamespacedGlobalClass'),
            [
                'reflection' => ['types' => [\DateTime::class], 'name' => 'alsoNullableNamespacedGlobalClass', 'nullable' => true, 'isArray' => null],
                'docblock' => ['types' => [\DateTime::class], 'name' => 'alsoNullableNamespacedGlobalClass', 'nullable' => true, 'isArray' => null],
            ],
        ];

        yield 'int<min,10>' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'maxRangeInt'),
            [
                'reflection' => ['types' => ['int'], 'name' => 'maxRangeInt', 'nullable' => false, 'isArray' => null],
                'docblock' => ['types' => ['int'], 'name' => 'maxRangeInt', 'nullable' => false, 'isArray' => null],
            ],
        ];

        yield 'positive-int' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'positiveInt'),
            [
                'reflection' => ['types' => ['int'], 'name' => 'positiveInt', 'nullable' => false, 'isArray' => null],
                'docblock' => ['types' => ['int'], 'name' => 'positiveInt', 'nullable' => false, 'isArray' => null],
            ],
        ];

        yield 'array-shape' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'arrayShape'),
            [
                'reflection' => ['types' => ['mixed'], 'name' => 'arrayShape', 'nullable' => false, 'isArray' => true],
                'docblock' => ['types' => ['mixed'], 'name' => 'arrayShape', 'nullable' => false, 'isArray' => true],
            ],
        ];

        yield 'promoted-string' => [
            (new \ReflectionClass(DocblockAndTypehintTypes::class))->getConstructor()->getParameters()[0],
            [
                'reflection' => ['types' => ['string'], 'name' => 'promotedString', 'nullable' => false, 'isArray' => null],
                'docblock' => ['types' => ['string'], 'name' => 'promotedString', 'nullable' => false, 'isArray' => null],
            ],
        ];

        yield 'return-string' => [
            (new \ReflectionClass(DocblockAndTypehintTypes::class))->getMethod('getString'),
            [
                'reflection' => ['types' => ['string'], 'name' => 'getString', 'nullable' => false, 'isArray' => null],
                'docblock' => ['types' => ['string'], 'name' => 'getString', 'nullable' => false, 'isArray' => null],
            ],
        ];
    }

    /**
     * @dataProvider propertyCases
     */
    public function testGetTypeDetailsFromTypeInfoReflection(\Reflector $reflector, array $expected): void
    {
        $this->assertEquals((object) $expected['reflection'], $this->getTypeDetailsFromTypeInfoReflection($reflector));
    }

    /**
     * @dataProvider propertyCases
     */
    public function testGetTypeDetailsFromTypeInfoDocblock(\Reflector $reflector, array $expected): void
    {
        $this->assertEquals((object) $expected['docblock'], $this->getTypeDetailsFromTypeInfoDocblock($reflector));
    }
}
