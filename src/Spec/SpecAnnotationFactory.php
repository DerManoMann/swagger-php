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
        if (!$reflector instanceof \ReflectionClass
            && !$reflector instanceof \ReflectionMethod
            && !$reflector instanceof \ReflectionProperty
            && !$reflector instanceof \ReflectionParameter
            && !$reflector instanceof \ReflectionClassConstant
        ) {
            return [];
        }

        if ($reflector instanceof \ReflectionProperty && $reflector->isPromoted()) {
            return [];
        }

        $specAttributes = $this->assembler->instantiate($reflector);

        if (!$specAttributes) {
            return [];
        }

        $annotations = [];
        foreach ($specAttributes as $instance) {
            $ctx = $this->resolveContext($instance, $context);
            $converted = $this->convert($instance, $ctx);
            if ($converted !== null) {
                $annotations[] = $converted;
            }
        }

        return $annotations;
    }

    protected function resolveContext(OpenApiAttributeInterface $instance, Context $methodContext): Context
    {
        $reflector = $instance->getReflector();
        if ($reflector === null) {
            return $methodContext;
        }

        if ($reflector instanceof \ReflectionParameter) {
            $ctx = new Context([
                'nested' => null,
                'property' => $reflector->getName(),
                'reflector' => $reflector,
            ], $methodContext);

            if ($reflector->isPromoted()) {
                $ctx = new Context([
                    'generated' => true,
                    'annotations' => [],
                    'property' => $reflector->getName(),
                    'reflector' => $reflector,
                ], $methodContext);

                $property = $reflector->getDeclaringClass()->getProperty($reflector->getName());
                if ($comment = $property->getDocComment()) {
                    $ctx->comment = $comment;
                }
            }

            return $ctx;
        }

        return $methodContext;
    }

    protected function convert(OpenApiAttributeInterface $attribute, Context $context): ?OA\AbstractAnnotation
    {
        if ($attribute instanceof OpenApi) {
            $props = ['_context' => $context, 'openapi' => $attribute->version ?? OA\OpenApi::DEFAULT_VERSION];
            if ($attribute->security !== null) {
                $props['security'] = $attribute->security;
            }
            if ($attribute->getExtensions() !== null) {
                $props['x'] = $attribute->getExtensions();
            }

            return new OA\OpenApi($props);
        }

        $classicClass = $this->resolveClassicClass($attribute);
        if ($classicClass === null) {
            return null;
        }

        $properties = $this->extractProperties($attribute, $context);
        $this->flattenSchema($attribute, $classicClass, $properties, $context);
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
    protected function extractProperties(OpenApiAttributeInterface $attribute, ?Context $parentContext = null): array
    {
        $properties = [];

        $ref = new \ReflectionObject($attribute);
        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            $name = $prop->getName();

            if (in_array($name, ['attachables', 'x', 'method'], true)) {
                continue;
            }

            if ($name === 'schema' && $this->classicExtendsSchema($attribute)) {
                continue;
            }

            if (!$prop->isInitialized($attribute)) {
                continue;
            }

            $value = $prop->getValue($attribute);

            if ($value === Generator::UNDEFINED) {
                continue;
            }

            if ($value === null && !$this->hasUndefinedDefault($attribute, $name)) {
                continue;
            }

            if ($value instanceof OpenApiAttributeInterface) {
                $nested = $this->convertNested($value, $parentContext);
                if ($nested !== null) {
                    $properties[$name] = $nested;
                }
            } elseif (is_array($value)) {
                $properties[$name] = $this->convertArray($value, $parentContext);
            } else {
                $properties[$name] = $value;
            }
        }

        if ($attribute->getExtensions() !== null) {
            $properties['x'] = $attribute->getExtensions();
        }
        if ($attribute->getAttachables() !== []) {
            $properties['attachables'] = $this->convertArray($attribute->getAttachables(), $parentContext);
        }

        return $properties;
    }

    protected function convertNested(OpenApiAttributeInterface $attribute, ?Context $parentContext = null): ?OA\AbstractAnnotation
    {
        $classicClass = self::CLASS_MAP[get_class($attribute)] ?? null;
        if ($classicClass === null) {
            return null;
        }

        $context = $this->buildNestedContext($attribute, $parentContext);
        $properties = $this->extractProperties($attribute, $context);
        $this->flattenSchema($attribute, $classicClass, $properties, $context);
        $properties['_context'] = $context;

        return new $classicClass($properties);
    }

    protected function buildNestedContext(OpenApiAttributeInterface $attribute, ?Context $parentContext): Context
    {
        $reflector = $attribute->getReflector();

        if ($reflector instanceof \ReflectionParameter) {
            $ctx = new Context([
                'nested' => null,
                'property' => $reflector->getName(),
                'reflector' => $reflector,
            ], $parentContext);

            if ($reflector->isPromoted()) {
                $ctx = new Context([
                    'generated' => true,
                    'annotations' => [],
                    'property' => $reflector->getName(),
                    'reflector' => $reflector,
                ], $parentContext);

                $property = $reflector->getDeclaringClass()->getProperty($reflector->getName());
                if ($comment = $property->getDocComment()) {
                    $ctx->comment = $comment;
                }
            }

            return $ctx;
        }

        return new Context(['nested' => null, 'generated' => true, 'comment' => null], $parentContext);
    }

    protected function convertArray(array $items, ?Context $parentContext = null): array
    {
        $result = [];
        foreach ($items as $key => $item) {
            if ($item instanceof OpenApiAttributeInterface) {
                $nested = $this->convertNested($item, $parentContext);
                if ($nested !== null) {
                    $result[$key] = $nested;
                }
            } elseif (is_array($item)) {
                $result[$key] = $this->convertArray($item, $parentContext);
            } else {
                $result[$key] = $item;
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
    protected function flattenSchema(OpenApiAttributeInterface $attribute, string $classicClass, array &$properties, ?Context $parentContext = null): void
    {
        if (!$this->classicExtendsSchema($attribute)) {
            return;
        }

        $schema = $attribute->schema ?? null;
        if ($schema instanceof OpenApiAttributeInterface) {
            $schemaProps = $this->extractProperties($schema, $parentContext);
            $properties = array_merge($properties, $schemaProps);
        }
    }

    protected function hasUndefinedDefault(OpenApiAttributeInterface $attribute, string $propertyName): bool
    {
        $ctor = (new \ReflectionClass($attribute))->getConstructor();
        if ($ctor === null) {
            return false;
        }

        foreach ($ctor->getParameters() as $param) {
            if ($param->getName() === $propertyName) {
                return $param->isDefaultValueAvailable() && $param->getDefaultValue() === Generator::UNDEFINED;
            }
        }

        return false;
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
