<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

use OpenApi\Analysers\AnnotationFactoryInterface;
use OpenApi\Annotations as OA;
use OpenApi\Context;
use OpenApi\GeneratorAwareTrait;

/**
 * Bridge factory: reads new Spec\* attributes via the Assembler and converts
 * them to classic annotations for the existing analysis pipeline (Phase 1).
 */
class SpecAnnotationFactory implements AnnotationFactoryInterface
{
    use GeneratorAwareTrait;

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
        if ($reflector instanceof \ReflectionProperty && $reflector->isPromoted()) {
            return [];
        }

        $specAttributes = $this->assembler->instantiate($reflector);

        if (!$specAttributes) {
            return [];
        }

        $annotations = [];
        foreach ($specAttributes as $instance) {
            $converted = $this->convert($instance, $context);
            if ($converted !== null) {
                $annotations[] = $converted;
            }
        }

        return $annotations;
    }

    protected function convert(OpenApiAttributeInterface $attribute, Context $context): ?OA\AbstractAnnotation
    {
        // TODO: implement conversion from Spec\* instances to classic OA\* annotations
        return null;
    }
}
