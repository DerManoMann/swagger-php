<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

use OpenApi\Analysers\AnnotationFactoryInterface;
use OpenApi\Annotations as OA;
use OpenApi\Context;
use OpenApi\Generator;
use OpenApi\GeneratorAwareTrait;

/**
 * Bridge factory: reads new Spec\* attributes via the Assembler and converts
 * them to classic annotations for the existing analysis pipeline (Phase 1).
 */
class SpecAnnotationFactory implements AnnotationFactoryInterface
{
    use GeneratorAwareTrait;

    /** @var array<class-string<OpenApiAttributeInterface>, class-string<OA\AbstractAnnotation>> */
    private const CLASS_MAP = [
        Schema::class => OA\Schema::class,
        Property::class => OA\Property::class,
        Operation::class => OA\Operation::class,
        Parameter::class => OA\Parameter::class,
        Response::class => OA\Response::class,
        RequestBody::class => OA\RequestBody::class,
        MediaType::class => OA\MediaType::class,
        Header::class => OA\Header::class,
        Encoding::class => OA\Encoding::class,
        ExternalDocumentation::class => OA\ExternalDocumentation::class,
        Discriminator::class => OA\Discriminator::class,
        Xml::class => OA\Xml::class,
        Link::class => OA\Link::class,
        SecurityScheme::class => OA\SecurityScheme::class,
        Flow::class => OA\Flow::class,
        Info::class => OA\Info::class,
        Contact::class => OA\Contact::class,
        License::class => OA\License::class,
        Tag::class => OA\Tag::class,
        Server::class => OA\Server::class,
        ServerVariable::class => OA\ServerVariable::class,
        Example::class => OA\Examples::class,
        Attachable::class => OA\Attachable::class,
    ];

    public function __construct(
        protected Assembler $assembler = new Assembler(),
    ) {
    }

    public function isSupported(): bool
    {
        return true;
    }

    public function build(\Reflector $reflector, Context $context): array
    {
        if ($reflector instanceof \ReflectionProperty && $reflector->isPromoted()) {
            return [];
        }

        $specAttributes = $this->assembler->instantiate($reflector);

        if (!$specAttributes) {
            return [];
        }

        $annotations = [];
        foreach ($specAttributes as $instance) {
            $converted = $this->convert($instance, $context);
            if ($converted !== null) {
                $annotations[] = $converted;
            }
        }

        return $annotations;
    }

    protected function convert(OpenApiAttributeInterface $attribute, Context $context): ?OA\AbstractAnnotation
    {
        if ($attribute instanceof OpenApi) {
            return new OA\OpenApi(['_context' => $context, 'openapi' => $attribute->version ?? OA\OpenApi::DEFAULT_VERSION]);
        }

        $classicClass = $this->resolveClassicClass($attribute);
        if ($classicClass === null) {
            return null;
        }

        $properties = $this->extractProperties($attribute);
        $this->flattenSchema($attribute, $classicClass, $properties);
        $properties['_context'] = $context;

        return new $classicClass($properties);
    }

    /**
     * @return class-string<OA\AbstractAnnotation>|null
     */
    protected function resolveClassicClass(OpenApiAttributeInterface $attribute): ?string
    {
        if ($attribute instanceof Operation) {
            return match ($attribute->method) {
                'get' => OA\Get::class,
                'post' => OA\Post::class,
                'put' => OA\Put::class,
                'delete' => OA\Delete::class,
                'patch' => OA\Patch::class,
                'options' => OA\Options::class,
                'head' => OA\Head::class,
                'trace' => OA\Trace::class,
                default => null,
            };
        }

        return self::CLASS_MAP[get_class($attribute)] ?? null;
    }

    /**
     * Extract non-null properties from a Spec attribute and convert to classic format.
     * null → Generator::UNDEFINED for scalar/object properties.
     * null → Generator::UNDEFINED for array properties.
     * Empty arrays stay as-is (they are explicitly set).
     *
     * @return array<string, mixed>
     */
    protected function extractProperties(OpenApiAttributeInterface $attribute): array
    {
        $properties = [];

        $ref = new \ReflectionObject($attribute);
        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $name = $prop->getName();

            if (in_array($name, ['sourceLocation', 'reflector', 'attachables', 'x', 'method'], true)) {
                continue;
            }

            if ($name === 'schema' && $this->classicExtendsSchema($attribute)) {
                continue;
            }

            if (!$prop->isInitialized($attribute)) {
                continue;
            }

            $value = $prop->getValue($attribute);

            if ($value === null) {
                continue;
            }

            if ($value instanceof AbstractAttribute) {
                $nested = $this->convertNested($value);
                if ($nested !== null) {
                    $properties[$name] = $nested;
                }
            } elseif (is_array($value)) {
                $properties[$name] = $this->convertArray($value);
            } else {
                $properties[$name] = $value;
            }
        }

        if ($attribute instanceof AbstractAttribute) {
            if ($attribute->x !== null) {
                $properties['x'] = $attribute->x;
            }
            if ($attribute->attachables !== []) {
                $properties['attachables'] = $this->convertArray($attribute->attachables);
            }
        }

        return $properties;
    }

    protected function convertNested(AbstractAttribute $attribute): ?OA\AbstractAnnotation
    {
        $classicClass = self::CLASS_MAP[get_class($attribute)] ?? null;
        if ($classicClass === null) {
            return null;
        }

        $properties = $this->extractProperties($attribute);
        $this->flattenSchema($attribute, $classicClass, $properties);
        $properties['_context'] = new Context(['nested' => null, 'generated' => true]);

        return new $classicClass($properties);
    }

    /**
     * @return list<mixed>
     */
    protected function convertArray(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            if ($item instanceof AbstractAttribute) {
                $nested = $this->convertNested($item);
                if ($nested !== null) {
                    $result[] = $nested;
                }
            } else {
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * If the classic target class extends OA\Schema, flatten the DTO's $schema
     * properties directly onto the classic instance (since it IS a schema).
     *
     * @param class-string<OA\AbstractAnnotation> $classicClass
     */
    protected function flattenSchema(OpenApiAttributeInterface $attribute, string $classicClass, array &$properties): void
    {
        if (!$this->classicExtendsSchema($attribute)) {
            return;
        }

        $schema = $attribute->schema ?? null;
        if ($schema instanceof AbstractAttribute) {
            $schemaProps = $this->extractProperties($schema);
            $properties = array_merge($properties, $schemaProps);
        }
    }

    /**
     * Check if this DTO has a $schema property AND its classic target extends OA\Schema.
     */
    protected function classicExtendsSchema(OpenApiAttributeInterface $attribute): bool
    {
        if ($attribute instanceof Schema) {
            return false;
        }

        if (!property_exists($attribute, 'schema')) {
            return false;
        }

        $classicClass = self::CLASS_MAP[get_class($attribute)] ?? null;

        return $classicClass !== null && is_a($classicClass, OA\Schema::class, true);
    }
}
