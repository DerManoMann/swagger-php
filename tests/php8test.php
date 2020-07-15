<?php declare(strict_types=1);

namespace OpenApi\Tests;

use OpenApi\Context;
use OpenApi\Parser\ReflectionAnalyser;
use OpenApi\Scanners\FileScanner;
use OpenApi\Tests\Fixtures\Apis\Operation;
use OpenApi\Tests\Fixtures\Apis\Php8Api;
use OpenApi\Tests\Fixtures\Apis\Php8Controller;
use OpenApi\Tests\Fixtures\Apis\Php8Pet;
use OpenApi\Util;

require './vendor/autoload.php';

$scanner = new FileScanner();
$sources = $scanner->scan(Util::finder(__DIR__.'/Fixtures/Apis/basic_php8.php'));

$analyser = new ReflectionAnalyser();
foreach ($sources as $type => $fqdnList) {
    foreach ($fqdnList as $fqdn) {
        $analysis = $analyser->fromFqdn($fqdn);
    }
}

$analysis->process();
//$analysis->validate();
echo $analysis->openapi->toYaml();

return;

$classes = [Php8Controller::class, Php8Api::class];

foreach ($classes as $class) {
    $rc = new \ReflectionClass($class);
    echo '=========================================================='.PHP_EOL;
    echo 'Name: '.$rc->getName().PHP_EOL;

    $attributes = $rc->getAttributes();

    foreach ($attributes as $attribute) {
        $instance = $attribute->newInstance();
        echo '    class  attr: '.$instance.PHP_EOL;
    }

    foreach ($rc->getMethods() as $method) {
        $operations = $method->getAttributes(Operation::class, \ReflectionAttribute::IS_INSTANCEOF);

        foreach ($operations as $operation) {
            $instance = $operation->newInstance();
            // [OpenApi\Tests\Fixtures\StaticAnalyser\Get: name=O:G:foo, method=GET]
            echo $instance.PHP_EOL;
        }
    }
    echo '=========================================================='.PHP_EOL;
}

return;
$rc = new \ReflectionClass(Php8Pet::class);
echo '=========================================================='.PHP_EOL;
echo 'Name: '.$rc->getName().PHP_EOL;

$context = new Context(['class' => $rc->getName()]);
$classDefinition = [
    'class' => $rc->getName(),
    'extends' => null,
    'properties' => [],
    'methods' => [],
    'context' => $context,
];

if ($parentClass = $rc->getParentClass()) {
    echo '  extends: '.$parentClass->getName().PHP_EOL;
    $classDefinition['extends'] = $parentClass->getName();
}

if ($interfaceNames = $rc->getInterfaceNames()) {
    foreach ($interfaceNames as $interfaceName) {
        echo '  implements: '.$interfaceName.PHP_EOL;
    }
    $classDefinition['implements'] = $interfaceNames;
}

if ($traitNames = $rc->getTraitNames()) {
    foreach ($traitNames as $traitName) {
        echo '  uses: '.$traitName.PHP_EOL;
    }
    $classDefinition['traits'][] = $traitNames;
}

foreach ($rc->getMethods() as $method) {
    $attributes = $method->getAttributes();

    foreach ($attributes as $attribute) {
        $instance = $attribute->newInstance();
        echo '    method "'.$method->getName().'()" attr: '.$instance.PHP_EOL;
    }
    $classDefinition['methods'][$method->getName()] = new Context(['method' => $method->getName()], $context);
}

foreach ($rc->getProperties() as $property) {
    $attributes = $property->getAttributes();
    foreach ($attributes as $attribute) {
        $instance = $attribute->newInstance();
        echo '    property "'.$property->getName().'" attr: '.$instance.PHP_EOL;
    }
    $classDefinition['properties'][$property->getName()] = new Context(['$property' => $property->getName()], $context);
}

//var_dump($classDefinition);
