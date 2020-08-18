<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Parser;

use OpenApi\Analysis;
use OpenApi\Context;

/**
 * OpenApi docblock analyser using reflection.
 *
 * Due to the nature of reflection this requires all related classes
 * to be auto-loadable.
 */
class ReflectionAnalyser
{
    protected $analysis;
    protected $annotationFactory;

    public function __construct(?AnnotationFactoryInterface $annotationFactory = null, ?Analysis $analysis = null)
    {
        $this->annotationFactory = $annotationFactory ?: new DocBlockAnnotationFactory();
        $this->analysis = $analysis ?: new Analysis();
    }

    public function fromFqdn(string $fqdn): Analysis
    {
        $analysis = $this->analysis;

        $rc = new \ReflectionClass($fqdn);

        $contextType = $this->contextType($rc);

        $context = new Context([$contextType => $rc->getShortName()]);
        if ($namespace = $rc->getNamespaceName()) {
            $context->namespace = $namespace;
        }

        $definition = [
            $contextType => $rc->getShortName(),
            'extends' => null,
            'implements' => [],
            'traits' => [],
            'properties' => [],
            'methods' => [],
            'context' => $context,
        ];
        $analysis->addAnnotations($this->annotationFactory->build($rc, $context), $context);

        if ($parentClass = $rc->getParentClass()) {
            $definition['extends'] = '\\'.$parentClass->getName();
        }

        if ($interfaceNames = $rc->getInterfaceNames()) {
            $definition['implements'] = array_map(function ($name) {
                return '\\'.$name;
            }, $interfaceNames);
        }

        if ($traitNames = $rc->getTraitNames()) {
            $definition['traits'] = array_map(function ($name) {
                return '\\'.$name;
            }, $traitNames);
        }

        foreach ($rc->getMethods() as $method) {
            $definition['methods'][$method->getName()] = $ctx = new Context(['method' => $method->getName()], $context);
            $analysis->addAnnotations($this->annotationFactory->build($method, $ctx), $ctx);
        }

        foreach ($rc->getProperties() as $property) {
            // static, type, nullable

            $definition['properties'][$property->getName()] = $ctx = new Context(['property' => $property->getName()], $context);
            if ($property->isStatic()) {
                $ctx->static = true;
            }
            $analysis->addAnnotations($this->annotationFactory->build($property, $ctx), $ctx);
        }

        $addDefinition = 'add'.ucfirst($contextType).'Definition';
        $this->analysis->{$addDefinition}($definition);

        return $this->analysis;
    }

    protected function contextType(\ReflectionClass $rc)
    {
        return $rc->isInterface() ? 'interface' : ($rc->isTrait() ? 'trait' : 'class');
    }
}
