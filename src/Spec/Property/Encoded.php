<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec\Property;

use OpenApi\Spec as OA;

/**
 * Property with encoding metadata that gets promoted to the parent MediaType.
 *
 * A shortcut that lets you declare encoding alongside the property it applies to.
 * The `Shortcuts` augmenter promotes the encoding parameters to the enclosing
 * `MediaType::$encoding` list automatically.
 *
 * Allows to shorten this:
 *
 *   #[OA\MediaType(
 *       mediaType: 'multipart/form-data',
 *       schema: new OA\Schema(ref: MyForm::class),
 *       encoding: [
 *           new OA\Encoding(encoding: 'avatar', contentType: 'image/png, image/jpeg'),
 *       ]
 *   )]
 *
 * to this (on the schema class):
 *
 *   #[OA\Property\Encoded(contentType: 'image/png, image/jpeg')]
 *   public \stdClass $avatar;
 *
 * @see [Encoding Object](https://spec.openapis.org/oas/v3.1.1.html#encoding-object)
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::TARGET_PARAMETER | \Attribute::TARGET_CLASS_CONSTANT | \Attribute::IS_REPEATABLE)]
class Encoded extends OA\Property
{
    /**
     * @param string|null              $property       The property name
     * @param string|null              $contentType    The Content-Type for encoding this property
     * @param list<OA\Header>|null     $headers        Additional headers for multipart media types
     * @param string|null              $style          How the property value is serialized
     * @param bool|null                $explode        Whether arrays/objects generate separate parameters
     * @param bool|null                $allowReserved  Whether reserved characters are allowed without encoding
     * @param OA\Schema|null           $schema         The schema defining the property type and constraints
     * @param array<string,mixed>|null $x              Vendor extensions (x-* properties)
     * @param list<OA\Attachable>|null $attachables    Reusable custom attachable attributes
     */
    public function __construct(
        ?string $property = null,
        public ?string $contentType = null,
        public ?array $headers = null,
        public ?string $style = null,
        public ?bool $explode = null,
        public ?bool $allowReserved = null,
        ?OA\Schema $schema = null,
        ?array $x = null,
        ?array $attachables = null,
    ) {
        parent::__construct(property: $property, schema: $schema, x: $x, attachables: $attachables);
    }
}
