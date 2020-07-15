<?php declare(strict_types=1);

namespace OpenApi\Parser;

use OpenApi\Context;

interface AnnotationFactoryInterface
{
    public function build(\Reflector $reflector, Context $context);
}
