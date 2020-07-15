<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Tests\Parser;

use OpenApi\Annotations\Operation;
use OpenApi\Parser\AttributeAnnotationFactory;
use OpenApi\Parser\ReflectionAnalyser;
use OpenApi\Processors\OperationId;
use OpenApi\Scanners\FileScanner;
use OpenApi\Tests\OpenApiTestCase;
use OpenApi\Util;

class ReflectionAnalyserTest extends OpenApiTestCase
{
    public function testSingleFileDocBlock()
    {
        $scanner = new FileScanner();
        $sources = $scanner->scan($fixtures = Util::finder($this->fixtures('Apis/basic.php')), true);

        $analyser = new ReflectionAnalyser();
        foreach ($sources as $type => $fqdnList) {
            foreach ($fqdnList as $fqdn) {
                $analysis = $analyser->fromFqdn($fqdn);
            }
        }

        $analysis->process();
        $operations = $analysis->getAnnotationsOfType(Operation::class);
        $this->assertIsArray($operations);

        $this->assertTrue($analysis->validate());
        //echo PHP_EOL.$analysis->openapi->toYaml().PHP_EOL;
        /*

openapi: 3.0.0
info:
  title: 'Basic single file API'
  version: 1.0.0
paths:
  '/products/{product_id}':
    get:
      tags:
        - Products
      operationId: 'OpenApi\Tests\Fixtures\Apis\ProductController::getProduct'
      responses:
        '200':
          description: 'successful operation'
          content:
            application/json:
              schema:
                $ref: '#/components/schemas/Product'
components:
  schemas:
    Product:
      title: Product
      description: Product
      allOf:
        -
          $ref: '#/components/schemas/NameTrait'
        -
          properties:
            id:
              description: 'The id.'
              format: int64
              example: 1
            name:
              description: 'The name.'
    NameTrait:
      properties:
        name:
          description: 'The name.'
      type: object

        */
    }

    public function testSingleFileAttributes()
    {
        if (PHP_MAJOR_VERSION < 8) {
            $this->markTestSkipped('Requires PHP8');
        }

        $sources = (new FileScanner())->scan(Util::finder($this->fixtures('Apis/basic_php8.php')), true);
        $analyser = new ReflectionAnalyser(new AttributeAnnotationFactory());
        foreach ($sources as $type => $fqdnList) {
            foreach ($fqdnList as $fqdn) {
                $analysis = $analyser->fromFqdn($fqdn);
            }
        }

        $analysis->process();
        $operations = $analysis->getAnnotationsOfType(Operation::class);
        $this->assertIsArray($operations);

        //echo PHP_EOL . $analysis->openapi->toYaml() . PHP_EOL;
        /*

info:
  title: 'Basic single file PHP8 API'
  version: 1.0.0
paths:
  '/products/{product_id}':
    get:
      tags:
        - Products
      operationId: 'OpenApi\Tests\Fixtures\Apis\Php8ProductController::getProduct'
      responses:
        '200':
          description: 'successful operation'
        '401':
          description: oops
components:
  schemas:
    Php8Product:
      title: Product
      description: Product
    Php8NameTrait: {  }
  responses:
    '200':
      description: 'successful operation'
    '401':
      description: oops

         */
        $this->assertTrue($analysis->validate());
    }
}
