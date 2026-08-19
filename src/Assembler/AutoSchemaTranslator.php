<?php declare(strict_types=1);

namespace OpenApi\Assembler;

use OpenApi\Spec as OA;

/*
 * @license Apache 2.0
 */

/**
 * Auto generates `OA\Schema` / `OA\Property` attributes for the given reflector.
 *
 * This should only ever be used on a specialized assembler for specific purposes; example: `AutoAnnotate`.
 */
class AutoSchemaTranslator extends AbstractAttributeTranslator
{
    public function translate(array $attributes, array $created, \ReflectionClassConstant|\ReflectionParameter|\ReflectionMethod|\ReflectionClass|\ReflectionProperty $reflector): array
    {
        $processed = parent::translate($attributes, $created, $reflector);

        if ($reflector instanceof \ReflectionClass) {
            $processed[] = new OA\Schema();
        }

        if ($reflector instanceof \ReflectionProperty && $reflector->isPublic()) {
            $processed[] = new OA\Property();
        }

        if ($reflector instanceof \ReflectionParameter) {
            $method = $reflector->getDeclaringFunction();
            if ($method->getName() === '__construct') {
                $processed[] = new OA\Property();
            }
        }

        return $processed;
    }
}
