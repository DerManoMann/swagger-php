<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Processors\Concerns;

use OpenApi\Analysers\TokenScanner;
use OpenApi\Annotations as OA;
use OpenApi\Context;
use OpenApi\Generator;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;

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

    protected function getTypeDetailsFromDocblock(\Reflector $reflector, ?Context $context = null): \stdClass
    {
        $details = (object) [
            'types' => [],
            'name' => null,
            'nullable' => null,
            'isArray' => null,
        ];

        $docblockReflector = $reflector;

        // promoted parameter - docblock is available via class/property
        if ($reflector instanceof \ReflectionParameter && method_exists($reflector, 'isPromoted') && $reflector->isPromoted()) {
            $docblockReflector = $reflector->getDeclaringClass()->getProperty($reflector->getName());
        }

        $docblock = $docblockReflector->getDocComment();

        if (Generator::isDefault($docblock) || empty($docblock)) {
            return $details;
        }

        $config = new ParserConfig([]);
        $constExprParser = new ConstExprParser($config);
        $phpDocParser = new PhpDocParser($config, new TypeParser($config, $constExprParser), $constExprParser);

        $phpDocNode = $phpDocParser->parse(new TokenIterator((new Lexer($config))->tokenize($docblock)));

        foreach (['var', 'param'] as $tag) {
            foreach ($phpDocNode->getTagsByName("@$tag") as $tagLine) {
                if ($tagLine instanceof PhpDocTagNode && $tagLine->value instanceof PhpDocTagValueNode) {
                    if ($tagLine->value->type instanceof UnionTypeNode) {
                        foreach ($tagLine->value->type->types as $type) {
                            if ($type instanceof GenericTypeNode) {
                                $genericTypes = array_map(fn (TypeNode $type) => trim($type->name), $type->genericTypes);
                                $details->types = array_merge($details->types, $genericTypes);

                                if ('array' === $type->type->name) {
                                    $details->isArray = true;
                                }
                            } elseif ($type instanceof ArrayShapeNode) {
                                $details->types[] = $type->kind;
                            } else {
                                $details->types[] = $type->name;
                            }
                        }
                    } else {
                        if ($tagLine->value->type instanceof ArrayTypeNode) {
                            $valueType = $tagLine->value->type->type;
                            $details->isArray = true;
                            $details->types[] = $valueType->name;
                        } else {
                            if ($tagLine->value->type instanceof GenericTypeNode) {
                                if ($tagLine->value->type->type instanceof IdentifierTypeNode) {
                                    if ($tagLine->value->type->genericTypes) {
                                          $genericTypes = array_map(fn (TypeNode $type) => trim($type->name), $tagLine->value->type->genericTypes);
                                        $details->types = array_merge($details->types, $genericTypes);

                                        if ('array' === $tagLine->value->type->type->name) {
                                            $details->isArray = true;
                                        }
                                    } else {
                                        $details->types[] = $tagLine->value->type->type->name;
                                    }
                                } else {
                                    $genericTypes = array_map(fn (TypeNode $type) => trim($type->name), $tagLine->value->type->genericTypes);
                                    $details->types = array_merge($details->types, $genericTypes);

                                    if ('array' === $tagLine->value->type->type->name) {
                                        $details->isArray = true;
                                    }
                                }
                            } elseif ($tagLine->value->type instanceof ArrayShapeNode) {
                                $details->types[] = $tagLine->value->type->kind;
                            } elseif ($tagLine->value->type instanceof NullableTypeNode) {
                                if ($tagLine->value->type->type instanceof GenericTypeNode) {
                                    $genericTypes = array_map(fn (TypeNode $type) => trim($type->name), $tagLine->value->type->type->genericTypes);
                                    $details->types = array_merge($details->types, $genericTypes);

                                    if ('array' === $tagLine->value->type->type->type->name) {
                                        $details->isArray = true;
                                    }
                                } else {
                                    $details->types[] = $tagLine->value->type->type->name;
                                }
                                $details->nullable = true;
                            } else {
                                $details->types[] = $tagLine->value->type->name;
                            }
                        }
                    }
                    break;
                }
            }
        }

        if (in_array('null', $details->types)) {
            $details->nullable = true;
            $details->types = array_values(array_filter($details->types, fn ($item) => $item !== 'null'));
        } else {
            $details->nullable ??= false;
        }

        // map FQCN if we can
        if ($rc = $reflector->getDeclaringClass()) {
            $fileDetails = $context
                ? $context['scanned']
                : (new TokenScanner())->scanFile($rc->getFileName())[$rc->getName()];

            $resolve = function (string $type) use ($rc, $fileDetails) {
                if (in_array($type, $fileDetails['uses'])) {
                    return $fileDetails['uses'][$type];
                }

                if (!array_key_exists($type, self::$NATIVE_TYPE_MAP) && $rc->inNamespace()) {
                    if ('\\' !== $type[0]) {
                        return $rc->getNamespaceName() . '\\' . $type;
                    } else {
                        return ltrim($type, '\\');
                    }
                }

                return $type;
            };
            $details->types = array_map($resolve, $details->types);
        }

        return $details;
    }

    protected function getTypeDetailsFromReflector(\Reflector $reflector): \stdClass
    {
        $details = (object) [
            'types' => [],
            'name' => null,
            'nullable' => null,
            'isArray' => null,
        ];

        if ($reflector instanceof \ReflectionParameter || $reflector instanceof \ReflectionProperty) {
            if ($rt = $reflector->getType()) {
                $details->name = $reflector->getName();
                $details->nullable = $rt->allowsNull();

                if ($rt instanceof \ReflectionNamedType) {
                    $details->types[] = $rt->getName();
                } elseif ($rt instanceof \ReflectionUnionType) {
                    foreach ($rt->getTypes() as $rut) {
                        if ($rut instanceof \ReflectionNamedType) {
                            $details->types[] = $rut->getName();
                        }
                    }
                }
            }
        }

        if (1 == count($details->types) && in_array('array', $details->types, true)) {
            $details->types = [];
            $details->isArray = true;
        }

        return $details;
    }
}
