<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

class Specification
{
    public string $version = '3.1.0';

    public ?Info $info = null;

    /** @var list<Server> */
    public array $servers = [];

    /** @var list<Tag> */
    public array $tags = [];

    /** @var list<ExternalDocumentation> */
    public array $externalDocs = [];

    /** @var list<array<string,list<string>>> */
    public array $security = [];

    /** @var list<Operation> */
    public array $operations = [];

    /** @var list<Schema> */
    public array $schemas = [];

    /** @var list<Response> */
    public array $responses = [];

    /** @var list<Parameter> */
    public array $parameters = [];

    /** @var list<RequestBody> */
    public array $requestBodies = [];

    /** @var list<Header> */
    public array $headers = [];

    /** @var list<SecurityScheme> */
    public array $securitySchemes = [];

    /** @var list<Link> */
    public array $links = [];

    /** @var list<Example> */
    public array $examples = [];

    public function add(OpenApiAttributeInterface ...$attributes): static
    {
        foreach ($attributes as $attribute) {
            match (true) {
                $attribute instanceof OpenApi => $this->version = $attribute->version ?? $this->version,
                $attribute instanceof Info => $this->info = $attribute,
                $attribute instanceof Server => $this->servers[] = $attribute,
                $attribute instanceof Tag => $this->tags[] = $attribute,
                $attribute instanceof ExternalDocumentation => $this->externalDocs[] = $attribute,
                $attribute instanceof Operation => $this->operations[] = $attribute,
                $attribute instanceof Schema => $this->schemas[] = $attribute,
                $attribute instanceof Response => $this->responses[] = $attribute,
                $attribute instanceof Parameter => $this->parameters[] = $attribute,
                $attribute instanceof RequestBody => $this->requestBodies[] = $attribute,
                $attribute instanceof Header => $this->headers[] = $attribute,
                $attribute instanceof SecurityScheme => $this->securitySchemes[] = $attribute,
                $attribute instanceof Link => $this->links[] = $attribute,
                $attribute instanceof Example => $this->examples[] = $attribute,
                default => throw new \InvalidArgumentException('Unsupported attribute: ' . get_class($attribute)),
            };
        }

        return $this;
    }
}
