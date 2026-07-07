<?php declare(strict_types=1);

/**
 * @license Apache 2.0
 */

namespace OpenApi\Spec;

use OpenApi\Generator;
use OpenApi\Pipeline;
use OpenApi\SourceFinder;
use Psr\Log\LoggerInterface;

class OpenApiBuilder
{
    /** @var list<string|iterable> */
    private array $sources = [];

    /** @var list<string> */
    private array $excludes = [];

    private ?string $version = null;

    private ?bool $classic = null;

    private ?Pipeline $augmenterPipeline = null;

    private ?SpecCompilerInterface $compiler = null;

    private ?LoggerInterface $logger = null;

    /** @var array<string, mixed> Classic mode config forwarded to Generator */
    private array $classicConfig = [];

    private ?Pipeline $classicProcessorPipeline = null;

    public function addSource(string|iterable $source): static
    {
        $this->sources[] = $source;

        return $this;
    }

    public function exclude(string $pattern): static
    {
        $this->excludes[] = $pattern;

        return $this;
    }

    public function setVersion(string $version): static
    {
        $this->version = $version;

        return $this;
    }

    /**
     * @param bool|null $classic true = classic pipeline, false = spec pipeline, null = auto-detect
     */
    public function setClassic(?bool $classic): static
    {
        $this->classic = $classic;

        return $this;
    }

    public function setCompiler(SpecCompilerInterface $compiler): static
    {
        $this->compiler = $compiler;

        return $this;
    }

    public function setLogger(LoggerInterface $logger): static
    {
        $this->logger = $logger;

        return $this;
    }

    public function getAugmenterPipeline(): Pipeline
    {
        if ($this->augmenterPipeline === null) {
            $this->augmenterPipeline = new Pipeline($this->getDefaultAugmenters());
        }

        return $this->augmenterPipeline;
    }

    public function setAugmenterPipeline(Pipeline $pipeline): static
    {
        $this->augmenterPipeline = $pipeline;

        return $this;
    }

    /**
     * Configure the augmenter pipeline via callable.
     *
     * @param callable(Pipeline): void $configurator
     */
    public function withAugmenterPipeline(callable $configurator): static
    {
        $configurator($this->getAugmenterPipeline());

        return $this;
    }


    /**
     * Classic mode: set Generator config.
     *
     * @param array<string, mixed> $config
     */
    public function setConfig(array $config): static
    {
        $this->classicConfig = $config + $this->classicConfig;

        return $this;
    }

    /**
     * Classic mode: configure the processor pipeline.
     *
     * @param callable(Pipeline): void $configurator
     */
    public function withProcessorPipeline(callable $configurator): static
    {
        $generator = $this->buildGenerator();
        $configurator($generator->getProcessorPipeline());
        $this->classicProcessorPipeline = $generator->getProcessorPipeline();

        return $this;
    }

    public function build(): BuildResult
    {
        $classic = $this->classic ?? $this->detectClassic();

        return $classic ? $this->buildClassic() : $this->buildSpec();
    }

    // -- Spec mode: individual steps exposed for advanced usage --

    /**
     * @return list<string>
     */
    public function scan(): array
    {
        $files = [];

        foreach ($this->sources as $source) {
            if (is_iterable($source) && !is_string($source)) {
                foreach ($source as $item) {
                    $path = $item instanceof \SplFileInfo ? $item->getPathname() : (string) $item;
                    if (is_file($path)) {
                        $files[] = $path;
                    }
                }
            } elseif (is_dir($source)) {
                foreach (new SourceFinder($source, $this->excludes) as $file) {
                    $files[] = $file->getPathname();
                }
            } elseif (is_file($source)) {
                $files[] = $source;
            }
        }

        return $files;
    }

    /**
     * @param list<string> $files
     * @return list<\ReflectionClass>
     */
    public function discoverClasses(array $files): array
    {
        $classes = [];

        foreach ($files as $file) {
            $realPath = realpath($file);
            $declared = get_declared_classes();
            include_once $file;
            $new = array_diff(get_declared_classes(), $declared);

            if ($new) {
                foreach ($new as $className) {
                    $rc = new \ReflectionClass($className);
                    if ($rc->getFileName() === $realPath) {
                        $classes[] = $rc;
                    }
                }
            } else {
                foreach (get_declared_classes() as $className) {
                    $rc = new \ReflectionClass($className);
                    if ($rc->getFileName() === $realPath) {
                        $classes[] = $rc;
                    }
                }
            }
        }

        return $classes;
    }

    /**
     * @param list<\ReflectionClass> $classes
     */
    public function assemble(array $classes): Specification
    {
        $assembler = new Assembler();

        foreach ($classes as $class) {
            $assembler->collect($class);
        }

        return $assembler->getSpecification();
    }

    public function augment(Specification $specification): void
    {
        $this->getAugmenterPipeline()->process($specification);
    }

    public function validate(Specification $specification): CompilerDiagnostics
    {
        $compiler = $this->resolveCompiler($specification);

        return $compiler->validate($specification);
    }

    /**
     * @return array<string, mixed>
     */
    public function compile(Specification $specification): array
    {
        $compiler = $this->resolveCompiler($specification);

        return $compiler->compile($specification);
    }

    // -- Internal --

    private function buildSpec(): BuildResult
    {
        $files = $this->scan();
        $classes = $this->discoverClasses($files);
        $specification = $this->assemble($classes);

        $this->augment($specification);

        $compiler = $this->resolveCompiler($specification);
        $diagnostics = $compiler->validate($specification);
        $output = $compiler->compile($specification);

        return new BuildResult($files, $specification, $compiler, $diagnostics, $output);
    }

    private function buildClassic(): BuildResult
    {
        $generator = $this->buildGenerator();

        $openApi = $generator->generate($this->sources, validate: true);

        $output = [];
        $diagnostics = new CompilerDiagnostics();

        if ($openApi !== null) {
            $output = json_decode($openApi->toJson(), true) ?? [];
        } else {
            $diagnostics->errors[] = new Diagnostic('Generator returned null — no valid OpenAPI spec produced');
        }

        return new BuildResult([], null, null, $diagnostics, $output);
    }

    private function buildGenerator(): Generator
    {
        $generator = new Generator($this->logger);

        if ($this->version !== null) {
            $generator->setVersion($this->version);
        }

        if ($this->classicConfig) {
            $generator->setConfig($this->classicConfig);
        }

        if ($this->classicProcessorPipeline !== null) {
            $generator->setProcessorPipeline($this->classicProcessorPipeline);
        }

        return $generator;
    }

    private function resolveCompiler(Specification $specification): SpecCompilerInterface
    {
        if ($this->compiler !== null) {
            return $this->compiler;
        }

        $version = $this->version ?? $specification->openapi->version ?? '3.1.0';

        $compilers = [
            new OpenApi30Compiler(),
            new OpenApi31Compiler(),
            new OpenApi32Compiler(),
        ];

        foreach ($compilers as $compiler) {
            if ($compiler->supports($version)) {
                return $compiler;
            }
        }

        return new OpenApi31Compiler();
    }

    private function detectClassic(): bool
    {
        foreach ($this->sources as $source) {
            if (!is_string($source) || !is_file($source)) {
                continue;
            }

            $content = file_get_contents($source);
            if ($content === false) {
                continue;
            }

            if (str_contains($content, 'OpenApi\\Spec\\')) {
                return false;
            }
        }

        return true;
    }

    /** @return list<callable> */
    private function getDefaultAugmenters(): array
    {
        return [
            new Augmenters\OperationIdAugmenter(),
        ];
    }
}
