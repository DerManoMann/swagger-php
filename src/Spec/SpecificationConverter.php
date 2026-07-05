<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

use OpenApi\Annotations as OA;
use OpenApi\Generator;

/**
 * Converts a fully-processed classic OA\OpenApi annotation tree into a Specification.
 *
 * This is the Phase 2 bridge: classic pipeline → Specification → Compiler.
 * Expects post-processor annotations (types resolved, refs in place, traits expanded).
 */
class SpecificationConverter
{
    public function convert(OA\OpenApi $openApi): Specification
    {
        $spec = new Specification();

        $spec->openapi = new OpenApi(
            version: $openApi->openapi,
            security: !Generator::isDefault($openApi->security) ? $openApi->security : null,
        );

        if (!Generator::isDefault($openApi->info)) {
            $spec->info = $this->convertInfo($openApi->info);
        }

        if (!Generator::isDefault($openApi->servers)) {
            foreach ($openApi->servers as $server) {
                $spec->servers[] = $this->convertServer($server);
            }
        }

        if (!Generator::isDefault($openApi->tags)) {
            foreach ($openApi->tags as $tag) {
                $spec->tags[] = $this->convertTag($tag);
            }
        }

        if (!Generator::isDefault($openApi->externalDocs)) {
            $spec->externalDocs[] = $this->convertExternalDocs($openApi->externalDocs);
        }

        if (!Generator::isDefault($openApi->paths)) {
            foreach ($openApi->paths as $pathItem) {
                $this->convertPathItem($pathItem, $spec);
            }
        }

        if (!Generator::isDefault($openApi->webhooks)) {
            foreach ($openApi->webhooks as $webhook) {
                $this->convertWebhook($webhook, $spec);
            }
        }

        if (!Generator::isDefault($openApi->components)) {
            $this->convertComponents($openApi->components, $spec);
        }

        return $spec;
    }

    protected function convertInfo(OA\Info $info): Info
    {
        $contact = null;
        if (!Generator::isDefault($info->contact)) {
            $contact = new Contact(
                name: $this->val($info->contact->name),
                url: $this->val($info->contact->url),
                email: $this->val($info->contact->email),
            );
        }

        $license = null;
        if (!Generator::isDefault($info->license)) {
            $license = new License(
                name: $this->val($info->license->name),
                identifier: $this->val($info->license->identifier),
                url: $this->val($info->license->url),
            );
        }

        return new Info(
            title: $this->val($info->title),
            description: $this->val($info->description),
            termsOfService: $this->val($info->termsOfService),
            version: $this->val($info->version),
            contact: $contact,
            license: $license,
            x: $this->extensions($info),
        );
    }

    protected function convertServer(OA\Server $server): Server
    {
        $variables = null;
        if (!Generator::isDefault($server->variables)) {
            $variables = [];
            foreach ($server->variables as $variable) {
                $variables[] = new ServerVariable(
                    serverVariable: $this->val($variable->serverVariable),
                    default: $this->val($variable->default),
                    description: $this->val($variable->description),
                    enum: !Generator::isDefault($variable->enum) ? $variable->enum : null,
                    x: $this->extensions($variable),
                );
            }
        }

        return new Server(
            url: $this->val($server->url),
            description: $this->val($server->description),
            variables: $variables,
            x: $this->extensions($server),
        );
    }

    protected function convertTag(OA\Tag $tag): Tag
    {
        return new Tag(
            name: $this->val($tag->name),
            description: $this->val($tag->description),
            externalDocs: !Generator::isDefault($tag->externalDocs)
                ? $this->convertExternalDocs($tag->externalDocs)
                : null,
            x: $this->extensions($tag),
        );
    }

    protected function convertExternalDocs(OA\ExternalDocumentation $docs): ExternalDocumentation
    {
        return new ExternalDocumentation(
            url: $this->val($docs->url),
            description: $this->val($docs->description),
            x: $this->extensions($docs),
        );
    }

    protected function convertPathItem(OA\PathItem $pathItem, Specification $spec): void
    {
        $methods = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];

        foreach ($methods as $method) {
            if (!Generator::isDefault($pathItem->{$method})) {
                $spec->operations[] = $this->convertOperation($pathItem->{$method}, $pathItem->path, $method);
            }
        }
    }

    protected function convertWebhook(OA\Webhook $webhook, Specification $spec): void
    {
        $methods = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace'];

        foreach ($methods as $method) {
            if (!Generator::isDefault($webhook->{$method})) {
                $operation = $this->convertOperation($webhook->{$method}, null, $method);
                $operation->webhook = $this->val($webhook->webhook);
                $spec->operations[] = $operation;
            }
        }
    }

    protected function convertOperation(OA\Operation $op, ?string $path, string $method): Operation
    {
        $parameters = null;
        if (!Generator::isDefault($op->parameters)) {
            $parameters = [];
            foreach ($op->parameters as $param) {
                $parameters[] = $this->convertParameter($param);
            }
        }

        $responses = null;
        if (!Generator::isDefault($op->responses)) {
            $responses = [];
            foreach ($op->responses as $response) {
                $responses[] = $this->convertResponse($response);
            }
        }

        $requestBody = null;
        if (!Generator::isDefault($op->requestBody)) {
            $requestBody = $this->convertRequestBody($op->requestBody);
        }

        $callbacks = null;
        if (!Generator::isDefault($op->callbacks)) {
            $callbacks = $this->convertCallbacks($op->callbacks);
        }

        return new Operation(
            path: $path,
            method: $method,
            operationId: $this->val($op->operationId),
            summary: $this->val($op->summary),
            description: $this->val($op->description),
            tags: !Generator::isDefault($op->tags) ? $op->tags : null,
            deprecated: $this->val($op->deprecated),
            security: !Generator::isDefault($op->security) ? $op->security : null,
            parameters: $parameters,
            requestBody: $requestBody,
            responses: $responses,
            callbacks: $callbacks,
            externalDocs: !Generator::isDefault($op->externalDocs)
                ? $this->convertExternalDocs($op->externalDocs)
                : null,
            servers: !Generator::isDefault($op->servers)
                ? array_map([$this, 'convertServer'], $op->servers)
                : null,
            x: $this->extensions($op),
        );
    }

    protected function convertParameter(OA\Parameter $param): Parameter
    {
        return new Parameter(
            name: $this->val($param->name),
            in: $this->val($param->in),
            description: $this->val($param->description),
            required: $this->val($param->required),
            deprecated: $this->val($param->deprecated),
            allowEmptyValue: $this->val($param->allowEmptyValue),
            ref: $this->val($param->ref),
            style: $this->val($param->style),
            explode: $this->val($param->explode),
            allowReserved: $this->val($param->allowReserved),
            schema: !Generator::isDefault($param->schema) ? $this->convertSchema($param->schema) : null,
            example: !Generator::isDefault($param->example) ? $param->example : Generator::UNDEFINED,
            examples: !Generator::isDefault($param->examples)
                ? array_map([$this, 'convertExample'], $param->examples)
                : null,
            content: !Generator::isDefault($param->content)
                ? array_map([$this, 'convertMediaType'], $param->content)
                : null,
            x: $this->extensions($param),
        );
    }

    protected function convertResponse(OA\Response $response): Response
    {
        $headers = null;
        if (!Generator::isDefault($response->headers)) {
            $headers = [];
            foreach ($response->headers as $header) {
                $headers[] = $this->convertHeader($header);
            }
        }

        $links = null;
        if (!Generator::isDefault($response->links)) {
            $links = [];
            foreach ($response->links as $link) {
                $links[] = $this->convertLink($link);
            }
        }

        return new Response(
            response: $this->val($response->response),
            description: $this->val($response->description),
            ref: $this->val($response->ref),
            headers: $headers,
            content: !Generator::isDefault($response->content)
                ? array_map([$this, 'convertMediaType'], $response->content)
                : null,
            links: $links,
            x: $this->extensions($response),
        );
    }

    protected function convertRequestBody(OA\RequestBody $body): RequestBody
    {
        return new RequestBody(
            description: $this->val($body->description),
            required: $this->val($body->required),
            ref: $this->val($body->ref),
            request: $this->val($body->request),
            content: !Generator::isDefault($body->content)
                ? array_map([$this, 'convertMediaType'], $body->content)
                : null,
            x: $this->extensions($body),
        );
    }

    protected function convertMediaType(OA\MediaType $mediaType): MediaType
    {
        $encoding = null;
        if (!Generator::isDefault($mediaType->encoding)) {
            $encoding = [];
            foreach ($mediaType->encoding as $enc) {
                $key = $this->val($enc->property);
                $encoding[$key] = $this->convertEncoding($enc);
            }
        }

        return new MediaType(
            mediaType: $this->val($mediaType->mediaType),
            schema: !Generator::isDefault($mediaType->schema) ? $this->convertSchema($mediaType->schema) : null,
            example: !Generator::isDefault($mediaType->example) ? $mediaType->example : Generator::UNDEFINED,
            examples: !Generator::isDefault($mediaType->examples)
                ? array_map([$this, 'convertExample'], $mediaType->examples)
                : null,
            encoding: $encoding,
            x: $this->extensions($mediaType),
        );
    }

    protected function convertHeader(OA\Header $header): Header
    {
        return new Header(
            header: $this->val($header->header),
            description: $this->val($header->description),
            required: $this->val($header->required),
            deprecated: $this->val($header->deprecated),
            ref: $this->val($header->ref),
            schema: !Generator::isDefault($header->schema) ? $this->convertSchema($header->schema) : null,
            x: $this->extensions($header),
        );
    }

    protected function convertLink(OA\Link $link): Link
    {
        return new Link(
            link: $this->val($link->link),
            operationRef: $this->val($link->operationRef),
            operationId: $this->val($link->operationId),
            parameters: !Generator::isDefault($link->parameters) ? $link->parameters : null,
            requestBody: $this->val($link->requestBody),
            description: $this->val($link->description),
            ref: $this->val($link->ref),
            server: !Generator::isDefault($link->server) ? $this->convertServer($link->server) : null,
            x: $this->extensions($link),
        );
    }

    protected function convertEncoding(OA\Encoding $encoding): Encoding
    {
        return new Encoding(
            encoding: $this->val($encoding->property),
            contentType: $this->val($encoding->contentType),
            style: $this->val($encoding->style),
            explode: $this->val($encoding->explode),
            allowReserved: $this->val($encoding->allowReserved),
            x: $this->extensions($encoding),
        );
    }

    protected function convertSchema(OA\Schema $schema): Schema
    {
        $properties = null;
        if (!Generator::isDefault($schema->properties)) {
            $properties = [];
            foreach ($schema->properties as $prop) {
                $properties[] = $this->convertProperty($prop);
            }
        }

        return new Schema(
            schema: $this->val($schema->schema),
            title: $this->val($schema->title),
            description: $this->val($schema->description),
            ref: $this->val($schema->ref),
            type: !Generator::isDefault($schema->type) ? $schema->type : null,
            format: $this->val($schema->format),
            nullable: $this->val($schema->nullable),
            minLength: $this->val($schema->minLength),
            maxLength: $this->val($schema->maxLength),
            pattern: $this->val($schema->pattern),
            minimum: $this->val($schema->minimum),
            maximum: $this->val($schema->maximum),
            exclusiveMinimum: $this->val($schema->exclusiveMinimum),
            exclusiveMaximum: $this->val($schema->exclusiveMaximum),
            multipleOf: $this->val($schema->multipleOf),
            items: !Generator::isDefault($schema->items) ? $this->convertSchema($schema->items) : null,
            minItems: $this->val($schema->minItems),
            maxItems: $this->val($schema->maxItems),
            uniqueItems: $this->val($schema->uniqueItems),
            additionalProperties: $this->convertAdditionalProperties($schema),
            properties: $properties,
            required: !Generator::isDefault($schema->required) ? $schema->required : null,
            minProperties: $this->val($schema->minProperties),
            maxProperties: $this->val($schema->maxProperties),
            allOf: !Generator::isDefault($schema->allOf) ? array_map([$this, 'convertSchema'], $schema->allOf) : null,
            anyOf: !Generator::isDefault($schema->anyOf) ? array_map([$this, 'convertSchema'], $schema->anyOf) : null,
            oneOf: !Generator::isDefault($schema->oneOf) ? array_map([$this, 'convertSchema'], $schema->oneOf) : null,
            not: !Generator::isDefault($schema->not) ? $this->convertSchema($schema->not) : null,
            enum: !Generator::isDefault($schema->enum) ? $schema->enum : null,
            const: !Generator::isDefault($schema->const) ? $schema->const : Generator::UNDEFINED,
            example: !Generator::isDefault($schema->example) ? $schema->example : Generator::UNDEFINED,
            default: !Generator::isDefault($schema->default) ? $schema->default : Generator::UNDEFINED,
            deprecated: $this->val($schema->deprecated),
            readOnly: $this->val($schema->readOnly),
            writeOnly: $this->val($schema->writeOnly),
            discriminator: !Generator::isDefault($schema->discriminator)
                ? $this->convertDiscriminator($schema->discriminator)
                : null,
            externalDocs: !Generator::isDefault($schema->externalDocs)
                ? $this->convertExternalDocs($schema->externalDocs)
                : null,
            xml: !Generator::isDefault($schema->xml) ? $this->convertXml($schema->xml) : null,
            x: $this->extensions($schema),
        );
    }

    protected function convertProperty(OA\Property $prop): Property
    {
        $schema = $this->convertSchema($prop);

        return new Property(
            property: $this->val($prop->property),
            schema: $schema,
            x: $this->extensions($prop),
        );
    }

    protected function convertAdditionalProperties(OA\Schema $schema): Schema|bool|null
    {
        if (Generator::isDefault($schema->additionalProperties)) {
            return null;
        }

        if (is_bool($schema->additionalProperties)) {
            return $schema->additionalProperties;
        }

        return $this->convertSchema($schema->additionalProperties);
    }

    protected function convertDiscriminator(OA\Discriminator $disc): Discriminator
    {
        return new Discriminator(
            propertyName: $this->val($disc->propertyName),
            mapping: !Generator::isDefault($disc->mapping) ? $disc->mapping : null,
            x: $this->extensions($disc),
        );
    }

    protected function convertXml(OA\Xml $xml): Xml
    {
        return new Xml(
            name: $this->val($xml->name),
            namespace: $this->val($xml->namespace),
            prefix: $this->val($xml->prefix),
            attribute: $this->val($xml->attribute),
            wrapped: $this->val($xml->wrapped),
            x: $this->extensions($xml),
        );
    }

    protected function convertSecurityScheme(OA\SecurityScheme $scheme): SecurityScheme
    {
        $flows = null;
        if (!Generator::isDefault($scheme->flows)) {
            $flows = [];
            foreach ($scheme->flows as $flow) {
                $flows[] = $this->convertFlow($flow);
            }
        }

        return new SecurityScheme(
            securityScheme: $this->val($scheme->securityScheme),
            type: $this->val($scheme->type),
            description: $this->val($scheme->description),
            name: $this->val($scheme->name),
            in: $this->val($scheme->in),
            scheme: $this->val($scheme->scheme),
            bearerFormat: $this->val($scheme->bearerFormat),
            openIdConnectUrl: $this->val($scheme->openIdConnectUrl),
            flows: $flows,
            ref: $this->val($scheme->ref),
            x: $this->extensions($scheme),
        );
    }

    protected function convertFlow(OA\Flow $flow): Flow
    {
        return new Flow(
            flow: $this->val($flow->flow),
            authorizationUrl: $this->val($flow->authorizationUrl),
            tokenUrl: $this->val($flow->tokenUrl),
            refreshUrl: $this->val($flow->refreshUrl),
            scopes: !Generator::isDefault($flow->scopes) ? (array) $flow->scopes : null,
            x: $this->extensions($flow),
        );
    }

    protected function convertExample(OA\Examples $example): Example
    {
        return new Example(
            example: $this->val($example->example),
            summary: $this->val($example->summary),
            description: $this->val($example->description),
            value: !Generator::isDefault($example->value) ? $example->value : null,
            externalValue: $this->val($example->externalValue),
            x: $this->extensions($example),
        );
    }

    protected function convertComponents(OA\Components $components, Specification $spec): void
    {
        if (!Generator::isDefault($components->schemas)) {
            foreach ($components->schemas as $schema) {
                $spec->schemas[] = $this->convertSchema($schema);
            }
        }

        if (!Generator::isDefault($components->responses)) {
            foreach ($components->responses as $response) {
                $spec->responses[] = $this->convertResponse($response);
            }
        }

        if (!Generator::isDefault($components->parameters)) {
            foreach ($components->parameters as $parameter) {
                $spec->parameters[] = $this->convertParameter($parameter);
            }
        }

        if (!Generator::isDefault($components->requestBodies)) {
            foreach ($components->requestBodies as $body) {
                $spec->requestBodies[] = $this->convertRequestBody($body);
            }
        }

        if (!Generator::isDefault($components->headers)) {
            foreach ($components->headers as $header) {
                $spec->headers[] = $this->convertHeader($header);
            }
        }

        if (!Generator::isDefault($components->securitySchemes)) {
            foreach ($components->securitySchemes as $scheme) {
                $spec->securitySchemes[] = $this->convertSecurityScheme($scheme);
            }
        }

        if (!Generator::isDefault($components->links)) {
            foreach ($components->links as $link) {
                $spec->links[] = $this->convertLink($link);
            }
        }

        if (!Generator::isDefault($components->examples)) {
            foreach ($components->examples as $example) {
                $spec->examples[] = $this->convertExample($example);
            }
        }
    }

    protected function convertCallbacks(array $callbacks): array
    {
        $result = [];
        foreach ($callbacks as $key => $value) {
            if ($value instanceof OA\AbstractAnnotation) {
                $result[$key] = json_decode(json_encode($value->jsonSerialize()), true);
            } elseif (is_array($value)) {
                $result[$key] = $this->convertCallbacks($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Convert a classic UNDEFINED-or-value to null-or-value.
     */
    protected function val(mixed $value): mixed
    {
        return Generator::isDefault($value) ? null : $value;
    }

    /**
     * Extract x- extensions from a classic annotation.
     *
     * @return array<string,mixed>|null
     */
    protected function extensions(OA\AbstractAnnotation $annotation): ?array
    {
        if (Generator::isDefault($annotation->x)) {
            return null;
        }

        return is_array($annotation->x) ? $annotation->x : null;
    }
}
