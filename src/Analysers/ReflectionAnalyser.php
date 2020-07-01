<?php

/**
 * @license Apache 2.0
 */

namespace OpenApi\Analysers;

use OpenApi\Analyser;
use OpenApi\Analysis;
use OpenApi\Context;

/**
 * OpenApi dockblock analyser using reflection.
 */
class ReflectionAnalyser
{
    protected $analysis;
    protected $analyser;

    public function __construct(?Analysis $analysis = null, ?Analyser $analyser = null)
    {
        $this->analysis = $analysis ?: new Analysis();
        $this->analyser = $analyser ?: new Analyser();
    }

    public function analyse(string $fqdn): Analysis
    {
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
        $this->analyseComment($context, $rc->getDocComment());

        if ($parentClass = $rc->getParentClass()) {
            $definition['extends'] = '\\' . $parentClass->getName();
        }

        if ($interfaceNames = $rc->getInterfaceNames()) {
            $definition['implements'] = array_map(function ($name) { return '\\'.$name; }, $interfaceNames);
        }

        if ($traitNames = $rc->getTraitNames()) {
            $definition['traits'] = array_map(function ($name) { return '\\'.$name; }, $traitNames);
        }

        foreach ($rc->getMethods() as $method) {
            $definition['methods'][$method->getName()] = $ctx = new Context(['method' => $method->getName()], $context);
            $this->analyseComment($ctx, $method->getDocComment());
        }

        foreach ($rc->getProperties() as $property) {
            // static, type, nullable

            $definition['properties'][$property->getName()] = $ctx = new Context(['property' => $property->getName()], $context);
            if ($property->isStatic()) {
                $ctx->static = true;
            }
            $this->analyseComment($ctx, $property->getDocComment());
        }

        $addDefinition = 'add' . ucfirst($contextType).'Definition';
        $this->analysis->{$addDefinition}($definition);

        return $this->analysis;
    }

    protected function contextType(\ReflectionClass $rc)
    {
        return $rc->isInterface() ? 'interface' : ($rc->isTrait() ? 'trait' : 'class');
    }

    protected function analyseComment(Context $context, $comment)
    {
        if ($comment) {
            $this->analysis->addAnnotations($this->analyser->fromComment($comment, $context), $context);
        }
    }

}
