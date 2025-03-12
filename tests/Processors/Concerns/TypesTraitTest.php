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
                'reflection' => ['explicitType' => null, 'types' => [], 'name' => null, 'nullable' => true, 'isArray' => false],
                'docblock' => ['explicitType' => null, 'types' => [], 'name' => null, 'nullable' => true, 'isArray' => false],
            ],
        ];
        yield 'string' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'string'),
            [
                'reflection' => ['explicitType' => 'string', 'types' => ['string'], 'name' => 'string', 'nullable' => false, 'isArray' => false],
                'docblock' => ['explicitType' => 'string', 'types' => ['string'], 'name' => 'string', 'nullable' => false, 'isArray' => false],
            ],
        ];

        yield '?string' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'nullableString'),
            [
                'reflection' => ['explicitType' => 'string', 'types' => ['string'], 'name' => 'nullableString', 'nullable' => true, 'isArray' => false],
                'docblock' => ['explicitType' => 'string', 'types' => ['string'], 'name' => 'nullableString', 'nullable' => true, 'isArray' => false],
            ],
        ];

        yield 'string[]' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'stringArray'),
            [
                'reflection' => ['explicitType' => 'mixed', 'types' => ['mixed'], 'name' => 'stringArray', 'nullable' => false, 'isArray' => true],
                'docblock' => ['explicitType' => 'string', 'types' => ['string'], 'name' => 'stringArray', 'nullable' => false, 'isArray' => true],
            ],
        ];

        yield 'array<string>' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'stringList'),
            [
                'reflection' => ['explicitType' => 'mixed', 'types' => ['mixed'], 'name' => 'stringList', 'nullable' => false, 'isArray' => true],
                'docblock' => ['explicitType' => 'string', 'types' => ['string'], 'name' => 'stringList', 'nullable' => false, 'isArray' => true],
            ],
        ];

        yield '?array<string>' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'nullableStringList'),
            [
                'reflection' => ['explicitType' => 'mixed', 'types' => ['mixed'], 'name' => 'nullableStringList', 'nullable' => true, 'isArray' => true],
                'docblock' => ['explicitType' => 'string', 'types' => ['string'], 'name' => 'nullableStringList', 'nullable' => true, 'isArray' => true],
            ],
        ];

        yield 'array<string>|null' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'nullableStringListUnion'),
            [
                'reflection' => ['explicitType' => 'mixed', 'types' => ['mixed'], 'name' => 'nullableStringListUnion', 'nullable' => true, 'isArray' => true],
                'docblock' => ['explicitType' => 'string', 'types' => ['string'], 'name' => 'nullableStringListUnion', 'nullable' => true, 'isArray' => true],
            ],
        ];

        yield 'DocblockAndTypehintTypes' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'class'),
            [
                'reflection' => ['explicitType' => DocblockAndTypehintTypes::class, 'types' => [DocblockAndTypehintTypes::class], 'name' => 'class', 'nullable' => false, 'isArray' => false],
                'docblock' => ['explicitType' => DocblockAndTypehintTypes::class, 'types' => [DocblockAndTypehintTypes::class], 'name' => 'class', 'nullable' => false, 'isArray' => false],
            ],
        ];

        yield '?DocblockAndTypehintTypes' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'nullableClass'),
            [
                'reflection' => ['explicitType' => DocblockAndTypehintTypes::class, 'types' => [DocblockAndTypehintTypes::class], 'name' => 'nullableClass', 'nullable' => true, 'isArray' => false],
                'docblock' => ['explicitType' => DocblockAndTypehintTypes::class, 'types' => [DocblockAndTypehintTypes::class], 'name' => 'nullableClass', 'nullable' => true, 'isArray' => false],
            ],
        ];

        yield '\\DateTime' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'namespacedGlobalClass'),
            [
                'reflection' => ['explicitType' => \DateTime::class, 'types' => [\DateTime::class], 'name' => 'namespacedGlobalClass', 'nullable' => false, 'isArray' => false],
                'docblock' => ['explicitType' => \DateTime::class, 'types' => [\DateTime::class], 'name' => 'namespacedGlobalClass', 'nullable' => false, 'isArray' => false],
            ],
        ];

        yield '\\DateTime|null' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'nullableNamespacedGlobalClass'),
            [
                'reflection' => ['explicitType' => \DateTime::class, 'types' => [\DateTime::class], 'name' => 'nullableNamespacedGlobalClass', 'nullable' => true, 'isArray' => false],
                'docblock' => ['explicitType' => \DateTime::class, 'types' => [\DateTime::class], 'name' => 'nullableNamespacedGlobalClass', 'nullable' => true, 'isArray' => false],
            ],
        ];

        yield 'null|\\DateTime' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'alsoNullableNamespacedGlobalClass'),
            [
                'reflection' => ['explicitType' => \DateTime::class, 'types' => [\DateTime::class], 'name' => 'alsoNullableNamespacedGlobalClass', 'nullable' => true, 'isArray' => false],
                'docblock' => ['explicitType' => \DateTime::class, 'types' => [\DateTime::class], 'name' => 'alsoNullableNamespacedGlobalClass', 'nullable' => true, 'isArray' => false],
            ],
        ];

        yield 'int<min,10>' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'intRange'),
            [
                'reflection' => ['explicitType' => 'int', 'types' => ['int'], 'name' => 'intRange', 'nullable' => false, 'isArray' => false],
                'docblock' => ['explicitType' => 'int', 'types' => ['int'], 'name' => 'intRange', 'nullable' => false, 'isArray' => false],
            ],
        ];

        yield 'positive-int' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'positiveInt'),
            [
                'reflection' => ['explicitType' => 'int', 'types' => ['int'], 'name' => 'positiveInt', 'nullable' => false, 'isArray' => false],
                'docblock' => ['explicitType' => 'positive-int', 'types' => ['int'], 'name' => 'positiveInt', 'nullable' => false, 'isArray' => false],
            ],
        ];

        yield 'array-shape' => [
            new \ReflectionProperty(DocblockAndTypehintTypes::class, 'arrayShape'),
            [
                'reflection' => ['explicitType' => 'mixed', 'types' => ['mixed'], 'name' => 'arrayShape', 'nullable' => false, 'isArray' => true],
                'docblock' => ['explicitType' => 'mixed', 'types' => ['mixed'], 'name' => 'arrayShape', 'nullable' => false, 'isArray' => true],
            ],
        ];

        yield 'promoted-string' => [
            (new \ReflectionClass(DocblockAndTypehintTypes::class))->getConstructor()->getParameters()[0],
            [
                'reflection' => ['explicitType' => 'string', 'types' => ['string'], 'name' => 'promotedString', 'nullable' => false, 'isArray' => false],
                'docblock' => ['explicitType' => 'string', 'types' => ['string'], 'name' => 'promotedString', 'nullable' => false, 'isArray' => false],
            ],
        ];

        yield 'return-string' => [
            (new \ReflectionClass(DocblockAndTypehintTypes::class))->getMethod('getString'),
            [
                'reflection' => ['explicitType' => 'string', 'types' => ['string'], 'name' => 'getString', 'nullable' => false, 'isArray' => false],
                'docblock' => ['explicitType' => 'string', 'types' => ['string'], 'name' => 'getString', 'nullable' => false, 'isArray' => false],
            ],
        ];
    }

    /**
     * @dataProvider propertyCases
     */
    public function testGetReflectionTypeDetails(\Reflector $reflector, array $expected): void
    {
        $this->assertEquals((object) $expected['reflection'], $this->getReflectionTypeDetails($reflector));
    }

    /**
     * @dataProvider propertyCases
     */
    public function testGetDockblockTypeDetails(\Reflector $reflector, array $expected): void
    {
        $this->assertEquals((object) $expected['docblock'], $this->getDockblockTypeDetails($reflector));
    }
}
