<?php

namespace OpenApiTests\Fixtures\StaticAnalyser;

use OpenApi\Analysis;
use OpenApi\Context;

require './vendor/autoload.php';

/* ====== APP =================== */
interface SomeInterface
{
}

trait OtherTrait
{

}

trait IdTrait
{
    use OtherTrait;

    <<Property('id')>>
    public function getId() {
        return $this->id;
    }
}

class Model
{

}

class Pet extends Model implements SomeInterface
{
    use IdTrait;

    protected $id;
}



/* ====== OA =================== */


class OpenApiAttribute
{
    public $name;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function __toString(): string
    {
        return '['.$this::class . ': name='.$this->name.']';
    }
}

class Operation extends OpenApiAttribute
{
    public $method;

    public function __construct($name, $method)
    {
        parent::__construct('O:'.$name);
        $this->method = $method;
    }

    public function __toString(): string
    {
        return '['.$this::class . ': name='.$this->name.', method='.$this->method.']';
    }
}

<<\PhpAttribute(self::TARGET_METHOD|self::TARGET_PROPERTY)>>
class Property extends OpenApiAttribute
{
    public function __construct($name)
    {
        parent::__construct($name);
    }
}

<<\PhpAttribute(self::TARGET_METHOD)>>
class Get extends Operation
{
    public function __construct($name)
    {
        parent::__construct('G:'.$name, 'GET');
    }
}

class Controller
{
    <<Get("foo")>>
    public function getPets() {

    }
}


$rc = new \ReflectionClass(Controller::class);

foreach ($rc->getMethods() as $method) {
    $operations = $method->getAttributes(Operation::class, \ReflectionAttribute::IS_INSTANCEOF);

    foreach ($operations as $operation) {
        $instance = $operation->newInstance();
        // [OpenApiTests\Fixtures\StaticAnalyser\Get: name=O:G:foo, method=GET]
        echo $instance . PHP_EOL;
    }
}


/**
 * Analyser using reflection ......
 * - how to scan?
 *   * by namespace?
 *   * what is in a file?  * class/interface/trait?
 *   * what is the name?
 *   * multiple entities per file, non matching names...
 *
 * - attributes are always attached to code, so no attribute only files...
 * - interogate registered classloader to find composer class loader to access class
 *   for namespace scanning
 * - parse files just for class/interface/trait detection, then use reflection
 * - DockBlockAnalyser vs. Scanner that provides class/etc + docblock details (either the current static
 *   or reflection)
 * - intermediate level between analyser and analysis
 * - rename a few things :)
 * - warning on unattached dockblocks?
 * - attributes as wrapper around annotations or standalone? 
 * - limited context without line info
 *
 * - testing: minimal  single file api to compare; PoC reflection analyser + abstraction (/docblock/attributes)
 */

$analysis = new Analysis();


$rc = new \ReflectionClass(Pet::class);
echo '=========================================================='.PHP_EOL;
echo 'Name: ' . $rc->getName().PHP_EOL;

$context = new Context(['class' => $rc->getName()]);
$classDefinition = [
    'class' => $rc->getName(),
    'extends' => null,
    'properties' => [],
    'methods' => [],
    'context' => $context,
];

if ($parentClass = $rc->getParentClass()) {
    echo '  extends: ' . $parentClass->getName().PHP_EOL;
    $classDefinition['extends'] = $parentClass->getName();
}

if ($interfaceNames = $rc->getInterfaceNames()) {
    foreach ($interfaceNames as $interfaceName) {
        echo '  implements: ' . $interfaceName.PHP_EOL;
    }
    $classDefinition['implements'] = $interfaceNames;
}

if ($traitNames = $rc->getTraitNames()) {
    foreach ($traitNames as $traitName) {
        echo '  uses: ' . $traitName.PHP_EOL;
    }
    $classDefinition['traits'][] = $traitNames;
}

foreach ($rc->getMethods() as $method) {
    $attributes = $method->getAttributes();

    foreach ($attributes as $attribute) {
        $instance = $attribute->newInstance();
        echo '    method "' . $method->getName() . '()" attr: '.$instance . PHP_EOL;
    }
    $classDefinition['methods'][$method->getName()] = new Context(['method' => $method->getName()], $context);
}

foreach ($rc->getProperties() as $property) {
    $attributes = $property->getAttributes();
    foreach ($attributes as $attribute) {
        $instance = $attribute->newInstance();
        echo '    property "' . $property->getName() . '" attr: '.$instance . PHP_EOL;
    }
    $classDefinition['properties'][$property->getName()] = new Context(['$property' => $property->getName()], $context);
}

var_dump($classDefinition);
