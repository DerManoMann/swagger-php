<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

final class SourceLocation
{
    public function __construct(
        public readonly ?string $filename = null,
        public readonly ?int $line = null,
        public readonly ?string $namespace = null,
        public readonly ?string $class = null,
        public readonly ?string $interface = null,
        public readonly ?string $trait = null,
        public readonly ?string $enum = null,
        public readonly ?string $method = null,
        public readonly ?string $property = null,
        public readonly ?bool $static = null,
        public readonly ?array $uses = null,
        public readonly string|array|null $extends = null,
        public readonly ?array $implements = null,
    ) {}

    public static function fromReflector(\Reflector $reflector): self
    {
        $class = null;
        $method = null;
        $property = null;
        $filename = null;
        $line = null;
        $namespace = null;

        if ($reflector instanceof \ReflectionClass) {
            $class = $reflector->getName();
            $namespace = $reflector->getNamespaceName() ?: null;
            $filename = $reflector->getFileName() ?: null;
            $line = $reflector->getStartLine() ?: null;
        } elseif ($reflector instanceof \ReflectionMethod) {
            $class = $reflector->getDeclaringClass()->getName();
            $method = $reflector->getName();
            $namespace = $reflector->getDeclaringClass()->getNamespaceName() ?: null;
            $filename = $reflector->getFileName() ?: null;
            $line = $reflector->getStartLine() ?: null;
        } elseif ($reflector instanceof \ReflectionProperty) {
            $class = $reflector->getDeclaringClass()->getName();
            $property = $reflector->getName();
            $namespace = $reflector->getDeclaringClass()->getNamespaceName() ?: null;
            $filename = $reflector->getDeclaringClass()->getFileName() ?: null;
            $line = null;
        }

        return new self(
            filename: $filename,
            line: $line,
            namespace: $namespace,
            class: $class,
            method: $method,
            property: $property,
        );
    }

    public function fullyQualifiedName(?string $source): ?string
    {
        if ($source === null) {
            return null;
        }

        if (str_starts_with($source, '\\')) {
            return $source;
        }

        if ($this->uses !== null) {
            foreach ($this->uses as $alias => $fqn) {
                if (strcasecmp($source, $alias) === 0) {
                    return $fqn;
                }
                if (str_starts_with($source, $alias . '\\')) {
                    return $fqn . substr($source, strlen($alias));
                }
            }
        }

        if ($this->namespace !== null) {
            return '\\' . $this->namespace . '\\' . $source;
        }

        return '\\' . $source;
    }
}
