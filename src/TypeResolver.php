<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi;

use OpenApi\Type\LegacyTypeResolver;
use OpenApi\Type\TypeInfoTypeResolver;
use Reflector;
use stdClass;

class TypeResolver
{
    protected $resolver = null;

    public function __construct()
    {
        $this->resolver = new TypeInfoTypeResolver();
        $this->resolver = new LegacyTypeResolver();
    }

    public function getDockblockTypeDetails(Reflector $reflector): stdClass
    {
        return $this->resolver->getDockblockTypeDetails($reflector);
    }

    public function getReflectionTypeDetails(Reflector $reflector): stdClass
    {
        return $this->resolver->getReflectionTypeDetails($reflector);
    }
}
