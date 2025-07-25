<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Annotations;

use OpenApi\Generator;
use OpenApi\Util;

/**
 * Holds a set of reusable objects for different aspects of the OA.
 *
 * All objects defined within the components object will have no effect on the API unless they are explicitly
 * referenced from properties outside the components object.
 *
 * @see [Components Object](https://spec.openapis.org/oas/v3.1.1.html#components-object)
 *
 * @Annotation
 */
class Components extends AbstractAnnotation
{
    public const COMPONENTS_PREFIX = '#/components/';

    /**
     * Schema reference.
     *
     * @var string
     */
    public const SCHEMA_REF = '#/components/schemas/';

    /**
     * Reusable Schemas.
     *
     * @var array<Schema|\OpenApi\Attributes\Schema>
     */
    public $schemas = Generator::UNDEFINED;

    /**
     * Reusable Responses.
     *
     * @var Response[]
     */
    public $responses = Generator::UNDEFINED;

    /**
     * Reusable Parameters.
     *
     * @var Parameter[]
     */
    public $parameters = Generator::UNDEFINED;

    /**
     * Reusable Examples.
     *
     * @var array<Examples>
     */
    public $examples = Generator::UNDEFINED;

    /**
     * Reusable Request Bodies.
     *
     * @var RequestBody[]
     */
    public $requestBodies = Generator::UNDEFINED;

    /**
     * Reusable Headers.
     *
     * @var Header[]
     */
    public $headers = Generator::UNDEFINED;

    /**
     * Reusable Security Schemes.
     *
     * @var SecurityScheme[]
     */
    public $securitySchemes = Generator::UNDEFINED;

    /**
     * Reusable Links.
     *
     * @var Link[]
     */
    public $links = Generator::UNDEFINED;

    /**
     * Reusable Callbacks.
     *
     * @var array
     */
    public $callbacks = Generator::UNDEFINED;

    /**
     * @inheritdoc
     */
    public static $_parents = [
        OpenApi::class,
    ];

    /**
     * @inheritdoc
     */
    public static $_nested = [
        Response::class => ['responses', 'response'],
        Parameter::class => ['parameters', 'parameter'],
        PathParameter::class => ['parameters', 'parameter'],
        RequestBody::class => ['requestBodies', 'request'],
        Examples::class => ['examples', 'example'],
        Header::class => ['headers', 'header'],
        SecurityScheme::class => ['securitySchemes', 'securityScheme'],
        Link::class => ['links', 'link'],
        Schema::class => ['schemas', 'schema'],
        Attachable::class => ['attachables'],
    ];

    /**
     * Returns a list of component annotation types.
     *
     * Each may be used as a root to resolve component refs
     */
    public static function componentTypes(): array
    {
        return array_filter(array_keys(self::$_nested), fn ($value): bool => $value !== Attachable::class);
    }

    /**
     * Generate a <code>#/components/...</code> reference for the given annotation.
     *
     * A <code>string</code> component value always assumes type <code>Schema</code>.
     *
     * @param AbstractAnnotation|string $component
     */
    public static function ref($component, bool $encode = true): string
    {
        if ($component instanceof AbstractAnnotation) {
            foreach (Components::$_nested as $type => $nested) {
                // exclude attachables
                if (2 == count($nested)) {
                    if ($component instanceof $type) {
                        $type = $nested[0];
                        $name = $component->{$nested[1]};
                        break;
                    }
                }
            }
        } else {
            $type = 'schemas';
            $name = $component;
        }

        return self::COMPONENTS_PREFIX . $type . '/' . ($encode ? Util::refEncode((string) $name) : $name);
    }
}
