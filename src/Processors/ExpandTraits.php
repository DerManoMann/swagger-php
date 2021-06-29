<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Processors;

use OpenApi\Analysis;
use OpenApi\Annotations\Schema;
use OpenApi\Generator;

class ExpandTraits
{
    use MergeTrait;

    public function __invoke(Analysis $analysis)
    {
        /** @var Schema[] $schemas */
        $schemas = $analysis->getAnnotationsOfType(Schema::class, true);

        foreach ($schemas as $schema) {
            if ($schema->_context->is('class') || $schema->_context->is('trait')) {
                $source = $schema->_context->class ?: $schema->_context->trait;
                $traits = $analysis->getTraitsOfClass($schema->_context->fullyQualifiedName($source), true);
                $existing = [];
                foreach ($traits as $trait) {
                    $traitSchema = $analysis->getSchemaForSource($trait['context']->fullyQualifiedName($trait['trait']));
                    if ($traitSchema) {
                        $refPath = $traitSchema->schema !== Generator::UNDEFINED ? $traitSchema->schema : $trait['trait'];
                        $this->inheritFrom($schema, $traitSchema, $refPath, $trait['context']->_context);
                    } else {
                        if ($schema->_context->is('class')) {
                            $this->mergeAnnotations($schema, $trait, $existing);
                            $this->mergeProperties($schema, $trait, $existing);
                            $this->mergeMethods($schema, $trait, $existing);
                        }
                    }
                }
            }
        }
    }
}
