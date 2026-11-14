<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Augmenter\Types;

use OpenApi\Augmenter;
use OpenApi\Builder;
use OpenApi\Builder\Mode;
use OpenApi\Builder\Result;
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
        $this->expectLogEntry("Type \"{$box}<int|string>\" has type arguments a schema cannot be named for, using the plain schema in {$refused}::\$unnamed", 'warning');

        $schemas = $this->schemas(Generics\Refused::class, Generics\Box::class, Generics\Customer::class);

        $this->assertSame(['Box', 'Customer', 'Refused'], $this->sorted(array_keys($schemas)));
        $this->assertSame('#/components/schemas/Box', $schemas['Refused']['properties']['tooMany']['$ref']);
        $this->assertSame('#/components/schemas/Customer', $schemas['Refused']['properties']['noTemplates']['$ref']);
        $this->assertSame('#/components/schemas/Box', $schemas['Refused']['properties']['unnamed']['$ref']);
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
        $node = Generics\Node::class;
        $this->expectLogEntry('Type "' . $node . '<' . str_repeat('list<', 10) . 'int' . str_repeat('>', 10) . ">\" is nested too deep in schemas made for type arguments, using the plain schema in {$node}::\$next", 'warning');

        $schemas = $this->schemas(Generics\Deep::class, Generics\Node::class);

        $this->assertCount(12, $schemas);
        $this->assertArrayHasKey('Node-of.' . str_repeat('list-of.', 9) . 'int', $schemas);
    }

    /**
     * @param class-string ...$classes
     *
     * @return array<string, array<string, mixed>>
     */
    private function schemas(string ...$classes): array
    {
        // only schemas are built
        $this->allowLogEntry('Document is missing info');

        $result = (new Builder())
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
