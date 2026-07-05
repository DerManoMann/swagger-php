<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

/**
 * OpenAPI 3.2.x compiler — superset of 3.1.
 *
 * Additions over 3.1:
 * - Tag: summary, parent, kind
 * - PathItem: query operation
 *
 * DTOs will gain these fields as the spec stabilises; this compiler
 * outputs them when present.
 */
class OpenApi32Compiler extends OpenApi31Compiler
{
    public function getVersion(): string
    {
        return '3.2.0';
    }

    public function compile(Specification $specification): array
    {
        $output = parent::compile($specification);
        $output['openapi'] = $specification->openapi->version ?? '3.2.0';

        return $output;
    }

    protected function compileTag(Tag $tag): array
    {
        $result = parent::compileTag($tag);

        // 3.2 fields — output when DTO gains them
        if (property_exists($tag, 'summary') && $tag->summary !== null) {
            $result['summary'] = $tag->summary;
        }
        if (property_exists($tag, 'parent') && $tag->parent !== null) {
            $result['parent'] = $tag->parent;
        }
        if (property_exists($tag, 'kind') && $tag->kind !== null) {
            $result['kind'] = $tag->kind;
        }

        return $result;
    }

    /**
     * @param  list<Operation>                     $operations
     * @return array<string, array<string, mixed>>
     */
    protected function compilePaths(array $operations): array
    {
        $paths = parent::compilePaths($operations);

        // 3.2 adds 'query' as a valid operation method — already handled
        // by the parent if Operation has method='query'

        return $paths;
    }
}
