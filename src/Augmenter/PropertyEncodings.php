<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Augmenter;

use OpenApi\Spec as OA;
use OpenApi\Spec\Property\Encoded;
use OpenApi\Specification;
use OpenApi\Utils\PipeInterface;

/**
 * Promotes encoding metadata from Property\Encoded to the parent MediaType.
 *
 * For each MediaType, finds Property\Encoded instances (either inline or via
 * schema $ref) and adds corresponding Encoding entries to the MediaType's
 * encoding list.
 *
 * @implements PipeInterface<Specification>
 */
class PropertyEncodings implements PipeInterface
{
    public function group(): string|\BackedEnum
    {
        return Group::Augment;
    }

    public function __invoke(mixed $payload): mixed
    {
        $schemaMap = $this->buildSchemaMap($payload);

        foreach ($payload->operations as $operation) {
            if ($operation->requestBody instanceof OA\RequestBody) {
                $this->processMediaTypes($operation->requestBody->content, $schemaMap);
            }

            if ($operation->responses) {
                foreach ($operation->responses as $response) {
                    $this->processMediaTypes($response->content, $schemaMap);
                }
            }

            if ($operation->parameters) {
                foreach ($operation->parameters as $parameter) {
                    $this->processMediaTypes($parameter->content, $schemaMap);
                }
            }
        }

        foreach ($payload->requestBodies as $body) {
            $this->processMediaTypes($body->content, $schemaMap);
        }

        foreach ($payload->responses as $response) {
            $this->processMediaTypes($response->content, $schemaMap);
        }

        foreach ($payload->parameters as $parameter) {
            $this->processMediaTypes($parameter->content, $schemaMap);
        }

        return null;
    }

    /**
     * @return array<string, OA\Schema>
     */
    protected function buildSchemaMap(Specification $specification): array
    {
        $map = [];
        foreach ($specification->schemas as $schema) {
            $name = $schema->schema ?? $schema->title;
            if ($name !== null) {
                $map['#/components/schemas/' . $name] = $schema;
            }
        }

        return $map;
    }

    /**
     * @param list<OA\MediaType>|null  $mediaTypes
     * @param array<string, OA\Schema> $schemaMap
     */
    protected function processMediaTypes(?array $mediaTypes, array $schemaMap): void
    {
        if (!$mediaTypes) {
            return;
        }

        foreach ($mediaTypes as $mediaType) {
            $this->promoteEncodings($mediaType, $schemaMap);
        }
    }

    /**
     * @param array<string, OA\Schema> $schemaMap
     */
    protected function promoteEncodings(OA\MediaType $mediaType, array $schemaMap): void
    {
        $properties = $this->resolveProperties($mediaType, $schemaMap);

        foreach ($properties as $property) {
            if (!$property instanceof Encoded) {
                continue;
            }

            $encoding = new OA\Encoding(
                encoding: $property->property,
                contentType: $property->contentType,
                headers: $property->headers,
                style: $property->style,
                explode: $property->explode,
                allowReserved: $property->allowReserved,
            );

            $mediaType->encoding ??= [];
            $mediaType->encoding[] = $encoding;
        }
    }

    /**
     * @param array<string, OA\Schema> $schemaMap
     *
     * @return list<OA\Property>
     */
    protected function resolveProperties(OA\MediaType $mediaType, array $schemaMap): array
    {
        if (!$mediaType->schema instanceof OA\Schema) {
            return [];
        }

        if ($mediaType->schema->properties !== null) {
            return $mediaType->schema->properties;
        }

        if ($mediaType->schema->ref !== null && isset($schemaMap[$mediaType->schema->ref])) {
            return $schemaMap[$mediaType->schema->ref]->properties ?? [];
        }

        return [];
    }
}
