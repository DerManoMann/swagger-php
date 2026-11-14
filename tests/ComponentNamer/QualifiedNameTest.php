<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\ComponentNamer;

use OpenApi\ComponentNamer\QualifiedName;
use OpenApi\OpenApiException;
use OpenApi\Spec as OA;
use OpenApi\Specification\ComponentKey;
use OpenApi\Tests\Fixtures;
use OpenApi\Type\Generics\TypeArguments;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Type;

final class QualifiedNameTest extends TestCase
{
    /**
     * @return iterable<string, array{QualifiedName, string}>
     */
    public static function nameCases(): iterable
    {
        yield 'default' => [new QualifiedName(), 'OpenApi.Tests.Fixtures.Augmenter.TypeSchema'];
        yield 'stripped' => [new QualifiedName(strip: 'OpenApi\Tests\Fixtures\\'), 'Augmenter.TypeSchema'];
        yield 'stripped without the trailing separator' => [new QualifiedName(strip: '\OpenApi\Tests\Fixtures'), 'Augmenter.TypeSchema'];
        yield 'run together' => [new QualifiedName(strip: 'OpenApi\Tests\Fixtures\\', separator: ''), 'AugmenterTypeSchema'];
        yield 'outside the stripped namespace' => [new QualifiedName(strip: 'App\Dto\\', separator: '_'), 'OpenApi_Tests_Fixtures_Augmenter_TypeSchema'];
    }

    #[DataProvider('nameCases')]
    public function testNamesAClassSchema(QualifiedName $namer, string $expected): void
    {
        $schema = (new OA\Schema())->setReflector(new \ReflectionClass(Fixtures\Augmenter\TypeSchema::class));

        $this->assertSame($expected, $namer->name($schema));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function typeArgumentCases(): iterable
    {
        yield 'a dot' => ['.', 'Augmenter.TypeSchema-of.int_list-of.Augmenter.BackedStringEnum'];
        yield 'run together' => ['', 'AugmenterTypeSchema-of.int_list-of.AugmenterBackedStringEnum'];
        yield 'a hyphen' => ['-', 'Augmenter-TypeSchema-of.int_list-of.Augmenter-BackedStringEnum'];
        yield 'an underscore' => ['_', 'Augmenter_TypeSchema-of.int_list-of.Augmenter_BackedStringEnum'];
    }

    /**
     * Every separator gives a name OpenAPI allows, with each class written as the namer names it.
     */
    #[DataProvider('typeArgumentCases')]
    public function testNamesTypeArgumentsWithEachSeparator(string $separator, string $expected): void
    {
        $typeArguments = new TypeArguments(Type::int(), Type::list(Type::enum(Fixtures\Augmenter\BackedStringEnum::class)));
        $schema = (new OA\Schema())
            ->setReflector(new \ReflectionClass(Fixtures\Augmenter\TypeSchema::class))
            ->setComponentKey(new ComponentKey(Fixtures\Augmenter\TypeSchema::class, typeArguments: $typeArguments));

        $name = (new QualifiedName(strip: 'OpenApi\Tests\Fixtures\\', separator: $separator))->name($schema);

        $this->assertSame($expected, $name);
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9._-]+$/', $name);
    }

    public function testLeavesAComponentWithoutAClassInline(): void
    {
        $this->assertNull((new QualifiedName())->name(new OA\Schema()));
    }

    public function testRejectsASeparatorAComponentKeyCannotHold(): void
    {
        $this->expectException(OpenApiException::class);
        $this->expectExceptionMessage('Invalid separator "\"');

        new QualifiedName(separator: '\\');
    }
}
