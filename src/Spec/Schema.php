<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

use OpenApi\Generator;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::TARGET_METHOD | \Attribute::TARGET_PROPERTY | \Attribute::IS_REPEATABLE)]
class Schema extends AbstractAttribute
{
    /**
     * @param string|class-string|null                $ref
     * @param list<string>|null                       $required
     * @param list<Property|Schema>|null              $properties
     * @param string|non-empty-array<string>|null     $type
     * @param list<Schema>|null                       $allOf
     * @param list<Schema>|null                       $anyOf
     * @param list<Schema>|null                       $oneOf
     * @param list<Schema>|null                       $prefixItems
     * @param list<string|int|float|bool|null>|null   $enum
     * @param array<string,Schema>|null               $patternProperties
     * @param array<string,list<string>>|null         $dependentRequired
     * @param array<string,Schema>|null               $dependentSchemas
     * @param array<string,mixed>|null                $x
     * @param list<Attachable>                         $attachables
     */
    public function __construct(
        // Identity / key
        public ?string $schema = null,
        public ?string $title = null,
        public ?string $description = null,

        // Reference
        public string|null $ref = null,

        // Core type
        public string|array|null $type = null,
        public ?string $format = null,
        public ?bool $nullable = null,

        // String constraints
        public ?int $minLength = null,
        public ?int $maxLength = null,
        public ?string $pattern = null,
        public ?string $contentMediaType = null,
        public ?string $contentEncoding = null,

        // Numeric constraints
        public int|float|null $minimum = null,
        public int|float|null $maximum = null,
        public int|float|bool|null $exclusiveMinimum = null,
        public int|float|bool|null $exclusiveMaximum = null,
        public int|float|null $multipleOf = null,

        // Array constraints
        public Schema|string|null $items = null,
        public ?int $minItems = null,
        public ?int $maxItems = null,
        public ?bool $uniqueItems = null,
        public ?array $prefixItems = null,
        public Schema|bool|null $contains = null,
        public ?int $minContains = null,
        public ?int $maxContains = null,
        public Schema|bool|null $unevaluatedItems = null,

        // Object constraints
        public ?array $properties = null,
        public ?array $required = null,
        public Schema|bool|null $additionalProperties = null,
        public ?array $patternProperties = null,
        public ?int $minProperties = null,
        public ?int $maxProperties = null,
        public Schema|bool|null $unevaluatedProperties = null,
        public Schema|null $propertyNames = null,
        public ?array $dependentRequired = null,
        public ?array $dependentSchemas = null,

        // Composition
        public ?array $allOf = null,
        public ?array $anyOf = null,
        public ?array $oneOf = null,
        public Schema|null $not = null,

        // Conditional
        public Schema|null $if = null,
        public Schema|null $then = null,
        public Schema|null $else = null,

        // Enum/const
        public ?array $enum = null,
        public mixed $const = Generator::UNDEFINED,

        // Examples
        public mixed $example = Generator::UNDEFINED,
        public ?array $examples = null,

        // Meta
        public ?bool $deprecated = null,
        public ?bool $readOnly = null,
        public ?bool $writeOnly = null,
        public mixed $default = Generator::UNDEFINED,

        // OpenAPI extensions on Schema Object
        public ?Discriminator $discriminator = null,
        public ?ExternalDocumentation $externalDocs = null,
        public ?Xml $xml = null,

        // Extension
        ?array $x = null,
        array $attachables = [],
    ) {
        parent::__construct(attachables: $attachables, x: $x);
    }

    public function allowedParents(): ?array
    {
        return [Parameter::class, Header::class, MediaType::class];
    }
}
