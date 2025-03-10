<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Processors\Concerns;

use OpenApi\Annotations as OA;
use OpenApi\Generator;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use Radebatz\TypeInfoExtras\Type\ExplicitType;
use Radebatz\TypeInfoExtras\Type\IntRangeType;
use Radebatz\TypeInfoExtras\TypeResolver\StringTypeResolver;
use Symfony\Component\TypeInfo\Exception\UnsupportedException;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\TypeInfo\Type\BuiltinType;
use Symfony\Component\TypeInfo\Type\CollectionType;
use Symfony\Component\TypeInfo\Type\NullableType;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeResolver\ReflectionTypeResolver;

trait TypesTrait
{
    protected static $NATIVE_TYPE_MAP = [
        'array' => 'array',
        'byte' => ['string', 'byte'],
        'boolean' => 'boolean',
        'bool' => 'boolean',
        'int' => 'integer',
        'integer' => 'integer',
        'long' => ['integer', 'long'],
        'float' => ['number', 'float'],
        'double' => ['number', 'double'],
        'string' => 'string',
        'date' => ['string', 'date'],
        'datetime' => ['string', 'date-time'],
        '\\datetime' => ['string', 'date-time'],
        'datetimeimmutable' => ['string', 'date-time'],
        '\\datetimeimmutable' => ['string', 'date-time'],
        'datetimeinterface' => ['string', 'date-time'],
        '\\datetimeinterface' => ['string', 'date-time'],
        'number' => 'number',
        'object' => 'object',
    ];

    public function mapNativeType(OA\Schema $schema, string $type): bool
    {
        if (!array_key_exists($type, self::$NATIVE_TYPE_MAP)) {
            return false;
        }

        $type = self::$NATIVE_TYPE_MAP[$type];
        if (is_array($type)) {
            if (Generator::isDefault($schema->format)) {
                $schema->format = $type[1];
            }
            $type = $type[0];
        }

        $schema->type = $type;

        return true;
    }

    public function native2spec(string $type): string
    {
        $mapped = array_key_exists($type, self::$NATIVE_TYPE_MAP) ? self::$NATIVE_TYPE_MAP[$type] : $type;

        return is_array($mapped) ? $mapped[0] : $mapped;
    }

    protected function normaliseTypeResult(\Reflector $reflector, ?Type $resolved): \stdClass
    {
        $details = (object) [
            'explicitType' => null,
            'types' => [],
            'name' => null,
            'nullable' => null,
            'isArray' => null,
        ];

        if (!$resolved) {
            $details->nullable = true;

            return $details;
        }

        $details->name = $reflector->getName();

        $details->nullable = $resolved instanceof NullableType;
        if ($details->nullable) {
            $resolved = $resolved->getWrappedType();
        }

        if ($resolved instanceof CollectionType) {
            $details->isArray = true;
            $resolved = $resolved->getCollectionValueType();
        }

        if ($resolved instanceof BuiltinType || $resolved instanceof ObjectType) {
            $details->explicitType = (string) $resolved;
            $details->types[] = (string) $resolved;
        } elseif ($resolved instanceof IntRangeType) {
            // use just `int` for custom `int<..>`
            $details->explicitType = str_contains($resolved->getExplicitType(), '<')
                ? $resolved->getTypeIdentifier()->value
                : $resolved->getExplicitType();
            $details->types[] = $resolved->getTypeIdentifier()->value;
        } elseif ($resolved instanceof ExplicitType) {
            $details->explicitType = $resolved->getExplicitType();
            $details->types[] = $resolved->getTypeIdentifier()->value;
        }

        return $details;
    }

    public function getTypeDetailsFromTypeInfoReflection(\Reflector $reflector): \stdClass
    {
        $subject = $reflector instanceof \ReflectionMethod
            ? $reflector->getReturnType()
            : $reflector->getType();
        try {
            $typeContext ??= (new TypeContextFactory())->createFromReflection($reflector);
            $resolved = (new ReflectionTypeResolver())->resolve($subject, $typeContext);
        } catch (UnsupportedException $exception) {
            $resolved = null;
        }

        return $this->normaliseTypeResult($reflector, $resolved);
    }

    public function getTypeDetailsFromTypeInfoDocblock(\Reflector $reflector): \stdClass
    {
        switch (true) {
            case $reflector instanceof \ReflectionProperty:
                $docComment = (method_exists($reflector, 'isPromoted') && $reflector->isPromoted())
                && $reflector->getDeclaringClass() && $reflector->getDeclaringClass()->getConstructor()
                    ? $reflector->getDeclaringClass()->getConstructor()->getDocComment()
                    : $reflector->getDocComment();
                break;
            case $reflector instanceof \ReflectionParameter:
                $docComment = $reflector->getDeclaringFunction()->getDocComment();
                break;
            case $reflector instanceof \ReflectionFunctionAbstract:
                $docComment = $reflector->getDocComment();
                break;
            default:
                $docComment = null;
        }

        if (!$docComment) {
            return $this->normaliseTypeResult($reflector, null);
        }

        $typeContext ??= (new TypeContextFactory())->createFromReflection($reflector);

        switch (true) {
            case $reflector instanceof \ReflectionProperty:
                $tagName = (method_exists($reflector, 'isPromoted') && $reflector->isPromoted())
                    ? '@param'
                    : '@var';
                break;
            case $reflector instanceof \ReflectionParameter:
                $tagName = '@param';
                break;
            case $reflector instanceof \ReflectionFunctionAbstract:
                $tagName = '@return';
                break;
            default:
                $tagName = null;

        }

        $lexer = new Lexer(new ParserConfig([]));
        $phpDocParser = new PhpDocParser(
            $config = new ParserConfig([]),
            new TypeParser($config, $constExprParser = new ConstExprParser($config)),
            $constExprParser,
        );

        $tokens = new TokenIterator($lexer->tokenize($docComment));
        $docNode = $phpDocParser->parse($tokens);

        foreach ($docNode->getTagsByName($tagName) as $tag) {
            $tagValue = $tag->value;

            if (
                $tagValue instanceof VarTagValueNode
                || $tagValue instanceof ParamTagValueNode && $tagName && '$' . $reflector->getName() === $tagValue->parameterName
                || $tagValue instanceof ReturnTagValueNode
            ) {
                $resolved = (new StringTypeResolver())->resolve((string) $tagValue, $typeContext);

                return $this->normaliseTypeResult($reflector, $resolved);
            }
        }

        return $this->normaliseTypeResult($reflector, null);
    }
}
