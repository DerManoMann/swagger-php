<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Augmenter;

use OpenApi\Augmenter;
use OpenApi\Spec as OA;
use OpenApi\Specification;
use OpenApi\Tests\Concerns\ExpectsLogEntries;
use OpenApi\Tests\Fixtures;
use PHPUnit\Framework\TestCase;

final class GenericsTest extends TestCase
{
    use ExpectsLogEntries;

    public function testArgumentsThatCannotBeReadAreReported(): void
    {
        $this->expectLogEntry('Type arguments "Unknown" for OpenApi\\Tests\\Fixtures\\Type\\Template bind no template, so no schema is made for them; write each class with its namespace');

        $specification = $this->refTo(Fixtures\Type\Template::class, 'Unknown');
        $generics = new Augmenter\Generics();
        $generics->setLogger($this->trackingLogger());
        $generics($specification);

        $this->assertCount(2, $specification->schemas);
    }

    public function testArgumentsForAClassWithoutTemplatesAreReported(): void
    {
        $this->expectLogEntry('Type arguments "int" for OpenApi\\Tests\\Fixtures\\Augmenter\\RefTarget bind no template, so no schema is made for them; the class declares no @template');

        $specification = $this->refTo(Fixtures\Augmenter\RefTarget::class, 'int');
        $generics = new Augmenter\Generics();
        $generics->setLogger($this->trackingLogger());
        $generics($specification);

        $this->assertCount(2, $specification->schemas);
    }

    public function testAPropertyTypedWithArgumentsGetsACopy(): void
    {
        $specification = $this->holding(new OA\Property(property: 'box'));

        (new Augmenter\Generics())($specification);

        $this->assertCount(3, $specification->schemas);
    }

    /**
     * `Types` leaves a schema's own type in place, so the PHP type asks for nothing.
     */
    public function testAPropertyWithATypeOfItsOwnGetsNoCopy(): void
    {
        $specification = $this->holding(new OA\Property(property: 'box', schema: new OA\Schema(type: 'object')));

        (new Augmenter\Generics())($specification);

        $this->assertCount(2, $specification->schemas);
    }

    /**
     * `Types` types a parameter by its own reflector, so a method's return type asks for nothing.
     */
    public function testAParameterOnAMethodGetsNoCopy(): void
    {
        $specification = $this->holding();
        $specification->parameters[] = (new OA\Parameter(name: 'box', in: 'query'))
            ->setReflector(new \ReflectionMethod(Fixtures\Augmenter\GenericBoxHolder::class, 'boxed'));

        (new Augmenter\Generics())($specification);

        $this->assertCount(2, $specification->schemas);
    }

    /**
     * The copy for `RefTarget`, which has no schema here, is typed once, with its template bound, and
     * that use's `null` is not added afterwards.
     */
    public function testAnArgumentWithoutASchemaLeavesTheMemberUntyped(): void
    {
        $this->expectLogEntry('No schema for OpenApi\\Tests\\Fixtures\\Augmenter\\RefTarget');

        $specification = $this->holding(new OA\Property(property: 'undescribed'));
        $logger = $this->trackingLogger();
        foreach ([new Augmenter\Generics(), new Augmenter\Types()] as $augmenter) {
            $augmenter->setLogger($logger);
            $augmenter($specification);
        }

        $value = $specification->schemas[2]->properties[0]->schema ?? null;
        $this->assertInstanceOf(OA\Schema::class, $value);
        $this->assertNull($value->type);
        $this->assertNull($value->nullable);
    }

    /**
     * A plain schema for the generic box, and one for its holder with the property given.
     */
    protected function holding(?OA\Property $property = null): Specification
    {
        $box = new OA\Schema(properties: array_map(
            static fn (string $name): OA\Property => (new OA\Property(property: $name))->setReflector(new \ReflectionProperty(Fixtures\Augmenter\GenericBox::class, $name)),
            ['value', 'count'],
        ));
        $holder = new OA\Schema(properties: $property instanceof OA\Property
            ? [$property->setReflector(new \ReflectionProperty(Fixtures\Augmenter\GenericBoxHolder::class, (string) $property->property))]
            : []);

        $specification = new Specification();
        $specification->schemas[] = $box->setReflector(new \ReflectionClass(Fixtures\Augmenter\GenericBox::class));
        $specification->schemas[] = $holder->setReflector(new \ReflectionClass(Fixtures\Augmenter\GenericBoxHolder::class));

        return $specification;
    }

    /**
     * A plain schema for the class, and one referencing it with type arguments.
     *
     * @param class-string $class
     */
    protected function refTo(string $class, string $arguments): Specification
    {
        $specification = new Specification();
        $specification->schemas[] = (new OA\Schema())->setReflector(new \ReflectionClass($class));
        $specification->schemas[] = new OA\Schema(schema: 'Holder', properties: [
            new OA\Property(property: 'value', schema: new OA\Schema\Ref(ref: $class, qualifiers: ['of' => $arguments])),
        ]);

        return $specification;
    }
}
