<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Parser;

use OpenApi\Context;

class DocBlockAnnotationFactory implements AnnotationFactoryInterface
{
    protected $docBlockParser;

    public function __construct(?DocBlockParser $docBlockParser = null)
    {
        $this->docBlockParser = $docBlockParser ?: new DocBlockParser();
    }

    public function build(\Reflector $reflector, Context $context)
    {
        if ($comment = $reflector->getDocComment()) {
            return $this->docBlockParser->fromComment($comment, $context);
        }

        return [];
    }
}
