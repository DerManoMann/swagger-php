<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

use Symfony\Component\Yaml\Yaml;

class BuildResult
{
    public function __construct(
        private array $files,
        private ?Specification $specification,
        private ?SpecCompilerInterface $compiler,
        private CompilerDiagnostics $diagnostics,
        private array $output,
    ) {
    }

    /** @return list<string> */
    public function files(): array
    {
        return $this->files;
    }

    public function specification(): ?Specification
    {
        return $this->specification;
    }

    public function compiler(): ?SpecCompilerInterface
    {
        return $this->compiler;
    }

    public function diagnostics(): CompilerDiagnostics
    {
        return $this->diagnostics;
    }

    public function isValid(): bool
    {
        return !$this->diagnostics->hasErrors();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->output;
    }

    public function toJson(int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE): string
    {
        return json_encode($this->output, $flags);
    }

    public function toYaml(int $inline = 10, int $indent = 4): string
    {
        return Yaml::dump($this->output, $inline, $indent, Yaml::DUMP_OBJECT_AS_MAP | Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
    }
}
