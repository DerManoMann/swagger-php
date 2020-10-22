<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Parser;

use OpenApi\Annotations\Operation;
use OpenApi\Parser\AttributeAnnotationFactory;
use OpenApi\Parser\DocBlockAnnotationFactory;
use OpenApi\Parser\ReflectionAnalyser;
use OpenApi\Scanners\FileScanner;
use OpenApi\Tests\OpenApiTestCase;
use OpenApi\Util;

class ReflectionAnalyserTest extends OpenApiTestCase
{
    public function testSingleFileDocBlock()
    {
        $scanner = new FileScanner();
        $sources = $scanner->scan(Util::finder($this->fixtures('Apis/basic.php')), true);

        $analyser = new ReflectionAnalyser(new DocBlockAnnotationFactory());
        foreach ($sources as $type => $fqdnList) {
            foreach ($fqdnList as $fqdn => $filename) {
                include_once $filename;
                $analysis = $analyser->fromFqdn($fqdn);
            }
        }

        $analysis->process();
        $operations = $analysis->getAnnotationsOfType(Operation::class);
        $this->assertIsArray($operations);

        $this->assertTrue($analysis->validate());
        //file_put_contents(__DIR__.'/single_doc_block.yml', $analysis->openapi->toYaml());
        echo PHP_EOL.$analysis->openapi->toYaml().PHP_EOL;
    }

    /**
     * @requires PHP 8
     */
    public function testSingleFileAttributes()
    {

        $sources = (new FileScanner())->scan(Util::finder($this->fixtures('Apis/basic_php8.php')), true);
        $analyser = new ReflectionAnalyser(new AttributeAnnotationFactory());
        foreach ($sources as $type => $fqdnList) {
            foreach ($fqdnList as $fqdn => $filename) {
                require_once $filename;
                $analysis = $analyser->fromFqdn($fqdn);
            }
        }

        $analysis->process();
        $operations = $analysis->getAnnotationsOfType(Operation::class);
        $this->assertIsArray($operations);

        $this->assertTrue($analysis->validate());
        //file_put_contents(__DIR__.'/single_attributes.yml', $analysis->openapi->toYaml());
        echo PHP_EOL.$analysis->openapi->toYaml().PHP_EOL;
    }
}
