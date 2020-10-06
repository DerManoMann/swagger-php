<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Parser;

use OpenApi\Annotations\AbstractAnnotation;
use OpenApi\Annotations\Operation;
use OpenApi\Annotations\Response;
use OpenApi\Context;
use const OpenApi\UNDEFINED;

class AttributeAnnotationFactory implements AnnotationFactoryInterface
{
    public function build(\Reflector $reflector, Context $context)
    {
        if ($context->is('annotations') === false) {
            $context->annotations = [];
        }

        $annotations = [];
        foreach ($reflector->getAttributes() as $attribute) {
            // @todo check for supported types
            $instance = $attribute->newInstance();
            if ($instance instanceof AbstractAnnotation) {
                $instance->_context = $context;
            }
            $annotations[] = $instance;
        }

        // @todo deal with nesting...
        foreach ($annotations as $annotation) {
            if ($annotation instanceof Operation) {
                foreach ($annotations as $ii => $inner) {
                    if ($inner instanceof Response) {
                        if ($annotation->responses === UNDEFINED) {
                            $annotation->responses = [];
                        }
                        $annotation->responses[] = $inner;
                        $annotations[$ii] = null;
                    }
                }
            }
        }

        $context->annotations = $annotations;

        return array_filter($annotations, fn ($a) => $a !== null);
    }
}
