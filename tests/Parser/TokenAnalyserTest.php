<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Parser;

use OpenApi\Annotations\Operation;
use OpenApi\Annotations\Schema;
use OpenApi\Context;
use OpenApi\Parser\DocBlockParser;
use OpenApi\Parser\TokenAnalyser;
use OpenApi\Tests\Fixtures\Parser\User;
use OpenApi\Tests\OpenApiTestCase;

class TokenAnalyserTest extends OpenApiTestCase
{
    public function singleDefinitionCases()
    {
        return [
            'global-class' => ['class AClass {}', '\AClass', 'AClass', 'classes', 'class'],
            'global-interface' => ['interface AInterface {}', '\AInterface', 'AInterface', 'interfaces', 'interface'],
            'global-trait' => ['trait ATrait {}', '\ATrait', 'ATrait', 'traits', 'trait'],

            'namespaced-class' => ['namespace Foo; class AClass {}', '\Foo\AClass', 'AClass', 'classes', 'class'],
            'namespaced-interface' => ['namespace Foo; interface AInterface {}', '\Foo\AInterface', 'AInterface', 'interfaces', 'interface'],
            'namespaced-trait' => ['namespace Foo; trait ATrait {}', '\Foo\ATrait', 'ATrait', 'traits', 'trait'],
        ];
    }

    /**
     * @dataProvider singleDefinitionCases
     */
    public function testSingleDefinition($code, $fqdn, $name, $type, $typeKey)
    {
        $analysis = $this->analysisFromCode($code);

        $this->assertSame([$fqdn], array_keys($analysis->$type));
        $definition = $analysis->$type[$fqdn];
        $this->assertSame($name, $definition[$typeKey]);
        $this->assertTrue(!array_key_exists('extends', $definition) || !$definition['extends']);
        $this->assertSame([], $definition['properties']);
        $this->assertSame([], $definition['methods']);
    }

    public function extendsDefinitionCases()
    {
        return [
            'global-class' => ['class AClass extends Other {}', '\AClass', 'AClass', '\Other', 'classes', 'class'],
            'namespaced-class' => ['namespace Foo; class AClass extends \Other {}', '\Foo\AClass', 'AClass', '\Other', 'classes', 'class'],
            'global-class-explicit' => ['class AClass extends \Bar\Other {}', '\AClass', 'AClass', '\Bar\Other', 'classes', 'class'],
            'namespaced-class-explicit' => ['namespace Foo; class AClass extends \Bar\Other {}', '\Foo\AClass', 'AClass', '\Bar\Other', 'classes', 'class'],
            'global-class-use' => ['use Bar\Other; class AClass extends Other {}', '\AClass', 'AClass', '\Bar\Other', 'classes', 'class'],
            'namespaced-class-use' => ['namespace Foo; use Bar\Other; class AClass extends Other {}', '\Foo\AClass', 'AClass', '\Bar\Other', 'classes', 'class'],
            'namespaced-class-as' => ['namespace Foo; use Bar\Some as Other; class AClass extends Other {}', '\Foo\AClass', 'AClass', '\Bar\Some', 'classes', 'class'],
            'namespaced-class-same' => ['namespace Foo; class AClass extends Other {}', '\Foo\AClass', 'AClass', '\Foo\Other', 'classes', 'class'],

            'global-interface' => ['interface AInterface extends Other {}', '\AInterface', 'AInterface', ['\Other'], 'interfaces', 'interface'],
            'namespaced-interface' => ['namespace Foo; interface AInterface extends \Other {}', '\Foo\AInterface', 'AInterface', ['\Other'], 'interfaces', 'interface'],
            'global-interface-explicit' => ['interface AInterface extends \Bar\Other {}', '\AInterface', 'AInterface', ['\Bar\Other'], 'interfaces', 'interface'],
            'namespaced-interface-explicit' => ['namespace Foo; interface AInterface extends \Bar\Other {}', '\Foo\AInterface', 'AInterface', ['\Bar\Other'], 'interfaces', 'interface'],
            'global-interface-use' => ['use Bar\Other; interface AInterface extends Other {}', '\AInterface', 'AInterface', ['\Bar\Other'], 'interfaces', 'interface'],
            'namespaced-interface-use' => ['namespace Foo; use Bar\Other; interface AInterface extends Other {}', '\Foo\AInterface', 'AInterface', ['\Bar\Other'], 'interfaces', 'interface'],
            'namespaced-interface-use-multi' => ['namespace Foo; use Bar\Other; interface AInterface extends Other, \More {}', '\Foo\AInterface', 'AInterface', ['\Bar\Other', '\More'], 'interfaces', 'interface'],
            'namespaced-interface-as' => ['namespace Foo; use Bar\Some as Other; interface AInterface extends Other {}', '\Foo\AInterface', 'AInterface', ['\Bar\Some'], 'interfaces', 'interface'],
        ];
    }

    /**
     * @dataProvider extendsDefinitionCases
     */
    public function testExtendsDefinition($code, $fqdn, $name, $extends, $type, $typeKey)
    {
        $analysis = $this->analysisFromCode($code);

        $this->assertSame([$fqdn], array_keys($analysis->$type));
        $definition = $analysis->$type[$fqdn];
        $this->assertSame($name, $definition[$typeKey]);
        $this->assertSame($extends, $definition['extends']);
    }

    public function usesDefinitionCases()
    {
        return [
            'global-class-use' => ['class AClass { use Other; }', '\AClass', 'AClass', ['\Other'], 'classes', 'class'],
            'namespaced-class-use' => ['namespace Foo; class AClass { use \Other; }', '\Foo\AClass', 'AClass', ['\Other'], 'classes', 'class'],
            'namespaced-class-use-namespaced' => ['namespace Foo; use Bar\Other; class AClass { use Other; }', '\Foo\AClass', 'AClass', ['\Bar\Other'], 'classes', 'class'],
            'namespaced-class-use-namespaced-as' => ['namespace Foo; use Bar\Other as Some; class AClass { use Some; }', '\Foo\AClass', 'AClass', ['\Bar\Other'], 'classes', 'class'],

            'global-trait-use' => ['trait ATrait { use Other; }', '\ATrait', 'ATrait', ['\Other'], 'traits', 'trait'],
            'namespaced-trait-use' => ['namespace Foo; trait ATrait { use \Other; }', '\Foo\ATrait', 'ATrait', ['\Other'], 'traits', 'trait'],
            'namespaced-trait-use-explicit' => ['namespace Foo; trait ATrait { use \Bar\Other; }', '\Foo\ATrait', 'ATrait', ['\Bar\Other'], 'traits', 'trait'],
            'namespaced-trait-use-multi' => ['namespace Foo; trait ATrait { use \Other; use \More; }', '\Foo\ATrait', 'ATrait', ['\Other', '\More'], 'traits', 'trait'],
            'namespaced-trait-use-mixed' => ['namespace Foo; use Bar\Other; trait ATrait { use Other, \More; }', '\Foo\ATrait', 'ATrait', ['\Bar\Other', '\More'], 'traits', 'trait'],
            'namespaced-trait-use-as' => ['namespace Foo; use Bar\Other as Some; trait ATrait { use Some; }', '\Foo\ATrait', 'ATrait', ['\Bar\Other'], 'traits', 'trait'],
        ];
    }

    /**
     * @dataProvider usesDefinitionCases
     */
    public function testUsesDefinition($code, $fqdn, $name, $traits, $type, $typeKey)
    {
        $analysis = $this->analysisFromCode($code);

        $this->assertSame([$fqdn], array_keys($analysis->$type));
        $definition = $analysis->$type[$fqdn];
        $this->assertSame($name, $definition[$typeKey]);
        $this->assertSame($traits, $definition['traits']);
    }

    public function testWrongCommentType()
    {
        $analyser = new TokenAnalyser();
        $this->assertOpenApiLogEntryContains('Annotations are only parsed inside `/**` DocBlocks');
        $analyser->fromCode("<?php\n/*\n * @OA\Parameter() */", new Context());
    }

    public function testIndentationCorrection()
    {
        $analysis = $this->analysisFromFixtures('Analysers/routes.php');
        $this->assertCount(20, $analysis->annotations);
    }

    public function testThirdPartyAnnotations()
    {
        $backup = DocBlockParser::$whitelist;
        DocBlockParser::$whitelist = ['OpenApi\Annotations\\'];
        $defaultAnalysis = $this->analysisFromFixtures('ThirdPartyAnnotations.php');
        $this->assertCount(3, $defaultAnalysis->annotations, 'Only read the @OA annotations, skip the others.');

        // Allow the analyser to parse 3rd party annotations, which might
        // contain useful info that could be extracted with a custom processor
        DocBlockParser::$whitelist[] = 'AnotherNamespace\Annotations';
        $openapi = \OpenApi\scan($this->fixtures('ThirdPartyAnnotations.php'));
        $this->assertSame('api/3rd-party', $openapi->paths[0]->path);
        $this->assertCount(4, $openapi->_unmerged);
        DocBlockParser::$whitelist = $backup;
        $analysis = $openapi->_analysis;
        $annotations = $analysis->getAnnotationsOfType('AnotherNamespace\Annotations\Unrelated');
        $this->assertCount(4, $annotations);
        $context = $analysis->getContext($annotations[0]);
        $this->assertInstanceOf('OpenApi\Context', $context);
        $this->assertSame('ThirdPartyAnnotations', $context->class);
        $this->assertSame('\OpenApi\Tests\Fixtures\ThirdPartyAnnotations', $context->fullyQualifiedName($context->class));
        $this->assertCount(1, $context->annotations);
    }

    public function testSyntaxPhp7()
    {
        try {
            $analyser = (new TokenAnalyser())->fromFile($this->fixtures('Analysers/php7.php')[0]);
            $this->assertNotNull($analyser);
        } catch (\Throwable $t) {
            $this->fail("Analyser produced an error: {$t->getMessage()}");
        }
    }

    public function testSyntaxPhp8()
    {
        if (PHP_MAJOR_VERSION < 8) {
            $this->markTestSkipped('Requires PHP8');
        }

        try {
            $analyser = (new TokenAnalyser())->fromFile($this->fixtures('Apis/basic_php8.php')[0]);
            $this->assertNotNull($analyser);
        } catch (\Throwable $t) {
            $this->fail("Analyser produced an error: {$t->getMessage()}");
        }
    }

    /**
     * dataprovider.
     */
    public function descriptions()
    {
        return [
            'class' => [
                ['classes', 'class'],
                'User',
                'Parser/User.php',
                '\OpenApi\Tests\Fixtures\Parser\User',
                '\OpenApi\Tests\Fixtures\Parser\Sub\SubClass',
                ['getFirstName'],
                null,
                ['\OpenApi\Tests\Fixtures\Parser\HelloTrait'], // use ... as ...
            ],
            'interface' => [
                ['interfaces', 'interface'],
                'UserInterface',
                'Parser/UserInterface.php',
                '\OpenApi\Tests\Fixtures\Parser\UserInterface',
                ['\OpenApi\Tests\Fixtures\Parser\OtherInterface'],
                null,
                null,
                null,
            ],
            'trait' => [
                ['traits', 'trait'],
                'HelloTrait',
                'Parser/HelloTrait.php',
                '\OpenApi\Tests\Fixtures\Parser\HelloTrait',
                null,
                null,
                null,
                ['\OpenApi\Tests\Fixtures\Parser\OtherTrait', '\OpenApi\Tests\Fixtures\Parser\AsTrait'],
            ],
        ];
    }

    /**
     * @dataProvider descriptions
     */
    public function testDescription($type, $name, $fixture, $fqdn, $extends, $methods, $interfaces, $traits)
    {
        $analysis = $this->analysisFromFixtures($fixture);

        list($pType, $sType) = $type;
        $description = $analysis->$pType[$fqdn];

        $this->assertSame($name, $description[$sType]);
        if (null !== $extends) {
            $this->assertSame($extends, $description['extends']);
        }
        if (null !== $methods) {
            $this->assertSame($methods, array_keys($description['methods']));
        }
        if (null !== $interfaces) {
            $this->assertSame($interfaces, $description['interfaces']);
        }
        if (null !== $traits) {
            $this->assertSame($traits, $description['traits']);
        }
    }

    public function testNamespacedConstAccess()
    {
        $analysis = $this->analysisFromFixtures('Parser/User.php');
        $schemas = $analysis->getAnnotationsOfType(Schema::class, true);

        $this->assertCount(1, $schemas);
        $this->assertEquals(User::CONSTANT, $schemas[0]->example);
    }

    public function testSingleFile()
    {
        $analysis = $this->analysisFromFixtures('Apis/basic.php');
        $analysis->process();
        $operations = $analysis->getAnnotationsOfType(Operation::class);
        $this->assertIsArray($operations);

        $this->assertTrue($analysis->validate());
    }
}
