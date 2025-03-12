<?php

namespace OpenApi\Type;

use Reflector;
use stdClass;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;

class LegacyTypeResolver
{

    protected function normaliseTypeResult(?string $explicitType = null, array $types = [], ?string $name = null, ?bool $nullable = null, ?bool $isArray = null): stdClass
    {
        $explicitType = $explicitType ?: ($types ? $types[0] : null);

        return (object)[
            'explicitType' => $explicitType,
            'types' => $types,
            'name' => $name,
            'nullable' => $explicitType ? $nullable : true,
            'isArray' => $isArray,
        ];
    }

    public function getReflectionTypeDetails(Reflector $reflector): stdClass
    {
        $subject = $reflector instanceof ReflectionMethod
            ? $reflector->getReturnType()
            : $reflector->getType();

        return $this->normaliseTypeResult();
    }

    public function getDockblockTypeDetails(Reflector $reflector): stdClass
    {
        switch (true) {
            case $reflector instanceof ReflectionProperty:
                $docComment = (method_exists($reflector, 'isPromoted') && $reflector->isPromoted())
                && $reflector->getDeclaringClass() && $reflector->getDeclaringClass()->getConstructor()
                    ? $reflector->getDeclaringClass()->getConstructor()->getDocComment()
                    : $reflector->getDocComment();
                break;
            case $reflector instanceof ReflectionParameter:
                $docComment = $reflector->getDeclaringFunction()->getDocComment();
                break;
            case $reflector instanceof ReflectionFunctionAbstract:
                $docComment = $reflector->getDocComment();
                break;
            default:
                $docComment = null;
        }

        if (!$docComment) {
            return $this->normaliseTypeResult();
        }

        switch (true) {
            case $reflector instanceof ReflectionProperty:
                $tagName = (method_exists($reflector, 'isPromoted') && $reflector->isPromoted())
                    ? '@param'
                    : '@var';
                break;
            case $reflector instanceof ReflectionParameter:
                $tagName = '@param';
                break;
            case $reflector instanceof ReflectionFunctionAbstract:
                $tagName = '@return';
                break;
            default:
                $tagName = null;
        }

        if (!$tagName) {
            return $this->normaliseTypeResult();
        }

        $docComment = str_replace("\r\n", "\n", $docComment);
        $docComment = preg_replace('/\*\/[ \t]*$/', '', $docComment); // strip '*/'
        preg_match("/$tagName\s+(?<type>[^\s]+)([ \t])?(?<description>.+)?$/im", $docComment, $matches);

        $explicitType = null;
        $type = $matches['type'];
        $nullable = in_array('null', explode('|', strtolower($type))) || str_contains($type, '?');
        $isArray = str_contains($type, '[]') || str_contains($type, 'array');
        $type = str_replace(['|null', 'null|', '?', 'null', '[]'], '', $type);

        // typed array
        $result = preg_match("/([^<]+)<([^>]+)>/", $type, $matches);
        if ($result) {
            if (!$isArray) {
                $type = $matches[1];
            } else {
                $type = $matches[2];
            }
        }

        // partial array shape
        $result = preg_match("/([^{]+){([^}]+)/", $type, $matches);
        if ($result) {
            $type = 'mixed';
        }

        // special types
        switch ($type) {
            case 'positive-int':
            case 'negative-int':
            case 'non-positive-int':
            case 'non-negative-int':
            case 'non-zero-int':
                $explicitType = $type;
                $type = 'int';
                break;
        }

        // cheat
        $name = $reflector->getName();

        return $this->normaliseTypeResult($explicitType, [$type], $name, $nullable, $isArray);
    }
}
