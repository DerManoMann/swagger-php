<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Annotations;

use OpenApi\Generator;

/**
 * @Annotation
 */
abstract class AbstractProperty extends Schema
{
    /**
     * The key into Schema->properties array.
     *
     * @var string
     */
    public $property = Generator::UNDEFINED;

    /**
     * Indicates the property is nullable.
     *
     * @var bool
     */
    public $nullable = Generator::UNDEFINED;

    /**
     * Same as adding to Schema::$required
     * @var bool
     */
    public $required = Generator::UNDEFINED;

    /**
     * @inheritdoc
     */
    public static $_parents = [
        AdditionalProperties::class,
        Schema::class,
        JsonContent::class,
        XmlContent::class,
        Property::class,
        Items::class,
    ];

    /**
     * @inheritdoc
     */
    public static $_nested = [
        Discriminator::class => 'discriminator',
        Items::class => 'items',
        Property::class => ['properties', 'property'],
        ExternalDocumentation::class => 'externalDocs',
        Xml::class => 'xml',
        AdditionalProperties::class => 'additionalProperties',
        Attachable::class => ['attachables'],
    ];

    /**
     * @inheritdoc
     */
    public static $_types = [
        'title' => 'string',
        'description' => 'string',
        'required' => 'boolean',
        'format' => 'string',
        'collectionFormat' => ['csv', 'ssv', 'tsv', 'pipes', 'multi'],
        'maximum' => 'number',
        'exclusiveMaximum' => 'boolean',
        'minimum' => 'number',
        'exclusiveMinimum' => 'boolean',
        'maxLength' => 'integer',
        'minLength' => 'integer',
        'pattern' => 'string',
        'maxItems' => 'integer',
        'minItems' => 'integer',
        'uniqueItems' => 'boolean',
        'multipleOf' => 'integer',
        'allOf' => '[' . Schema::class . ']',
        'oneOf' => '[' . Schema::class . ']',
        'anyOf' => '[' . Schema::class . ']',
    ];

    /** @inheritdoc */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        $json = parent::jsonSerialize();
        unset($json->required);

        return $json;
    }
}

if (\PHP_VERSION_ID >= 80100) {
    /**
     * @Annotation
     */
    #[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_PROPERTY)]
    class Property extends AbstractProperty
    {
        public function __construct(
            array $properties = [],
            string $property = Generator::UNDEFINED,
            string $description = Generator::UNDEFINED,
            string $title = Generator::UNDEFINED,
            string $type = Generator::UNDEFINED,
            string $format = Generator::UNDEFINED,
            string $ref = Generator::UNDEFINED,
            ?bool $required = null,
            ?array $allOf = null,
            ?array $anyOf = null,
            ?array $oneOf = null,
            ?bool $nullable = null,
            ?Items $items = null,
            ?bool $deprecated = null,
            $example = Generator::UNDEFINED,
            $examples = Generator::UNDEFINED,
            ?array $x = null,
            ?array $attachables = null
        ) {
            parent::__construct($properties + [
                    'property' => $property,
                    'description' => $description,
                    'title' => $title,
                    'type' => $type,
                    'format' => $format,
                    'nullable' => $nullable ?? Generator::UNDEFINED,
                    'deprecated' => $deprecated ?? Generator::UNDEFINED,
                    'example' => $example,
                    'ref' => $ref,
                    'required' => $this->required !== Generator::UNDEFINED ? $this->required : ($required ?? Generator::UNDEFINED),
                    'allOf' => $allOf ?? Generator::UNDEFINED,
                    'anyOf' => $anyOf ?? Generator::UNDEFINED,
                    'oneOf' => $oneOf ?? Generator::UNDEFINED,
                    'x' => $x ?? Generator::UNDEFINED,
                    'value' => $this->combine($items, $examples, $attachables),
                ]);
        }
    }
} else {
    /**
     * @Annotation
     */
    class Property extends AbstractProperty
    {
        public function __construct(array $properties)
        {
            parent::__construct($properties);
        }
    }
}
