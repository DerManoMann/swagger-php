<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Type;

use OpenApi\Specification\ComponentKey;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Radebatz\TypeInfoExtras\Type\ExplicitType;
use Radebatz\TypeInfoExtras\Type\IntRangeType;
use Radebatz\TypeInfoExtras\TypeResolver\StringTypeResolver;
use Symfony\Component\TypeInfo\Exception\InvalidArgumentException;
use Symfony\Component\TypeInfo\Exception\UnsupportedException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\ArrayShapeType;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\CompositeTypeInterface;
use Symfony\Component\TypeInfo\Type\GenericType;
use Symfony\Component\TypeInfo\Type\IntersectionType;
use Symfony\Component\TypeInfo\Type\NullableType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\TemplateType;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeIdentifier;
use Symfony\Component\TypeInfo\TypeResolver\ReflectionTypeResolver;
use Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver as SymfonyStringTypeResolver;

/**
 * Resolves a PHP reflector to a SchemaType value object.
 *
 * Shared core between the spec-attributes augmenter and the classic annotation resolver.
 * Uses Symfony TypeInfo + phpstan/phpdoc-parser for reflective type resolution.
 */
class TypeResolver implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected TypeMapper $typeMapper;

    /** @var array<string, list<string>> the templates of each class asked about, by class */
    protected array $templateNames = [];

    public function __construct()
    {
        $this->typeMapper = new TypeMapper();
    }

    /**
     * Resolve the PHP type of a reflector into a SchemaType.
     *
     * @param \ReflectionProperty|\ReflectionParameter|\ReflectionMethod|\ReflectionClassConstant $reflector
     */
    public function resolve(\Reflector $reflector): ?SchemaType
    {
        return $this->resolveWithTemplates($reflector, []);
    }

    /**
     * Resolve the PHP type of a reflector, with each template its docblock names replaced by a type.
     *
     * A member of a generic class reads `T` where the class is used as `Envelope<Customer>`. With
     * the templates from {@see templates()}, its type is the one that use means.
     *
     * @param \ReflectionProperty|\ReflectionParameter|\ReflectionMethod|\ReflectionClassConstant $reflector
     * @param array<string, Type>                                                                 $templates by template name
     */
    public function resolveWithTemplates(\Reflector $reflector, array $templates): ?SchemaType
    {
        $docblockType = $this->getDocblockType($reflector);
        $reflectionType = $this->getReflectionType($reflector);

        if ($docblockType instanceof Type && $templates !== []) {
            try {
                $bound = $this->bindTemplates($docblockType, $templates);
            } catch (InvalidArgumentException $exception) {
                // an argument the type cannot take, such as `int` in `T&\Countable`
                $this->logger?->warning(sprintf('Cannot use the type arguments in "%s" on %s, so its type is left out: %s', $docblockType, $this->describe($reflector), $exception->getMessage()));
                $bound = Type::mixed();
            }
            // `mixed` is all PHP can declare for a template, and its `null` is not the argument's
            if ((string) $bound !== (string) $docblockType && (string) $reflectionType === 'mixed') {
                $reflectionType = null;
            }
            $docblockType = $bound;
        }

        if (!$docblockType && !$reflectionType) {
            return null;
        }

        $nullable = null;
        if (($docblockType instanceof Type && $docblockType->isNullable()) || ($reflectionType instanceof Type && $reflectionType->isNullable())) {
            $nullable = true;
        }

        $docblockType = $docblockType instanceof NullableType ? $docblockType->getWrappedType() : $docblockType;
        $reflectionType = $reflectionType instanceof NullableType ? $reflectionType->getWrappedType() : $reflectionType;

        $effectiveType = $docblockType ?? $reflectionType;
        if (!$effectiveType instanceof Type) {
            return $nullable !== null ? new SchemaType(nullable: $nullable) : null;
        }

        $result = $this->mapType($effectiveType);
        $result->nullable = $nullable;

        $this->applyNativeTypeMapping($result);

        return $result;
    }

    public function getDocblockType(\Reflector $reflector): ?Type
    {
        $docComment = match (true) {
            $reflector instanceof \ReflectionProperty => $reflector->isPromoted()
                && $reflector->getDeclaringClass()->getConstructor() instanceof \ReflectionMethod
                    ? $reflector->getDeclaringClass()->getConstructor()->getDocComment()
                    : $reflector->getDocComment(),
            $reflector instanceof \ReflectionParameter => $reflector->getDeclaringFunction()->getDocComment(),
            $reflector instanceof \ReflectionFunctionAbstract => $reflector->getDocComment(),
            default => null,
        };

        if (!$docComment) {
            return null;
        }

        $typeContext = (new TypeContextFactory(new SymfonyStringTypeResolver()))->createFromReflection($reflector);

        $tagName = match (true) {
            $reflector instanceof \ReflectionProperty => $reflector->isPromoted()
                ? '@param'
                : '@var',
            $reflector instanceof \ReflectionParameter => '@param',
            $reflector instanceof \ReflectionFunctionAbstract => '@return',
            default => null,
        };

        if ($tagName === null) {
            return null;
        }

        $lexer = new Lexer(new ParserConfig([]));
        $config = new ParserConfig([]);
        $constExprParser = new ConstExprParser($config);
        $phpDocParser = new PhpDocParser(
            $config,
            new TypeParser($config, $constExprParser),
            $constExprParser,
        );

        $tokens = new TokenIterator($lexer->tokenize($docComment));
        $docNode = $phpDocParser->parse($tokens);

        foreach ($docNode->getTagsByName($tagName) as $tag) {
            $tagValue = $tag->value;

            if (
                $tagValue instanceof VarTagValueNode
                || ($tagValue instanceof ParamTagValueNode && '$' . $reflector->getName() === $tagValue->parameterName)
                || $tagValue instanceof ReturnTagValueNode
            ) {
                try {
                    return (new StringTypeResolver())->resolve((string) $tagValue, $typeContext);
                } catch (UnsupportedException|InvalidArgumentException) {
                    $this->logger?->warning(sprintf('Unsupported docblock type "%s" on %s', $tagValue->type, $this->describe($reflector)));
                }
            }
        }

        return null;
    }

    /**
     * The type arguments a key carries, by the name of the template each one fills, or none for
     * a key without them.
     *
     * Templates are matched by position, as PHPStan and Psalm do. An argument without a template,
     * or a template without an argument, is left out.
     *
     * @return array<string, Type>
     */
    public function templates(?ComponentKey $key): array
    {
        $arguments = $key?->qualifiers[ComponentKey::TYPE_ARGUMENTS] ?? null;
        if (!$key instanceof ComponentKey || !is_string($arguments)) {
            return [];
        }

        $variables = TypeArguments::parse($key->class, $arguments) ?? [];
        $names = $this->templateNames($key->class);
        $count = min(count($names), count($variables));

        return array_combine(array_slice($names, 0, $count), array_slice($variables, 0, $count));
    }

    /**
     * Name a member for a log message: `Pet::$name`, `Pet::name()`, `$id of Pet::get()`.
     */
    public function describe(\Reflector $reflector): string
    {
        return match (true) {
            $reflector instanceof \ReflectionProperty => $reflector->class . '::$' . $reflector->name,
            $reflector instanceof \ReflectionParameter => '$' . $reflector->name . ' of ' . $this->describe($reflector->getDeclaringFunction()),
            $reflector instanceof \ReflectionMethod => $reflector->class . '::' . $reflector->name . '()',
            $reflector instanceof \ReflectionFunctionAbstract => $reflector->name . '()',
            default => $reflector::class,
        };
    }

    protected function mapType(Type $type): SchemaType
    {
        if ($type instanceof CompositeTypeInterface) {
            return $this->mapCompositeType($type);
        }

        if ($type instanceof BuiltinType) {
            return $this->mapBuiltinType($type);
        }

        if ($type instanceof ObjectType) {
            // In the global namespace a short-name docblock comes back as `\Foo`, while
            // `\Foo` itself comes back as `Foo`. `ComponentIndex` keys on `getClassName()`,
            // which carries no leading slash.
            $className = ltrim($type->getClassName(), '\\');

            if ($className === \stdClass::class) {
                return new SchemaType(type: 'object');
            }

            // the date classes themselves are mapped by name, a subclass of them by what it is
            if (is_a($className, \DateTimeInterface::class, true)) {
                return new SchemaType(type: 'string', format: 'date-time');
            }

            return new SchemaType(type: $className);
        }

        if ($type instanceof IntRangeType) {
            // an open end comes as `PHP_INT_MIN`/`PHP_INT_MAX`, which is no bound of the API
            return new SchemaType(
                type: $type->getTypeIdentifier()->value,
                minimum: \PHP_INT_MIN === $type->getFrom() ? null : $type->getFrom(),
                maximum: \PHP_INT_MAX === $type->getTo() ? null : $type->getTo(),
            );
        }

        if ($type instanceof ExplicitType) {
            return new SchemaType(type: $type->getTypeIdentifier()->value);
        }

        if ($type instanceof ArrayShapeType && [] !== $type->getShape()) {
            return $this->mapArrayShape($type);
        }

        if ($type instanceof CollectionType) {
            return $this->mapCollectionType($type);
        }

        // `Foo<T>` on a class: the class, with its arguments kept where the class declares
        // templates for them to fill, so the use can reference a schema made for them. Last,
        // so `array<...>` and `int<...>` reach the arms above.
        if ($type instanceof GenericType) {
            $result = $this->mapType($type->getWrappedType());
            if ($result->isRef() && $this->templateNames($result->type) !== []) {
                $result->typeArguments = $this->typeArguments($type);
            }

            return $result;
        }

        return new SchemaType();
    }

    /**
     * The arguments as one type string, or null when every one is `mixed`, which says nothing a
     * template does not, or when one is still a template, as in the class's own `Node<T>`.
     *
     * @param GenericType<ObjectType<class-string>> $type
     */
    protected function typeArguments(GenericType $type): ?string
    {
        $arguments = $type->getVariableTypes();
        $mixed = array_filter($arguments, static fn (Type $argument): bool => (string) $argument === 'mixed');
        $templates = array_filter($arguments, $this->mentionsTemplate(...));

        return count($mixed) === count($arguments) || $templates !== [] ? null : TypeArguments::spell(...$arguments);
    }

    /**
     * Whether a template occurs anywhere in the type. Type-info's own `traverse()` does not reach
     * a generic type's arguments.
     */
    protected function mentionsTemplate(Type $type): bool
    {
        return match (true) {
            $type instanceof TemplateType => true,
            $type instanceof GenericType => array_filter($type->getVariableTypes(), $this->mentionsTemplate(...)) !== [],
            $type instanceof CollectionType, $type instanceof NullableType => $this->mentionsTemplate($type->getWrappedType()),
            $type instanceof CompositeTypeInterface => array_filter($type->getTypes(), $this->mentionsTemplate(...)) !== [],
            default => false,
        };
    }

    /**
     * The templates a class declares, in declaration order.
     *
     * @return list<string>
     */
    protected function templateNames(string $class): array
    {
        if (!class_exists($class) && !interface_exists($class)) {
            return [];
        }

        return $this->templateNames[$class] ??= array_keys((new TypeContextFactory(new SymfonyStringTypeResolver()))->createFromClassName($class)->templates);
    }

    /**
     * The type with each template replaced by its argument. Type-info has no helper for this, so
     * the type tree is rebuilt wherever a template occurs in it.
     *
     * @param array<string, Type> $templates
     */
    protected function bindTemplates(Type $type, array $templates): Type
    {
        return match (true) {
            $type instanceof TemplateType => $templates[$type->getName()] ?? $type,
            $type instanceof NullableType => Type::nullable($this->bindTemplates($type->getWrappedType(), $templates)),
            $type instanceof ArrayShapeType => $this->bindArrayShape($type, $templates),
            $type instanceof CollectionType => new CollectionType(
                $type->getWrappedType() instanceof GenericType ? $this->bindGeneric($type->getWrappedType(), $templates) : $type->getWrappedType(),
                $type->isList(),
            ),
            $type instanceof GenericType => $this->bindGeneric($type, $templates),
            $type instanceof UnionType => $this->bindUnion($type, $templates),
            $type instanceof IntersectionType => Type::intersection(...array_map(fn (Type $member): Type => $this->bindTemplates($member, $templates), $type->getTypes())),
            default => $type,
        };
    }

    /**
     * An array shape with each template replaced. It extends a collection, whose rebuild would
     * drop the shape.
     *
     * @param array<string, Type> $templates
     */
    protected function bindArrayShape(ArrayShapeType $type, array $templates): ArrayShapeType
    {
        $shape = array_map(fn (array $member): array => ['type' => $this->bindTemplates($member['type'], $templates), 'optional' => $member['optional']], $type->getShape());
        $extraValueType = $type->getExtraValueType();

        return new ArrayShapeType(
            $shape,
            $type->getExtraKeyType(),
            $extraValueType instanceof Type ? $this->bindTemplates($extraValueType, $templates) : null,
        );
    }

    /**
     * A union with each template replaced. One that takes `mixed` is `mixed`, which type-info does
     * not allow in a union.
     *
     * @param UnionType<Type>     $type
     * @param array<string, Type> $templates
     */
    protected function bindUnion(UnionType $type, array $templates): Type
    {
        $members = array_map(fn (Type $member): Type => $this->bindTemplates($member, $templates), $type->getTypes());
        foreach ($members as $member) {
            if ($member->isIdentifiedBy(TypeIdentifier::MIXED)) {
                return Type::mixed();
            }
        }

        return Type::union(...$members);
    }

    /**
     * @template T of BuiltinType<TypeIdentifier::ARRAY>|BuiltinType<TypeIdentifier::ITERABLE>|ObjectType<class-string>
     *
     * @param GenericType<T>      $type
     * @param array<string, Type> $templates
     *
     * @return GenericType<T>
     */
    protected function bindGeneric(GenericType $type, array $templates): GenericType
    {
        return new GenericType($type->getWrappedType(), ...array_map(fn (Type $variable): Type => $this->bindTemplates($variable, $templates), $type->getVariableTypes()));
    }

    /**
     * @param CompositeTypeInterface<Type> $type
     */
    protected function mapCompositeType(CompositeTypeInterface $type): SchemaType
    {
        $types = $type->getTypes();

        $isNonZeroInt = 2 === count($types) && $types[0] instanceof IntRangeType && $types[1] instanceof IntRangeType;
        if ($isNonZeroInt) {
            return new SchemaType(type: 'int', not: ['const' => 0]);
        }

        $allBuiltin = array_reduce($types, static fn ($carry, $t): bool => $carry && $t instanceof BuiltinType, true);

        if ($type instanceof UnionType) {
            if ($allBuiltin) {
                $mappableTypes = array_values(array_filter(
                    array_map(static fn (Type $t): string => (string) $t, $types),
                    $this->typeMapper->hasOpenApiType(...),
                ));

                return new SchemaType(type: [] === $mappableTypes ? null : $mappableTypes);
            }

            $builtinTypes = array_filter($types, static fn (Type $t): bool => $t instanceof BuiltinType);
            $otherTypes = array_filter($types, static fn (Type $t): bool => !$t instanceof BuiltinType);

            $oneOf = [];
            if ($builtinTypes !== []) {
                $builtinSchema = new SchemaType(
                    type: array_values(array_map(static fn (Type $t): string => (string) $t, $builtinTypes)),
                );
                $this->applyNativeTypeMapping($builtinSchema);
                $oneOf[] = $builtinSchema;
            }

            foreach ($otherTypes as $otherType) {
                $oneOf[] = $this->mapType($otherType);
            }

            return new SchemaType(oneOf: $oneOf);
        }

        if ($type instanceof IntersectionType) {
            $allOf = [];
            foreach ($types as $intersectionType) {
                $allOf[] = $this->mapType($intersectionType);
            }

            return new SchemaType(allOf: $allOf);
        }

        return new SchemaType();
    }

    /**
     * @param BuiltinType<TypeIdentifier> $type
     */
    protected function mapBuiltinType(BuiltinType $type): SchemaType
    {
        $typeName = (string) $type;
        if ($this->typeMapper->hasOpenApiType($typeName)) {
            return new SchemaType(type: $typeName);
        }

        return new SchemaType();
    }

    /**
     * @param CollectionType<BuiltinType<TypeIdentifier::ARRAY>|BuiltinType<TypeIdentifier::ITERABLE>|ObjectType<class-string>|GenericType<BuiltinType<TypeIdentifier::ARRAY>|BuiltinType<TypeIdentifier::ITERABLE>|ObjectType<class-string>>> $type
     */
    protected function mapCollectionType(CollectionType $type): SchemaType
    {
        if ($type->isList() || $type->getCollectionKeyType() instanceof UnionType) {
            $itemType = $this->mapType($type->getCollectionValueType());
            $this->applyNativeTypeMapping($itemType);

            return new SchemaType(type: 'array', items: $itemType);
        }

        $valueSchema = $this->mapType($type->getCollectionValueType());
        $this->applyNativeTypeMapping($valueSchema);

        return new SchemaType(type: 'object', additionalProperties: $valueSchema);
    }

    protected function mapArrayShape(ArrayShapeType $type): SchemaType
    {
        $shape = $type->getShape();

        if (array_is_list($shape)) {
            $itemType = $this->mapType($type->getCollectionValueType());
            $this->applyNativeTypeMapping($itemType);

            return new SchemaType(type: 'array', items: $itemType);
        }

        $properties = [];
        $required = [];
        foreach ($shape as $name => $member) {
            $propertyName = (string) $name;
            $propertySchema = $this->mapType($member['type']);
            $this->applyNativeTypeMapping($propertySchema);
            $properties[$propertyName] = $propertySchema;

            if (!($member['optional'] ?? false)) {
                $required[] = $propertyName;
            }
        }

        $result = new SchemaType(type: 'object', properties: $properties);
        if ([] !== $required) {
            $result->required = $required;
        }

        if ($type->getExtraValueType() instanceof Type) {
            $result->additionalProperties = true;
        }

        return $result;
    }

    /**
     * Apply native PHP type to OpenAPI type/format mapping in-place.
     */
    protected function applyNativeTypeMapping(SchemaType $schema): void
    {
        if (is_string($schema->type)) {
            $mapped = $this->typeMapper->map($schema->type);
            if ($mapped === null) {
                // not a native type — leave as-is (likely a FQCN for ref resolution)
            } elseif ('mixed' === $mapped['type']) {
                $schema->type = null;
            } else {
                $schema->type = $mapped['type'];
                if ($mapped['format'] !== null && $schema->format === null) {
                    $schema->format = $mapped['format'];
                }
            }
        } elseif (is_array($schema->type)) {
            /** @var list<string> $mapped neither array_map() nor toSpecTypes() change a list's shape */
            $mapped = $this->typeMapper->toSpecTypes(
                array_map(static fn ($t): string => strtolower((string) $t), $schema->type),
            );
            $schema->type = $mapped;
        }
    }

    /**
     * @param \ReflectionProperty|\ReflectionParameter|\ReflectionMethod|\ReflectionClass<object>|\ReflectionClassConstant $reflector
     */
    protected function getReflectionType(\Reflector $reflector): ?Type
    {
        $subject = $reflector instanceof \ReflectionClass
            ? $reflector->getName()
            : (
                $reflector instanceof \ReflectionMethod
                ? $reflector->getReturnType()
                : (method_exists($reflector, 'getType') ? $reflector->getType() : null)
            );

        try {
            $typeContext = (new TypeContextFactory())->createFromReflection($reflector);

            return (new ReflectionTypeResolver())->resolve($subject, $typeContext);
        } catch (UnsupportedException) {
            return null;
        }
    }
}
