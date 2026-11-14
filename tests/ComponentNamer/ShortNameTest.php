<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\ComponentNamer;

use OpenApi\ComponentNamer\QualifiedName;
use OpenApi\ComponentNamer\ShortName;
use OpenApi\Spec as OA;
use OpenApi\Specification\ComponentKey;
use OpenApi\Tests\Fixtures\Augmenter\BackedStringEnum;
use OpenApi\Tests\Fixtures\ClassDescriptions\Account;
use OpenApi\Tests\Fixtures\ClassDescriptions\Author;
use OpenApi\Type\Generics\TypeArguments;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Type;

final class ShortNameTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function qualifierCases(): iterable
    {
        yield 'plain' => [[], 'Author'];
        yield 'one value' => [['groups' => ['summary']], 'Author-groups.summary'];
        yield 'values in sorted order' => [['groups' => ['summary', 'admin']], 'Author-groups.admin_summary'];
        yield 'qualifiers in sorted order' => [['groups' => 'summary', 'direction' => 'input'], 'Author-direction.input-groups.summary'];
        yield 'no values' => [['groups' => []], 'Author-groups'];
        yield 'scalars' => [['strict' => true, 'version' => 2], 'Author-strict.true-version.2'];
        yield 'characters a name does not allow' => [['groups' => ['user:read']], 'Author-groups.user_read'];
        yield 'a class' => [['of' => Account::class], 'Author-of.Account'];
    }

    /**
     * @param array<string, mixed> $qualifiers
     */
    #[DataProvider('qualifierCases')]
    public function testAddsTheKeysQualifiers(array $qualifiers, string $expected): void
    {
        $this->assertSame($expected, (new ShortName())->name($this->author($qualifiers)));
    }

    public function testNamesAClassQualifierLikeItsComponent(): void
    {
        $namer = new QualifiedName(strip: 'OpenApi\Tests\Fixtures\\');

        $this->assertSame('ClassDescriptions.Author-of.ClassDescriptions.Account', $namer->name($this->author(['of' => Account::class])));
    }

    /**
     * @return iterable<string, array{TypeArguments, ?string}>
     */
    public static function typeArgumentCases(): iterable
    {
        $account = Type::object(Account::class);

        yield 'a class' => [new TypeArguments($account), 'Author-of.Account'];
        yield 'a class written with a leading backslash' => [new TypeArguments(Type::object('\\' . Account::class)), 'Author-of.Account'];
        yield 'an enum' => [new TypeArguments(Type::enum(BackedStringEnum::class)), 'Author-of.BackedStringEnum'];
        yield 'a scalar' => [new TypeArguments(Type::int()), 'Author-of.int'];
        yield 'several, in the order given' => [new TypeArguments(Type::string(), $account, Type::mixed()), 'Author-of.string_Account_mixed'];
        yield 'a list' => [new TypeArguments(Type::list($account)), 'Author-of.list-of.Account'];
        yield 'a list of lists' => [new TypeArguments(Type::list(Type::list(Type::bool()))), 'Author-of.list-of.list-of.bool'];
        yield 'a class with type arguments' => [new TypeArguments(Type::generic($account, Type::float(), $account)), 'Author-of.Account-of.float_Account'];
        yield 'a union' => [new TypeArguments(Type::union($account, Type::int())), null];
        yield 'a nullable class' => [new TypeArguments(Type::nullable($account)), null];
        yield 'a map' => [new TypeArguments(Type::dict($account)), null];
        yield 'an array shape' => [new TypeArguments(Type::arrayShape(['id' => Type::int()])), null];
        yield 'a list of a union' => [new TypeArguments(Type::list(Type::union($account, Type::int()))), null];
        yield 'a template left unbound' => [new TypeArguments(Type::template('T')), null];
        yield 'a class with a template left unbound' => [new TypeArguments(Type::generic($account, Type::template('T'))), null];
        yield 'a class that does not exist' => [new TypeArguments(Type::object('App\Missing')), null];
        yield 'one of several that cannot be written' => [new TypeArguments($account, Type::null()), null];
    }

    #[DataProvider('typeArgumentCases')]
    public function testAddsTheKeysTypeArguments(TypeArguments $typeArguments, ?string $expected): void
    {
        $schema = (new OA\Schema())
            ->setReflector(new \ReflectionClass(Author::class))
            ->setComponentKey(new ComponentKey(Author::class, typeArguments: $typeArguments));

        $this->assertSame($expected, (new ShortName())->name($schema));
    }

    public function testTypeArgumentsComeAfterTheQualifiers(): void
    {
        $schema = (new OA\Schema())
            ->setReflector(new \ReflectionClass(Author::class))
            ->setComponentKey(new ComponentKey(Author::class, ['groups' => ['summary']], new TypeArguments(Type::object(Account::class))));

        $this->assertSame('Author-groups.summary-of.Account', (new ShortName())->name($schema));
    }

    public function testNamesAClassTypeArgumentLikeItsComponent(): void
    {
        $namer = new QualifiedName(strip: 'OpenApi\Tests\Fixtures\\');
        $schema = (new OA\Schema())
            ->setReflector(new \ReflectionClass(Author::class))
            ->setComponentKey(new ComponentKey(Author::class, typeArguments: new TypeArguments(Type::list(Type::object(Account::class)))));

        $this->assertSame('ClassDescriptions.Author-of.list-of.ClassDescriptions.Account', $namer->name($schema));
    }

    public function testLeavesAComponentWithoutANameInline(): void
    {
        $schema = (new OA\Schema())->setComponentKey(new ComponentKey(Author::class, ['groups' => ['summary']]));

        $this->assertNull((new ShortName())->name($schema));
    }

    /**
     * @param array<string, mixed> $qualifiers
     */
    protected function author(array $qualifiers): OA\Schema
    {
        return (new OA\Schema())
            ->setReflector(new \ReflectionClass(Author::class))
            ->setComponentKey(new ComponentKey(Author::class, $qualifiers));
    }
}
