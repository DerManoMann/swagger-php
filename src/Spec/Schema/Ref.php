<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec\Schema;

use OpenApi\Spec as OA;
use OpenApi\Specification\ComponentKey;
use OpenApi\Type\Generics\TypeArguments;
use Symfony\Component\TypeInfo\Exception\UnsupportedException;

/**
 * A reference-only schema — $ref is required, most other Schema properties are unavailable.
 *
 * In OpenAPI 3.1+, $ref can be combined with title and description to override
 * the referenced schema's metadata without duplicating the definition.
 *
 * Usage:
 * ```php
 * #[OA\Property(schema: new OA\Schema\Ref(ref: Pet::class))]
 * #[OA\Property(schema: new OA\Schema\Ref(ref: '#/components/schemas/Pet', title: 'The pet'))]
 * #[OA\Property(schema: new OA\Schema\Ref(ref: Pet::class, description: 'Override desc'))]
 * ```
 *
 * If used on a `$ref` directly, only the ref value and the qualifiers are used.
 *
 * A ref given as a class can carry qualifiers. A class can be described more than once, such as
 * once per serialization group, and the qualifiers say which description the ref means. They
 * have to match the qualifiers that description was registered with:
 * ```php
 * #[OA\Property(schema: new OA\Schema\Ref(ref: Author::class, qualifiers: ['groups' => ['light']]))]
 * ```
 *
 * A generic class can be referenced with type arguments, one type for each template, written as
 * in a docblock with class names fully qualified. The schema made for them is referenced:
 * ```php
 * #[OA\Property(schema: new OA\Schema\Ref(ref: Envelope::class, of: [Customer::class]))]
 * ```
 */
#[\Attribute(\Attribute::TARGET_ALL | \Attribute::IS_REPEATABLE)]
class Ref extends OA\Schema
{
    /**
     * @var array<string, scalar|list<scalar>>
     */
    protected array $qualifiers;

    /**
     * @var list<string>|TypeArguments|null
     */
    protected array|TypeArguments|null $of;

    /**
     * @param array<string, scalar|list<scalar>>|null $qualifiers Which description of the class to reference, when it has more than one
     * @param list<string>|TypeArguments|null         $of         The type arguments a generic class is referenced with, one type for each template
     */
    public function __construct(
        string $ref,
        ?string $title = null,
        ?string $description = null,
        ?array $x = null,
        ?array $attachables = null,
        ?OA\Fan $fan = null,
        ?array $qualifiers = null,
        array|TypeArguments|null $of = null,
    ) {
        $this->qualifiers = $qualifiers ?? [];
        $this->of = $of;

        parent::__construct(
            title: $title,
            description: $description,
            ref: $ref,
            x: $x,
            attachables: $attachables,
            fan: $fan,
        );
    }

    /**
     * The type arguments the class is referenced with, read from their strings where they were
     * written as strings.
     *
     * @throws UnsupportedException where a string cannot be read as a type
     */
    public function getTypeArguments(): ?TypeArguments
    {
        if (is_array($this->of)) {
            // read once: from here on the ref holds them as types
            $this->of = $this->of === [] ? null : TypeArguments::fromStrings(...$this->of);
        }

        return $this->of;
    }

    public function setTypeArguments(?TypeArguments $typeArguments): static
    {
        $this->of = $typeArguments;

        return $this;
    }

    /**
     * The key the ref looks its component up by, or null when the ref is a JSON pointer.
     */
    public function getRefKey(): ?ComponentKey
    {
        if (!is_string($this->ref) || str_starts_with($this->ref, '#/')) {
            return null;
        }

        try {
            $typeArguments = $this->getTypeArguments();
        } catch (UnsupportedException) {
            // `Augmenter\Types` reports arguments it cannot read, and drops them
            $typeArguments = null;
        }

        return new ComponentKey($this->ref, $this->qualifiers, $typeArguments);
    }
}
