<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi;

use OpenApi\Annotations as OA;

/**
 * Result of the analyser.
 *
 * Pretends to be an array of annotations, but also contains detected classes
 * and helper functions for the processors.
 */
class Analysis
{
    public \SplObjectStorage $annotations;

    /**
     * Class definitions.
     */
    public array $classes = [];

    /**
     * Interface definitions.
     */
    public array $interfaces = [];

    /**
     * Trait definitions.
     */
    public array $traits = [];

    /**
     * Enum definitions.
     */
    public array $enums = [];

    /**
     * The target OpenApi annotation.
     */
    public ?OA\OpenApi $openapi = null;

    public ?Context $context = null;

    public function __construct(array $annotations = [], ?Context $context = null)
    {
        $this->annotations = new \SplObjectStorage();
        $this->context = $context;

        $this->addAnnotations($annotations, $context);
    }

    public function addAnnotation(object $annotation, Context $context): void
    {
        if ($this->annotations->contains($annotation)) {
            return;
        }

        $context->ensureRoot($this->context);

        if ($annotation instanceof OA\OpenApi) {
            $this->openapi = $this->openapi ?: $annotation;
        } else {
            if ($context->is('annotations') === false) {
                $context->annotations = [];
            }

            if (in_array($annotation, $context->annotations, true) === false) {
                $context->annotations[] = $annotation;
            }
        }
        $this->annotations->attach($annotation, $context);
        $blacklist = property_exists($annotation, '_blacklist') ? $annotation::$_blacklist : [];
        foreach ($annotation as $property => $value) {
            if (in_array($property, $blacklist)) {
                if ($property === '_unmerged') {
                    foreach ($value as $item) {
                        $this->addAnnotation($item, $context);
                    }
                }
            } elseif (is_array($value)) {
                foreach ($value as $item) {
                    if ($item instanceof OA\AbstractAnnotation) {
                        $this->addAnnotation($item, $context);
                    }
                }
            } elseif ($value instanceof OA\AbstractAnnotation) {
                $this->addAnnotation($value, $context);
            }
        }
    }

    public function addAnnotations(array $annotations, Context $context): void
    {
        foreach ($annotations as $annotation) {
            $this->addAnnotation($annotation, $context);
        }
    }

    public function addClassDefinition(array $definition): void
    {
        $class = $definition['context']->fullyQualifiedName($definition['class']);
        $this->classes[$class] = $definition;
    }

    public function addInterfaceDefinition(array $definition): void
    {
        $interface = $definition['context']->fullyQualifiedName($definition['interface']);
        $this->interfaces[$interface] = $definition;
    }

    public function addTraitDefinition(array $definition): void
    {
        $trait = $definition['context']->fullyQualifiedName($definition['trait']);
        $this->traits[$trait] = $definition;
    }

    public function addEnumDefinition(array $definition): void
    {
        $enum = $definition['context']->fullyQualifiedName($definition['enum']);
        $this->enums[$enum] = $definition;
    }

    public function addAnalysis(Analysis $analysis): void
    {
        foreach ($analysis->annotations as $annotation) {
            $this->addAnnotation($annotation, $analysis->annotations[$annotation]);
        }
        $this->classes = array_merge($this->classes, $analysis->classes);
        $this->interfaces = array_merge($this->interfaces, $analysis->interfaces);
        $this->traits = array_merge($this->traits, $analysis->traits);
        $this->enums = array_merge($this->enums, $analysis->enums);
        if (!$this->openapi instanceof OA\OpenApi && $analysis->openapi instanceof OA\OpenApi) {
            $this->openapi = $analysis->openapi;
        }
    }

    /**
     * Get all subclasses of the given parent class.
     *
     * @param string $parent the parent class
     *
     * @return array map of class => definition pairs of sub-classes
     */
    public function getSubClasses(string $parent): array
    {
        $definitions = [];
        foreach ($this->classes as $class => $classDefinition) {
            if ($classDefinition['extends'] === $parent) {
                $definitions[$class] = $classDefinition;
                $definitions = array_merge($definitions, $this->getSubClasses($class));
            }
        }

        return $definitions;
    }

    /**
     * Get a list of all super classes for the given class.
     *
     * @param string $class  the class name
     * @param bool   $direct flag to find only the actual class parents
     *
     * @return array map of class => definition pairs of parent classes
     */
    public function getSuperClasses(string $class, bool $direct = false): array
    {
        $classDefinition = $this->classes[$class] ?? null;
        if (!$classDefinition || empty($classDefinition['extends'])) {
            // unknown class, or no inheritance
            return [];
        }

        $extends = $classDefinition['extends'];
        $extendsDefinition = $this->classes[$extends] ?? null;
        if (!$extendsDefinition) {
            return [];
        }

        $parentDetails = [$extends => $extendsDefinition];

        if ($direct) {
            return $parentDetails;
        }

        return array_merge($parentDetails, $this->getSuperClasses($extends));
    }

    /**
     * Get the list of interfaces used by the given class or by classes which it extends.
     *
     * @param string $class  the class name
     * @param bool   $direct flag to find only the actual class interfaces
     *
     * @return array map of class => definition pairs of interfaces
     */
    public function getInterfacesOfClass(string $class, bool $direct = false): array
    {
        $classes = $direct ? [] : array_keys($this->getSuperClasses($class));
        // add self
        $classes[] = $class;

        $definitions = [];
        foreach ($classes as $clazz) {
            if (isset($this->classes[$clazz])) {
                $definition = $this->classes[$clazz];
                if (isset($definition['implements'])) {
                    foreach ($definition['implements'] as $interface) {
                        if (array_key_exists($interface, $this->interfaces)) {
                            $definitions[$interface] = $this->interfaces[$interface];
                        }
                    }
                }
            }
        }

        if (!$direct) {
            // expand recursively for interfaces extending other interfaces
            $collect = function ($interfaces, $cb) use (&$definitions): void {
                foreach ($interfaces as $interface) {
                    if (isset($this->interfaces[$interface]['extends'])) {
                        $cb($this->interfaces[$interface]['extends'], $cb);
                        foreach ($this->interfaces[$interface]['extends'] as $fqdn) {
                            $definitions[$fqdn] = $this->interfaces[$fqdn];
                        }
                    }
                }
            };
            $collect(array_keys($definitions), $collect);
        }

        return $definitions;
    }

    /**
     * Get the list of traits used by the given class/trait or by classes which it extends.
     *
     * @param string $source the source name
     * @param bool   $direct flag to find only the actual class traits
     *
     * @return array map of class => definition pairs of traits
     */
    public function getTraitsOfClass(string $source, bool $direct = false): array
    {
        $sources = $direct ? [] : array_keys($this->getSuperClasses($source));
        // add self
        $sources[] = $source;

        $definitions = [];
        foreach ($sources as $sourze) {
            if (isset($this->classes[$sourze]) || isset($this->traits[$sourze])) {
                $definition = $this->classes[$sourze] ?? $this->traits[$sourze];
                if (isset($definition['traits'])) {
                    foreach ($definition['traits'] as $trait) {
                        if (array_key_exists($trait, $this->traits)) {
                            $definitions[$trait] = $this->traits[$trait];
                        }
                    }
                }
            }
        }

        if (!$direct) {
            // expand recursively for traits using other traits
            $collect = function ($traits, $cb) use (&$definitions): void {
                foreach ($traits as $trait) {
                    if (isset($this->traits[$trait]['traits'])) {
                        $cb($this->traits[$trait]['traits'], $cb);
                        foreach ($this->traits[$trait]['traits'] as $fqdn) {
                            $definitions[$fqdn] = $this->traits[$fqdn];
                        }
                    }
                }
            };
            $collect(array_keys($definitions), $collect);
        }

        return $definitions;
    }

    /**
     * @param class-string|array<class-string> $classes one or more class names
     * @param bool                             $strict  in non-strict mode child classes are also detected
     *
     * @return OA\AbstractAnnotation[]
     */
    public function getAnnotationsOfType($classes, bool $strict = false): array
    {
        $unique = new \SplObjectStorage();
        $annotations = [];

        foreach ((array) $classes as $class) {
            /** @var OA\AbstractAnnotation $annotation */
            foreach ($this->annotations as $annotation) {
                if ($annotation instanceof $class && (!$strict || ($annotation->isRoot($class) && !$unique->contains($annotation)))) {
                    $unique->attach($annotation);
                    $annotations[] = $annotation;
                }
            }
        }

        return $annotations;
    }

    /**
     * @param string $fqdn the source class/interface/trait
     */
    public function getSchemaForSource(string $fqdn): ?OA\Schema
    {
        return $this->getAnnotationForSource($fqdn, OA\Schema::class);
    }

    /**
     * @template T of OA\AbstractAnnotation
     *
     * @param  string          $fqdn  the source class/interface/trait
     * @param  class-string<T> $class
     * @return T|null
     */
    public function getAnnotationForSource(string $fqdn, string $class): ?OA\AbstractAnnotation
    {
        $fqdn = '\\' . ltrim($fqdn, '\\');

        foreach ([$this->classes, $this->interfaces, $this->traits, $this->enums] as $definitions) {
            if (array_key_exists($fqdn, $definitions)) {
                $definition = $definitions[$fqdn];
                if (is_iterable($definition['context']->annotations)) {
                    /** @var OA\AbstractAnnotation $annotation */
                    foreach (array_reverse($definition['context']->annotations) as $annotation) {
                        if ($annotation instanceof $class && $annotation->isRoot($class) && !$annotation->_context->is('generated')) {
                            return $annotation;
                        }
                    }
                }
            }
        }

        return null;
    }

    public function getContext(object $annotation): ?Context
    {
        if ($annotation instanceof OA\AbstractAnnotation) {
            return $annotation->_context;
        }
        if ($this->annotations->contains($annotation) === false) {
            throw new OpenApiException('Annotation not found');
        }
        $context = $this->annotations[$annotation];
        if ($context instanceof Context) {
            return $context;
        }

        throw new OpenApiException('Annotation has no context - did you use addAnnotation()/addAnnotations()');
    }

    /**
     * Build an analysis with only the annotations that are merged into the OpenAPI annotation.
     */
    public function merged(): Analysis
    {
        if (!$this->openapi instanceof OA\OpenApi) {
            throw new OpenApiException('No openapi target set. Run the MergeIntoOpenApi processor');
        }
        $unmerged = $this->openapi->_unmerged;
        $this->openapi->_unmerged = [];
        $analysis = new Analysis([$this->openapi], $this->context);
        $this->openapi->_unmerged = $unmerged;

        return $analysis;
    }

    /**
     * Analysis with only the annotations that not merged.
     */
    public function unmerged(): Analysis
    {
        return $this->split()->unmerged;
    }

    /**
     * Split the annotation into two analysis.
     * One with annotations that are merged and one with annotations that are not merged.
     *
     * @return \stdClass {merged: Analysis, unmerged: Analysis}
     */
    public function split(): \stdClass
    {
        $result = new \stdClass();
        $result->merged = $this->merged();
        $result->unmerged = new Analysis([], $this->context);
        foreach ($this->annotations as $annotation) {
            if ($result->merged->annotations->contains($annotation) === false) {
                $result->unmerged->annotations->attach($annotation, $this->annotations[$annotation]);
            }
        }

        return $result;
    }

    /**
     * Apply the processor(s).
     *
     * @param callable|array<callable> $processors One or more processors
     */
    public function process($processors = null): void
    {
        if (false === is_array($processors) && is_callable($processors)) {
            $processors = [$processors];
        }

        foreach ($processors as $processor) {
            $processor($this);
        }
    }

    public function validate(): bool
    {
        if ($this->openapi instanceof OA\OpenApi) {
            return $this->openapi->validate();
        }

        $this->context->logger->warning('No openapi target set. Run the MergeIntoOpenApi processor before validate()');

        return false;
    }
}
