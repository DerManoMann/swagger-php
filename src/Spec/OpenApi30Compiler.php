<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

use OpenApi\Generator;

/**
 * Downgrade compiler: outputs OpenAPI 3.0.x from the canonical 3.1+ internal model.
 *
 * Key differences from 3.1:
 * - type must be string (not array); nullable is a keyword
 * - exclusiveMinimum/Maximum is boolean (not numeric)
 * - $ref cannot have siblings
 * - No webhooks, const, examples array, unevaluatedProperties, if/then/else, prefixItems
 */
class OpenApi30Compiler extends OpenApi31Compiler
{
    protected CompilerDiagnostics $diagnostics;

    public function getVersion(): string
    {
        return '3.0.3';
    }

    public function compile(Specification $specification): array
    {
        $this->diagnostics = new CompilerDiagnostics();

        $output = ['openapi' => $specification->openapi->version ?? '3.0.3'];

        if ($specification->info !== null) {
            $output['info'] = $this->compileInfo($specification->info);
        }

        if ($specification->servers) {
            $output['servers'] = array_map([$this, 'compileServer'], $specification->servers);
        }

        $paths = $this->compilePaths($specification->operations);
        if ($paths) {
            $output['paths'] = $paths;
        }

        if ($specification->operations) {
            foreach ($specification->operations as $op) {
                if ($op->webhook !== null) {
                    $this->diagnostics->warnings[] = new Diagnostic('Webhooks are not supported in OpenAPI 3.0');
                    break;
                }
            }
        }

        if ($specification->tags) {
            $output['tags'] = array_map([$this, 'compileTag'], $specification->tags);
        }

        if ($specification->openapi->security) {
            $output['security'] = $specification->openapi->security;
        }

        if ($specification->externalDocs) {
            $output['externalDocs'] = $this->compileExternalDocs($specification->externalDocs[0]);
        }

        $components = $this->compileComponents($specification);
        if ($components) {
            $output['components'] = $components;
        }

        return $output;
    }

    public function validate(Specification $specification): CompilerDiagnostics
    {
        $diagnostics = parent::validate($specification);

        foreach ($specification->operations as $op) {
            if ($op->webhook !== null) {
                $diagnostics->warnings[] = new Diagnostic('Webhooks are not supported in OpenAPI 3.0');
            }
        }

        return $diagnostics;
    }

    public function getDiagnostics(): CompilerDiagnostics
    {
        return $this->diagnostics;
    }

    protected function compileSchema(Schema|string $schema): array
    {
        if (is_string($schema)) {
            return ['$ref' => $schema];
        }

        if ($schema->ref !== null) {
            return ['$ref' => $schema->ref];
        }

        $type = $schema->type;
        $nullable = $schema->nullable;

        // Convert type array to single type + nullable
        if (is_array($type)) {
            if (in_array('null', $type, true)) {
                $nullable = true;
                $type = array_values(array_filter($type, fn ($t) => $t !== 'null'));
            }
            $type = count($type) === 1 ? $type[0] : null;
        } elseif ($nullable === true) {
            // Already have nullable as boolean — keep type as-is
        }

        // Convert numeric exclusiveMinimum/Maximum to boolean form
        $minimum = $schema->minimum;
        $maximum = $schema->maximum;
        $exclusiveMinimum = null;
        $exclusiveMaximum = null;

        if (is_numeric($schema->exclusiveMinimum)) {
            $minimum = $schema->exclusiveMinimum;
            $exclusiveMinimum = true;
        } elseif ($schema->exclusiveMinimum === true) {
            $exclusiveMinimum = true;
        }

        if (is_numeric($schema->exclusiveMaximum)) {
            $maximum = $schema->exclusiveMaximum;
            $exclusiveMaximum = true;
        } elseif ($schema->exclusiveMaximum === true) {
            $exclusiveMaximum = true;
        }

        // const → enum
        $enum = $schema->enum;
        if ($schema->const !== Generator::UNDEFINED) {
            $enum = [$schema->const];
        }

        $result = $this->filter([
            'type' => $type,
            'format' => $schema->format,
            'title' => $schema->title,
            'description' => $schema->description,
            'nullable' => $nullable,
            'enum' => $enum,

            // String
            'minLength' => $schema->minLength,
            'maxLength' => $schema->maxLength,
            'pattern' => $schema->pattern,

            // Numeric
            'minimum' => $minimum,
            'maximum' => $maximum,
            'exclusiveMinimum' => $exclusiveMinimum,
            'exclusiveMaximum' => $exclusiveMaximum,
            'multipleOf' => $schema->multipleOf,

            // Array
            'items' => $schema->items !== null ? $this->compileSchema($schema->items) : null,
            'minItems' => $schema->minItems,
            'maxItems' => $schema->maxItems,
            'uniqueItems' => $schema->uniqueItems,

            // Object
            'properties' => $schema->properties !== null ? $this->compileProperties($schema->properties) : null,
            'required' => $schema->required,
            'additionalProperties' => $schema->additionalProperties !== null ? (is_bool($schema->additionalProperties) ? $schema->additionalProperties : $this->compileSchema($schema->additionalProperties)) : null,
            'minProperties' => $schema->minProperties,
            'maxProperties' => $schema->maxProperties,

            // Composition
            'allOf' => $schema->allOf !== null ? array_map([$this, 'compileSchema'], $schema->allOf) : null,
            'anyOf' => $schema->anyOf !== null ? array_map([$this, 'compileSchema'], $schema->anyOf) : null,
            'oneOf' => $schema->oneOf !== null ? array_map([$this, 'compileSchema'], $schema->oneOf) : null,
            'not' => $schema->not !== null ? $this->compileSchema($schema->not) : null,

            // Meta
            'deprecated' => $schema->deprecated,
            'readOnly' => $schema->readOnly,
            'writeOnly' => $schema->writeOnly,

            // OpenAPI extensions on schema
            'discriminator' => $schema->discriminator !== null ? $this->compileDiscriminator($schema->discriminator) : null,
            'externalDocs' => $schema->externalDocs !== null ? $this->compileExternalDocs($schema->externalDocs) : null,
            'xml' => $schema->xml !== null ? $this->compileXml($schema->xml) : null,
        ], $schema);

        // default/example — null is a valid JSON value
        if ($schema->default !== Generator::UNDEFINED) {
            $result['default'] = $schema->default;
        }
        // examples array → single example (3.0 only supports singular)
        if ($schema->example !== Generator::UNDEFINED) {
            $result['example'] = $schema->example;
        } elseif ($schema->examples !== null && $schema->examples !== []) {
            $result['example'] = $schema->examples[0];
        }

        return $result;
    }

    protected function compileLicense(License $license): array
    {
        // 3.0 doesn't have identifier
        return $this->filter([
            'name' => $license->name,
            'url' => $license->url,
        ], $license);
    }
}
