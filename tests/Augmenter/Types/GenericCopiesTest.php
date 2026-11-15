<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Augmenter\Types;

use OpenApi\Augmenter;
use OpenApi\Augmenter\Types\CopyRule;
use OpenApi\Builder;
use OpenApi\Builder\Mode;
use OpenApi\Builder\Result;
use OpenApi\Contracts\AttributeInterface;
use OpenApi\Contracts\ComponentNamerInterface;
use OpenApi\Tests\Concerns\ExpectsLogEntries;
use OpenApi\Tests\Fixtures\Generics;
use OpenApi\Utils\Pipeline;
use PHPUnit\Framework\TestCase;

final class GenericCopiesTest extends TestCase
{
    use ExpectsLogEntries;

    public function testAUseWithTypeArgumentsReferencesASchemaMadeForThem(): void
    {
        $schemas = $this->schemas(Generics\Uses::class, Generics\Box::class, Generics\Pair::class, Generics\Customer::class);
        $uses = $schemas['Uses']['properties'];

        $this->assertSame('#/components/schemas/Box-of.Customer', $uses['customer']['$ref']);
        $this->assertSame('#/components/schemas/Box-of.int', $uses['numbers']['items']['$ref']);
        $this->assertSame('#/components/schemas/Pair-of.string_Customer', $uses['pair']['$ref']);

        $this->assertSame(['$ref' => '#/components/schemas/Customer'], $schemas['Box-of.Customer']['properties']['value']);
        $this->assertSame(['$ref' => '#/components/schemas/Customer'], $schemas['Box-of.Customer']['properties']['history']['items']);
        $this->assertSame('integer', $schemas['Box-of.Customer']['properties']['count']['type']);
        $this->assertSame('integer', $schemas['Box-of.int']['properties']['value']['type']);
        $this->assertSame('string', $schemas['Pair-of.string_Customer']['properties']['first']['type']);
    }

    public function testTheClasssOwnSchemaStaysAsItWas(): void
    {
        $schemas = $this->schemas(Generics\Uses::class, Generics\Box::class, Generics\Pair::class, Generics\Customer::class);

        // the template says nothing, so the property's schema is empty
        $this->assertSame([], (array) $schemas['Box']['properties']['value']);
        $this->assertSame('#/components/schemas/Box', $schemas['Uses']['properties']['plain']['$ref']);
        $this->assertSame('#/components/schemas/Box', $schemas['Uses']['properties']['mixed']['$ref']);
    }

    public function testASchemaIsMadeOnceForEachSetOfTypeArguments(): void
    {
        $schemas = $this->schemas(Generics\Uses::class, Generics\Box::class, Generics\Pair::class, Generics\Customer::class);

        $this->assertSame(
            ['Box', 'Box-of.Box-of.bool', 'Box-of.Customer', 'Box-of.Status', 'Box-of.bool', 'Box-of.int', 'Customer', 'Pair', 'Pair-of.string_Customer', 'Status', 'Uses'],
            $this->sorted(array_keys($schemas)),
        );
    }

    public function testAMemberOfACopyCanAskForACopy(): void
    {
        $schemas = $this->schemas(Generics\Uses::class, Generics\Box::class, Generics\Pair::class, Generics\Customer::class);

        $this->assertSame('#/components/schemas/Box-of.Customer', $schemas['Pair-of.string_Customer']['properties']['second']['$ref']);
        $this->assertSame('#/components/schemas/Box-of.bool', $schemas['Box-of.Box-of.bool']['properties']['value']['$ref']);
        $this->assertSame('boolean', $schemas['Box-of.bool']['properties']['value']['type']);
    }

    /**
     * `ImpliedSchemas` runs before any copy is made, and finds the enum in the use's arguments.
     */
    public function testAnEnumACopyReadsATemplateAsGetsItsSchema(): void
    {
        $schemas = $this->schemas(Generics\Uses::class, Generics\Box::class, Generics\Pair::class, Generics\Customer::class);

        $this->assertSame(['active', 'closed'], $schemas['Status']['enum']);
        $this->assertSame(['$ref' => '#/components/schemas/Status'], $schemas['Box-of.Status']['properties']['value']);
    }

    public function testAMemberReadingATemplateIsNotNullableByItsDeclaredType(): void
    {
        $schemas = $this->schemas(Generics\Uses::class, Generics\Box::class, Generics\Pair::class, Generics\Customer::class);

        $this->assertArrayNotHasKey('nullable', $schemas['Box-of.int']['properties']['value']);
        $this->assertSame('integer', $schemas['Box-of.int']['properties']['value']['type']);
    }

    public function testAUseThatGetsNoCopyKeepsThePlainSchemaAndIsReported(): void
    {
        $box = Generics\Box::class;
        $customer = Generics\Customer::class;
        $refused = Generics\Refused::class;
        $this->expectLogEntry("Type \"{$box}<int, string>\" does not match the templates of its class, using the plain schema in {$refused}::\$tooMany", 'warning');
        $this->expectLogEntry("Type \"{$customer}<int>\" has type arguments, but its class declares no @template, using the plain schema in {$refused}::\$noTemplates", 'warning');
        // at each use, not only at the first with these arguments
        $this->expectLogEntry("Type \"{$customer}<int>\" has type arguments, but its class declares no @template, using the plain schema in {$refused}::\$noTemplatesAgain", 'warning');
        $this->expectLogEntry("Type \"{$box}<int|string>\" has type arguments a schema cannot be named for, using the plain schema in {$refused}::\$unnamed", 'warning');

        $schemas = $this->schemas(Generics\Refused::class, Generics\Box::class, Generics\Customer::class);

        $this->assertSame(['Box', 'Customer', 'Refused'], $this->sorted(array_keys($schemas)));
        $this->assertSame('#/components/schemas/Box', $schemas['Refused']['properties']['tooMany']['$ref']);
        $this->assertSame('#/components/schemas/Customer', $schemas['Refused']['properties']['noTemplates']['$ref']);
        $this->assertSame('#/components/schemas/Box', $schemas['Refused']['properties']['unnamed']['$ref']);
    }

    /**
     * A union with a class that is no component is dropped, and every member is still reported,
     * nested ones too. A use with type arguments in it makes no copy, which nothing would reference.
     */
    public function testADroppedUnionReportsEveryMemberAndMakesNoCopy(): void
    {
        $generics = 'OpenApi\\Tests\\Fixtures\\Generics';
        $reported = Generics\Reported::class;
        foreach (['nested', 'refused', 'copied'] as $property) {
            $this->expectLogEntry("Type \"{$generics}\\Unlisted\" in {$reported}::\${$property} matches no schema, omitted", 'error');
        }
        $this->expectLogEntry("Type \"{$generics}\\AlsoUnlisted\" in {$reported}::\$nested matches no schema, omitted", 'error');
        $this->expectLogEntry('Type "' . Generics\Customer::class . "<int>\" has type arguments, but its class declares no @template, using the plain schema in {$reported}::\$refused", 'warning');
        // `Flawed`'s own members, below
        $this->allowLogEntry(Generics\Flawed::class . '::');

        $schemas = $this->schemas(Generics\Reported::class, Generics\Flawed::class, Generics\Box::class, Generics\Customer::class);

        $this->assertArrayNotHasKey('Box-of.Customer', $schemas);
        $this->assertSame([], (array) $schemas['Reported']['properties']['copied']);
    }

    /**
     * A copy's members are its class's, typed again, and what is wrong in them is reported once.
     */
    public function testAMemberOfAGenericClassIsReportedOnceForAllItsCopies(): void
    {
        $flawed = Generics\Flawed::class;
        $this->expectLogEntry("Type \"OpenApi\\Tests\\Fixtures\\Generics\\Unlisted\" in {$flawed}::\$missing matches no schema, omitted", 'error');
        $this->expectLogEntry('Type "' . Generics\Customer::class . "<int>\" has type arguments, but its class declares no @template, using the plain schema in {$flawed}::\$refused", 'warning');
        // the dropped unions, above
        $this->allowLogEntry(Generics\Reported::class . '::');

        $schemas = $this->schemas(Generics\Reported::class, Generics\Flawed::class, Generics\Box::class, Generics\Customer::class);

        $this->assertArrayHasKey('Flawed-of.int', $schemas);
        $this->assertArrayHasKey('Flawed-of.string', $schemas);
    }

    /**
     * Two classes with one short name give two copies one name, as they would give their own schemas.
     */
    public function testTwoCopiesWithOneNameAreReported(): void
    {
        $box = Generics\Box::class;
        $this->expectLogEntry("Schema \"Box-of.Customer\" is declared more than once, the last one kept; they describe {$box}<" . Generics\Customer::class . "> and {$box}<" . Generics\Billing\Customer::class . '>, give one a name of its own or name components with QualifiedName', 'warning');

        $this->schemas(Generics\Collides::class, Generics\Box::class, Generics\Customer::class, Generics\Billing\Customer::class);
    }

    public function testCopiesAskingForCopiesEndWithAWarning(): void
    {
        // one level past the limit is refused, from inside the deepest copy
        $node = Generics\Node::class;
        $depth = CopyRule::MAX_DEPTH + 1;
        $this->expectLogEntry('Type "' . $node . '<' . str_repeat('list<', $depth) . 'int' . str_repeat('>', $depth + 1) . "\" nests its type arguments more than 5 deep, using the plain schema in {$node}::\$next", 'warning');

        $schemas = $this->schemas(Generics\Deep::class, Generics\Node::class);

        // `Deep`, `Node`, `Node<int>` and one copy for each level up to the limit
        $this->assertCount(3 + CopyRule::MAX_DEPTH, $schemas);
        $this->assertArrayHasKey('Node-of.' . str_repeat('list-of.', CopyRule::MAX_DEPTH) . 'int', $schemas);
    }

    public function testACopyOfAClassNamedByHandIsNamedAfterIt(): void
    {
        $schemas = $this->schemas(Generics\NamedUse::class, Generics\Named::class, Generics\Customer::class);

        $this->assertSame(['Customer', 'NamedUse', 'Parcel', 'Parcel-of.Customer'], $this->sorted(array_keys($schemas)));
        $this->assertSame('#/components/schemas/Parcel-of.Customer', $schemas['NamedUse']['properties']['parcel']['$ref']);
        $this->assertSame(['$ref' => '#/components/schemas/Customer'], $schemas['Parcel-of.Customer']['properties']['content']);
    }

    /**
     * A namer that leaves the type arguments out would give the copy the plain schema's name, and
     * the copy would replace it.
     */
    public function testACopyNamedAsThePlainSchemaIsRefused(): void
    {
        $box = Generics\Box::class;
        $collides = Generics\Collides::class;
        $this->expectLogEntry("Type \"{$box}<" . Generics\Customer::class . ">\" would be named as the schema of its class, using the plain schema in {$collides}::\$customer", 'warning');
        $this->expectLogEntry("Type \"{$box}<" . Generics\Billing\Customer::class . ">\" would be named as the schema of its class, using the plain schema in {$collides}::\$billingCustomer", 'warning');

        $namer = new class () implements ComponentNamerInterface {
            public function name(AttributeInterface $component): ?string
            {
                $reflector = $component->getReflector();

                return $reflector instanceof \ReflectionClass ? $reflector->getShortName() : null;
            }
        };
        $schemas = $this->schemas([Generics\Collides::class, Generics\Box::class, Generics\Customer::class, Generics\Billing\Customer::class], $namer);

        $this->assertSame(['BillingCustomer', 'Box', 'Collides', 'Customer'], $this->sorted(array_keys($schemas)));
        // the plain schema, with its template unbound
        $this->assertSame([], (array) $schemas['Box']['properties']['value']);
        $this->assertSame('#/components/schemas/Box', $schemas['Collides']['properties']['customer']['$ref']);
    }

    /**
     * @param class-string|list<class-string> $classes
     * @param class-string                    ...$more
     *
     * @return array<string, array<string, mixed>>
     */
    private function schemas(string|array $classes, string|ComponentNamerInterface ...$more): array
    {
        // a list of classes can be followed by a namer
        $namer = null;
        if (is_array($classes)) {
            $namer = $more[0] ?? null;
            $more = [];
        }
        /** @var list<class-string> $classes */
        $classes = [...(array) $classes, ...$more];

        // only schemas are built
        $this->allowLogEntry('Document is missing info');

        $builder = new Builder();
        if ($namer instanceof ComponentNamerInterface) {
            $builder->setComponentNamer($namer);
        }

        $result = $builder
            ->setMode(Mode::SPEC)
            ->setLogger($this->trackingLogger())
            // keep what no operation references
            ->withAugmenters(static fn (Pipeline $augmenters): Pipeline => $augmenters->remove(Augmenter\Cleanup::class))
            ->addSource(array_map(static fn (string $class): string => (string) (new \ReflectionClass($class))->getFileName(), $classes))
            ->build();
        $this->assertInstanceOf(Result::class, $result);

        return $result->toArray()['components']['schemas'] ?? [];
    }

    /**
     * @param list<string> $names
     *
     * @return list<string>
     */
    private function sorted(array $names): array
    {
        sort($names);

        return $names;
    }
}
