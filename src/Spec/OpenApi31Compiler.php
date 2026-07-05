<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

use OpenApi\Generator;

class OpenApi31Compiler implements SpecCompilerInterface
{

    public function getVersion(): string
    {
        return '3.1.0';
    }

    public function validate(Specification $specification): CompilerDiagnostics
    {
        $diagnostics = new CompilerDiagnostics();

        if ($specification->info === null) {
            $diagnostics->errors[] = new Diagnostic('info is required');
        } elseif ($specification->info->title === null) {
            $diagnostics->errors[] = new Diagnostic('info.title is required');
        }

        if (!$specification->operations && !$specification->schemas) {
            $diagnostics->warnings[] = new Diagnostic('No paths or components defined');
        }

        return $diagnostics;
    }

    public function compile(Specification $specification): array
    {
        $output = ['openapi' => $specification->openapi?->version ?? '3.1.0'];

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

        $webhooks = $this->compileWebhooks($specification->operations);
        if ($webhooks) {
            $output['webhooks'] = $webhooks;
        }

        if ($specification->tags) {
            $output['tags'] = array_map([$this, 'compileTag'], $specification->tags);
        }

        if ($specification->openapi?->security) {
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

    protected function compileInfo(Info $info): array
    {
        return $this->filter([
            'title' => $info->title,
            'description' => $info->description,
            'termsOfService' => $info->termsOfService,
            'contact' => $info->contact !== null ? $this->compileContact($info->contact) : null,
            'license' => $info->license !== null ? $this->compileLicense($info->license) : null,
            'version' => $info->version,
        ], $info);
    }

    protected function compileContact(Contact $contact): array
    {
        return $this->filter([
            'name' => $contact->name,
            'url' => $contact->url,
            'email' => $contact->email,
        ], $contact);
    }

    protected function compileLicense(License $license): array
    {
        return $this->filter([
            'name' => $license->name,
            'identifier' => $license->identifier,
            'url' => $license->url,
        ], $license);
    }

    protected function compileServer(Server $server): array
    {
        $variables = null;
        if ($server->variables) {
            $variables = [];
            foreach ($server->variables as $variable) {
                if ($variable->name !== null) {
                    $variables[$variable->name] = $this->compileServerVariable($variable);
                }
            }
            $variables = $variables ?: null;
        }

        return $this->filter([
            'url' => $server->url,
            'description' => $server->description,
            'variables' => $variables,
        ], $server);
    }

    protected function compileServerVariable(ServerVariable $variable): array
    {
        return $this->filter([
            'default' => $variable->default,
            'enum' => $variable->enum,
            'description' => $variable->description,
        ], $variable);
    }

    protected function compileTag(Tag $tag): array
    {
        return $this->filter([
            'name' => $tag->name,
            'description' => $tag->description,
            'externalDocs' => $tag->externalDocs !== null ? $this->compileExternalDocs($tag->externalDocs) : null,
        ], $tag);
    }

    protected function compileExternalDocs(ExternalDocumentation $docs): array
    {
        return $this->filter([
            'url' => $docs->url,
            'description' => $docs->description,
        ], $docs);
    }

    /**
     * @param list<Operation> $operations
     * @return array<string, array<string, mixed>>
     */
    protected function compilePaths(array $operations): array
    {
        $paths = [];

        foreach ($operations as $operation) {
            if ($operation->path === null || $operation->method === null) {
                continue;
            }

            $paths[$operation->path] ??= [];
            $paths[$operation->path][$operation->method] = $this->compileOperation($operation);
        }

        return $paths;
    }

    /**
     * @param list<Operation> $operations
     * @return array<string, array<string, mixed>>
     */
    protected function compileWebhooks(array $operations): array
    {
        $webhooks = [];

        foreach ($operations as $operation) {
            if ($operation->webhook === null || $operation->method === null) {
                continue;
            }

            $webhooks[$operation->webhook] ??= [];
            $webhooks[$operation->webhook][$operation->method] = $this->compileOperation($operation);
        }

        return $webhooks;
    }

    protected function compileOperation(Operation $operation): array
    {
        return $this->filter([
            'tags' => $operation->tags,
            'summary' => $operation->summary,
            'description' => $operation->description,
            'externalDocs' => $operation->externalDocs !== null ? $this->compileExternalDocs($operation->externalDocs) : null,
            'operationId' => $operation->operationId,
            'parameters' => $operation->parameters ? array_map([$this, 'compileParameter'], $operation->parameters) : null,
            'requestBody' => $operation->requestBody !== null ? $this->compileRequestBody($operation->requestBody) : null,
            'responses' => $operation->responses ? $this->compileResponses($operation->responses) : null,
            'callbacks' => $operation->callbacks,
            'deprecated' => $operation->deprecated,
            'security' => $operation->security,
            'servers' => $operation->servers ? array_map([$this, 'compileServer'], $operation->servers) : null,
        ], $operation);
    }

    protected function compileParameter(Parameter $parameter): array
    {
        if ($parameter->ref !== null) {
            return ['$ref' => $parameter->ref];
        }

        return $this->filter([
            'name' => $parameter->name,
            'in' => $parameter->in,
            'description' => $parameter->description,
            'required' => $parameter->required,
            'deprecated' => $parameter->deprecated,
            'allowEmptyValue' => $parameter->allowEmptyValue,
            'style' => $parameter->style,
            'explode' => $parameter->explode,
            'allowReserved' => $parameter->allowReserved,
            'schema' => $parameter->schema !== null ? $this->compileSchema($parameter->schema) : null,
            'example' => $parameter->example,
            'examples' => $parameter->examples !== null ? $this->compileExamples($parameter->examples) : null,
            'content' => $parameter->content !== null ? $this->compileMediaTypes($parameter->content) : null,
        ], $parameter);
    }

    protected function compileRequestBody(RequestBody $body): array
    {
        if ($body->ref !== null) {
            return ['$ref' => $body->ref];
        }

        return $this->filter([
            'description' => $body->description,
            'content' => $body->content ? $this->compileMediaTypes($body->content) : null,
            'required' => $body->required,
        ], $body);
    }

    /**
     * @param list<Response> $responses
     * @return array<string, mixed>
     */
    protected function compileResponses(array $responses): array
    {
        $result = [];

        foreach ($responses as $response) {
            $key = (string) $response->response;
            $result[$key] = $this->compileResponse($response);
        }

        return $result;
    }

    protected function compileResponse(Response $response): array
    {
        if ($response->ref !== null) {
            return ['$ref' => $response->ref];
        }

        $headers = null;
        if ($response->headers) {
            $headers = [];
            foreach ($response->headers as $header) {
                if ($header->header !== null) {
                    $headers[$header->header] = $this->compileHeader($header);
                }
            }
            $headers = $headers ?: null;
        }

        $links = null;
        if ($response->links) {
            $links = [];
            foreach ($response->links as $link) {
                $name = $link->link ?? $link->operationId ?? 'link';
                $links[$name] = $this->compileLink($link);
            }
            $links = $links ?: null;
        }

        return $this->filter([
            'description' => $response->description,
            'headers' => $headers,
            'content' => $response->content ? $this->compileMediaTypes($response->content) : null,
            'links' => $links,
        ], $response);
    }

    protected function compileHeader(Header $header): array
    {
        if ($header->ref !== null) {
            return ['$ref' => $header->ref];
        }

        return $this->filter([
            'description' => $header->description,
            'required' => $header->required,
            'deprecated' => $header->deprecated,
            'schema' => $header->schema !== null ? $this->compileSchema($header->schema) : null,
        ], $header);
    }

    /**
     * @param list<MediaType> $mediaTypes
     * @return array<string, mixed>
     */
    protected function compileMediaTypes(array $mediaTypes): array
    {
        $result = [];

        foreach ($mediaTypes as $mediaType) {
            $key = $mediaType->mediaType ?? 'application/json';
            $result[$key] = $this->compileMediaType($mediaType);
        }

        return $result;
    }

    protected function compileMediaType(MediaType $mediaType): array
    {
        $encoding = null;
        if ($mediaType->encoding) {
            $encoding = [];
            foreach ($mediaType->encoding as $name => $enc) {
                $encoding[$name] = $this->compileEncoding($enc);
            }
            $encoding = $encoding ?: null;
        }

        return $this->filter([
            'schema' => $mediaType->schema !== null ? $this->compileSchema($mediaType->schema) : null,
            'example' => $mediaType->example,
            'examples' => $mediaType->examples !== null ? $this->compileExamples($mediaType->examples) : null,
            'encoding' => $encoding,
        ], $mediaType);
    }

    protected function compileEncoding(Encoding $encoding): array
    {
        return $this->filter([
            'contentType' => $encoding->contentType,
            'style' => $encoding->style,
            'explode' => $encoding->explode,
            'allowReserved' => $encoding->allowReserved,
        ], $encoding);
    }

    protected function compileLink(Link $link): array
    {
        if ($link->ref !== null) {
            return ['$ref' => $link->ref];
        }

        return $this->filter([
            'operationRef' => $link->operationRef,
            'operationId' => $link->operationId,
            'parameters' => $link->parameters,
            'requestBody' => $link->requestBody,
            'description' => $link->description,
            'server' => $link->server !== null ? $this->compileServer($link->server) : null,
        ], $link);
    }

    protected function compileSchema(Schema|string $schema): array
    {
        if (is_string($schema)) {
            return ['$ref' => $schema];
        }

        if ($schema->ref !== null) {
            return ['$ref' => $schema->ref];
        }

        return $this->filter([
            'type' => $schema->type,
            'format' => $schema->format,
            'title' => $schema->title,
            'description' => $schema->description,
            'nullable' => $schema->nullable,
            'enum' => $schema->enum,
            'const' => $schema->const,
            'default' => $schema->default,

            // String
            'minLength' => $schema->minLength,
            'maxLength' => $schema->maxLength,
            'pattern' => $schema->pattern,
            'contentMediaType' => $schema->contentMediaType,
            'contentEncoding' => $schema->contentEncoding,

            // Numeric
            'minimum' => $schema->minimum,
            'maximum' => $schema->maximum,
            'exclusiveMinimum' => $schema->exclusiveMinimum,
            'exclusiveMaximum' => $schema->exclusiveMaximum,
            'multipleOf' => $schema->multipleOf,

            // Array
            'items' => $schema->items !== null ? $this->compileSchema($schema->items) : null,
            'minItems' => $schema->minItems,
            'maxItems' => $schema->maxItems,
            'uniqueItems' => $schema->uniqueItems,
            'prefixItems' => $schema->prefixItems !== null ? array_map([$this, 'compileSchema'], $schema->prefixItems) : null,
            'contains' => $schema->contains !== null ? (is_bool($schema->contains) ? $schema->contains : $this->compileSchema($schema->contains)) : null,
            'minContains' => $schema->minContains,
            'maxContains' => $schema->maxContains,
            'unevaluatedItems' => $schema->unevaluatedItems !== null ? (is_bool($schema->unevaluatedItems) ? $schema->unevaluatedItems : $this->compileSchema($schema->unevaluatedItems)) : null,

            // Object
            'properties' => $schema->properties !== null ? $this->compileProperties($schema->properties) : null,
            'required' => $schema->required,
            'additionalProperties' => $schema->additionalProperties !== null ? (is_bool($schema->additionalProperties) ? $schema->additionalProperties : $this->compileSchema($schema->additionalProperties)) : null,
            'patternProperties' => $schema->patternProperties !== null ? array_map([$this, 'compileSchema'], $schema->patternProperties) : null,
            'minProperties' => $schema->minProperties,
            'maxProperties' => $schema->maxProperties,
            'unevaluatedProperties' => $schema->unevaluatedProperties !== null ? (is_bool($schema->unevaluatedProperties) ? $schema->unevaluatedProperties : $this->compileSchema($schema->unevaluatedProperties)) : null,
            'propertyNames' => $schema->propertyNames !== null ? $this->compileSchema($schema->propertyNames) : null,
            'dependentRequired' => $schema->dependentRequired,
            'dependentSchemas' => $schema->dependentSchemas !== null ? array_map([$this, 'compileSchema'], $schema->dependentSchemas) : null,

            // Composition
            'allOf' => $schema->allOf !== null ? array_map([$this, 'compileSchema'], $schema->allOf) : null,
            'anyOf' => $schema->anyOf !== null ? array_map([$this, 'compileSchema'], $schema->anyOf) : null,
            'oneOf' => $schema->oneOf !== null ? array_map([$this, 'compileSchema'], $schema->oneOf) : null,
            'not' => $schema->not !== null ? $this->compileSchema($schema->not) : null,

            // Conditional
            'if' => $schema->if !== null ? $this->compileSchema($schema->if) : null,
            'then' => $schema->then !== null ? $this->compileSchema($schema->then) : null,
            'else' => $schema->else !== null ? $this->compileSchema($schema->else) : null,

            // Examples
            'example' => $schema->example,
            'examples' => $schema->examples,

            // Meta
            'deprecated' => $schema->deprecated,
            'readOnly' => $schema->readOnly,
            'writeOnly' => $schema->writeOnly,

            // OpenAPI extensions on schema
            'discriminator' => $schema->discriminator !== null ? $this->compileDiscriminator($schema->discriminator) : null,
            'externalDocs' => $schema->externalDocs !== null ? $this->compileExternalDocs($schema->externalDocs) : null,
            'xml' => $schema->xml !== null ? $this->compileXml($schema->xml) : null,
        ], $schema);
    }

    /**
     * @param list<Property> $properties
     * @return array<string, mixed>
     */
    protected function compileProperties(array $properties): array
    {
        $result = [];

        foreach ($properties as $property) {
            $name = $property->property ?? 'unknown';
            $result[$name] = $property->schema !== null
                ? $this->compileSchema($property->schema)
                : new \stdClass();
        }

        return $result;
    }

    protected function compileDiscriminator(Discriminator $discriminator): array
    {
        return $this->filter([
            'propertyName' => $discriminator->propertyName,
            'mapping' => $discriminator->mapping,
        ], $discriminator);
    }

    protected function compileXml(Xml $xml): array
    {
        return $this->filter([
            'name' => $xml->name,
            'namespace' => $xml->namespace,
            'prefix' => $xml->prefix,
            'attribute' => $xml->attribute,
            'wrapped' => $xml->wrapped,
        ], $xml);
    }

    protected function compileComponents(Specification $specification): array
    {
        $components = [];

        if ($specification->schemas) {
            $schemas = [];
            foreach ($specification->schemas as $schema) {
                $name = $schema->schema ?? $schema->title ?? 'Schema';
                $schemas[$name] = $this->compileSchema($schema);
            }
            $components['schemas'] = $schemas;
        }

        if ($specification->responses) {
            $responses = [];
            foreach ($specification->responses as $response) {
                $key = (string) $response->response;
                $responses[$key] = $this->compileResponse($response);
            }
            $components['responses'] = $responses;
        }

        if ($specification->parameters) {
            $parameters = [];
            foreach ($specification->parameters as $parameter) {
                $name = $parameter->parameter ?? $parameter->name ?? 'param';
                $parameters[$name] = $this->compileParameter($parameter);
            }
            $components['parameters'] = $parameters;
        }

        if ($specification->requestBodies) {
            $bodies = [];
            foreach ($specification->requestBodies as $i => $body) {
                $name = $body->request ?? 'body' . $i;
                $bodies[$name] = $this->compileRequestBody($body);
            }
            $components['requestBodies'] = $bodies;
        }

        if ($specification->headers) {
            $headers = [];
            foreach ($specification->headers as $header) {
                $name = $header->header ?? 'header';
                $headers[$name] = $this->compileHeader($header);
            }
            $components['headers'] = $headers;
        }

        if ($specification->securitySchemes) {
            $schemes = [];
            foreach ($specification->securitySchemes as $scheme) {
                $name = $scheme->securityScheme ?? 'scheme';
                $schemes[$name] = $this->compileSecurityScheme($scheme);
            }
            $components['securitySchemes'] = $schemes;
        }

        if ($specification->links) {
            $links = [];
            foreach ($specification->links as $link) {
                $name = $link->link ?? $link->operationId ?? 'link';
                $links[$name] = $this->compileLink($link);
            }
            $components['links'] = $links;
        }

        if ($specification->examples) {
            $examples = [];
            foreach ($specification->examples as $example) {
                $name = $example->example ?? 'example';
                $examples[$name] = $this->compileExample($example);
            }
            $components['examples'] = $examples;
        }

        return $components;
    }

    protected function compileSecurityScheme(SecurityScheme $scheme): array
    {
        return $this->filter([
            'type' => $scheme->type,
            'description' => $scheme->description,
            'name' => $scheme->name,
            'in' => $scheme->in,
            'scheme' => $scheme->scheme,
            'bearerFormat' => $scheme->bearerFormat,
            'openIdConnectUrl' => $scheme->openIdConnectUrl,
            'flows' => $scheme->flows !== null ? $this->compileFlows($scheme->flows) : null,
        ], $scheme);
    }

    /**
     * @param list<Flow> $flows
     */
    protected function compileFlows(array $flows): array
    {
        $result = [];

        foreach ($flows as $flow) {
            if ($flow->flow !== null) {
                $result[$flow->flow] = $this->compileFlow($flow);
            }
        }

        return $result;
    }

    protected function compileFlow(Flow $flow): array
    {
        return $this->filter([
            'authorizationUrl' => $flow->authorizationUrl,
            'tokenUrl' => $flow->tokenUrl,
            'refreshUrl' => $flow->refreshUrl,
            'scopes' => $flow->scopes,
        ], $flow);
    }

    protected function compileExample(Example $example): array
    {
        return $this->filter([
            'summary' => $example->summary,
            'description' => $example->description,
            'value' => $example->value,
            'externalValue' => $example->externalValue,
        ], $example);
    }

    /**
     * @param list<Example> $examples
     * @return array<string, mixed>
     */
    protected function compileExamples(array $examples): array
    {
        $result = [];

        foreach ($examples as $example) {
            $name = $example->example ?? 'example';
            $result[$name] = $this->compileExample($example);
        }

        return $result;
    }

    /**
     * Remove null entries and apply x- extensions.
     */
    protected function filter(array $result, AbstractAttribute $attribute): array
    {
        $result = array_filter($result, fn ($value) => $value !== null && $value !== Generator::UNDEFINED && $value !== []);

        if ($attribute->x !== null) {
            foreach ($attribute->x as $key => $value) {
                $result['x-' . $key] = $value;
            }
        }

        return $result;
    }
}
