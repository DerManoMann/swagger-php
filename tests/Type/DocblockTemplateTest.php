<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Type;

use OpenApi\Specification\ComponentKey;
use OpenApi\Tests\Concerns\ExpectsLogEntries;
use OpenApi\Tests\Fixtures\Type\Template;
use OpenApi\Type\TypeResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DocblockTemplateTest extends TestCase
{
    use ExpectsLogEntries;

    /**
     * @return iterable<string, array{string}>
     */
    public static function templateCases(): iterable
    {
        yield 'unbounded' => ['results'];
        yield 'bounded' => ['bounded'];
    }

    #[DataProvider('templateCases')]
    public function testClassTemplateIsNoUnknownClass(string $property): void
    {
        $resolver = new TypeResolver();
        $resolver->setLogger($this->trackingLogger());

        $type = $resolver->resolve(new \ReflectionProperty(Template::class, $property));

        $this->assertSame('array', $type?->type);
    }

    /**
     * Type-info reads an unbounded template as `mixed`, which an intersection does not take.
     */
    public function testAnIntersectionTypeInfoRejectsIsReported(): void
    {
        $this->expectLogEntry('Unsupported docblock type "(T & \\Countable)" on');

        $resolver = new TypeResolver();
        $resolver->setLogger($this->trackingLogger());

        $this->assertNull($resolver->getDocblockType(new \ReflectionProperty(Template::class, 'unbounded')));
    }

    public function testAnArrayShapeKeepsItsShape(): void
    {
        $resolver = new TypeResolver();
        $templates = $resolver->templates(new ComponentKey(Template::class, ['of' => 'string']));

        $type = $resolver->resolveWithTemplates(new \ReflectionProperty(Template::class, 'shaped'), $templates);

        $this->assertSame('object', $type?->type);
        $this->assertSame('string', $type->properties['item']->type ?? null);
        $this->assertSame('integer', $type->properties['id']->type ?? null);
    }

    public function testARefinedArgumentKeepsItsRefinement(): void
    {
        $resolver = new TypeResolver();
        $templates = $resolver->templates(new ComponentKey(Template::class, ['of' => 'int<0, 100>']));

        $type = $resolver->resolveWithTemplates(new \ReflectionProperty(Template::class, 'results'), $templates);

        $this->assertSame(0, $type?->items?->minimum);
        $this->assertSame(100, $type->items->maximum);
    }

    public function testATemplateIsReplacedByItsArgument(): void
    {
        $resolver = new TypeResolver();
        $templates = $resolver->templates(new ComponentKey(Template::class, ['of' => 'int']));

        $type = $resolver->resolveWithTemplates(new \ReflectionProperty(Template::class, 'results'), $templates);

        $this->assertSame('array', $type?->type);
        $this->assertSame('integer', $type->items?->type);
    }

    public function testAUnionTakingMixedIsMixed(): void
    {
        $resolver = new TypeResolver();
        $templates = $resolver->templates(new ComponentKey(Template::class, ['of' => 'mixed']));

        $type = $resolver->resolveWithTemplates(new \ReflectionProperty(Template::class, 'either'), $templates);

        $this->assertNull($type?->type);
    }

    public function testAnArgumentTheTypeCannotTakeIsReported(): void
    {
        $this->expectLogEntry('Cannot use the type arguments in "B&Countable"');

        $resolver = new TypeResolver();
        $resolver->setLogger($this->trackingLogger());
        $templates = $resolver->templates(new ComponentKey(Template::class, ['of' => 'int, int']));

        $type = $resolver->resolveWithTemplates(new \ReflectionProperty(Template::class, 'counted'), $templates);

        $this->assertNull($type?->type);
    }
}
