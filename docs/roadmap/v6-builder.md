# OpenApiBuilder Design

Single public entry point for both classic and new `Spec\*` pipelines. `BuildResult` is the stable contract; the pipeline behind it is an implementation detail.

## Rationale

Generator is ~60% processor/analyser plumbing that won't exist in the new path. Rather than maintaining two separate entry points, `OpenApiBuilder` wraps both pipelines behind a unified `BuildResult` contract:

- **Classic mode**: wraps Generator internally, adapts `OA\OpenApi` output into BuildResult
- **Spec mode**: runs new pipeline (Assembler → Augmenters → Compiler), produces BuildResult natively

Users adopt the builder first (one-line change), then migrate annotations at their own pace. The mode can even be auto-detected from which attribute namespace is found in sources.

## Version Timeline

- **v6**: Builder ships supporting both modes. Classic is default (or auto-detected from `OA\*` attributes).
- **v7**: Spec mode is default. Classic still available via explicit `setMode('classic')`.
- **v8**: Classic mode removed. Builder is spec-only.

## API Surface

```php
use OpenApi\Spec\OpenApiBuilder;
use OpenApi\Spec\BuildResult;

$result = (new OpenApiBuilder())
    ->addSource('src/Controllers')
    ->addSource('src/Models')
    ->setVersion('3.1.0')
    ->addAugmenter(new CustomAugmenter())
    ->removeAugmenter(CleanupAugmenter::class)
    ->addExtension(new VendorExtension())
    ->setLogger($psrLogger)
    ->build();

// Result container — access any pipeline step
$result->files();              // string[] scanned source files
$result->specification();      // Specification (post-augmentation)
$result->compiler();           // SpecCompilerInterface used
$result->diagnostics();        // CompilerDiagnostics (validation)
$result->toArray();            // array (compiled output)
$result->toJson();             // string
$result->toYaml();             // string
```

## Dual-Mode Support

```php
// Explicit mode selection
$result = (new OpenApiBuilder())
    ->addSource('src/')
    ->setMode('spec')   // or 'classic'
    ->build();

// Auto-detection (default): scans sources for attribute namespace
// Found OA\* attributes only  → classic mode
// Found Spec\* attributes only → spec mode
// Mixed                        → error with guidance
```

### Classic mode internals

```php
private function buildClassic(): BuildResult
{
    $generator = new Generator($this->logger);
    $generator->setVersion($this->version);
    // apply config from builder...

    $openApi = $generator->generate($this->sources, validate: true);

    // Adapt into BuildResult
    $output = json_decode($openApi->toJson(), true);
    $diagnostics = $this->collectClassicDiagnostics($generator);

    return new BuildResult(
        files: $this->resolvedFiles,
        specification: null,  // not available in classic mode
        compiler: null,
        diagnostics: $diagnostics,
        output: $output,
    );
}
```

### Spec mode internals

```php
private function buildSpec(): BuildResult
{
    // 1. Scan sources → ReflectionClass[]
    $files = $this->scanSources();
    $classes = $this->discoverClasses($files);

    // 2. Assemble → Specification
    $assembler = new Assembler();
    foreach ($classes as $class) {
        $assembler->collect($class);
    }
    $specification = $assembler->getSpecification();

    // 3. Augment
    foreach ($this->getAugmenters() as $augmenter) {
        $augmenter->augment($specification);
    }

    // 4. Select compiler (auto from version or explicit)
    $compiler = $this->resolveCompiler($specification);

    // 5. Validate
    $diagnostics = $compiler->validate($specification);

    // 6. Compile → array
    $output = $compiler->compile($specification);

    return new BuildResult($files, $specification, $compiler, $diagnostics, $output);
}
```

## BuildResult Container

```php
namespace OpenApi\Spec;

class BuildResult
{
    public function __construct(
        private array $files,
        private ?Specification $specification,
        private ?SpecCompilerInterface $compiler,
        private CompilerDiagnostics $diagnostics,
        private array $output,
    ) {}

    public function files(): array { return $this->files; }
    public function specification(): ?Specification { return $this->specification; }
    public function compiler(): ?SpecCompilerInterface { return $this->compiler; }
    public function diagnostics(): CompilerDiagnostics { return $this->diagnostics; }
    public function isValid(): bool { return !$this->diagnostics->hasErrors(); }

    public function toArray(): array { return $this->output; }
    public function toJson(int $flags = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES): string { ... }
    public function toYaml(): string { ... }
}
```

Note: `specification()` and `compiler()` return null in classic mode — those concepts don't exist in the classic pipeline. Code that needs them should use spec mode.

## Builder Internals

```php
namespace OpenApi\Spec;

class OpenApiBuilder
{
    private array $sources = [];
    private array $excludes = [];
    private ?string $version = null;
    private ?string $mode = null;  // null = auto-detect
    private array $augmenters = [];
    private array $extensions = [];
    private ?LoggerInterface $logger = null;
    private ?SpecCompilerInterface $compiler = null;

    // Classic-mode config (forwarded to Generator)
    private array $classicConfig = [];
    private ?Pipeline $classicProcessorPipeline = null;

    public function addSource(string|iterable $source): self { ... }
    public function exclude(string $pattern): self { ... }
    public function setVersion(string $version): self { ... }
    public function setMode(string $mode): self { ... }  // 'spec', 'classic', or null (auto)
    public function setCompiler(SpecCompilerInterface $compiler): self { ... }
    public function setLogger(LoggerInterface $logger): self { ... }

    // Augmenter management (spec mode)
    public function addAugmenter(SpecAugmenter $augmenter, ?string $before = null): self { ... }
    public function removeAugmenter(string $class): self { ... }
    public function withAugmenters(callable $configurator): self { ... }

    // Classic config (classic mode — forwarded to Generator)
    public function setConfig(array $config): self { ... }
    public function withProcessorPipeline(callable $configurator): self { ... }

    // Extensions (spec mode)
    public function addExtension(CompilerExtension $ext): self { ... }

    public function build(): BuildResult
    {
        $mode = $this->mode ?? $this->detectMode();

        return match ($mode) {
            'classic' => $this->buildClassic(),
            'spec' => $this->buildSpec(),
        };
    }

    private function detectMode(): string
    {
        // Scan source attributes to detect OA\* vs Spec\*
        // Could also check for presence of specific marker files/config
    }
}
```

## Default Augmenter Pipeline

```php
private function getDefaultAugmenters(): array
{
    return [
        new DocblockAugmenter(),        // summary/description from PHPDoc
        new InheritanceAugmenter(),     // interface/trait → allOf
        new EnumAugmenter(),            // PHP enums → schema enum values
        new TypeAugmenter(),            // type resolution on schemas/properties/parameters
        new OperationIdAugmenter(),     // auto-generate operationId
        new CleanupAugmenter(),         // remove unused components
    ];
}
```

## Hooking Into Steps

Three patterns:

### 1. Access after build (inspect results)

```php
$result = $builder->build();

if (!$result->isValid()) {
    foreach ($result->diagnostics()->errors as $error) {
        echo $error->message . "\n";
    }
}

$spec = $result->specification();
foreach ($spec->schemas as $schema) { /* custom checks */ }
```

### 2. Custom augmenters (modify between steps)

```php
class SecurityAugmenter implements SpecAugmenter
{
    public function augment(Specification $specification): void
    {
        // Add security schemes, validate auth coverage, etc.
    }
}

$result = (new OpenApiBuilder())
    ->addSource('src/')
    ->addAugmenter(new SecurityAugmenter(), before: CleanupAugmenter::class)
    ->build();
```

### 3. Step-by-step control (advanced)

```php
$builder = (new OpenApiBuilder())->addSource('src/')->setMode('spec');

// Run steps manually for full control
$files = $builder->scan();
$specification = $builder->assemble($files);
$builder->augment($specification);
$diagnostics = $builder->validate($specification);

// Inspect/modify specification before compile
$specification->schemas = array_filter(...);

$output = $builder->compile($specification);
```

## Migration Path

### Step 1: Adopt builder (trivial, no annotation changes)

```php
// Before (Generator directly)
$openApi = (new Generator())->generate(['src/']);
echo $openApi->toJson();

// After (Builder, auto-detects classic mode from OA\* attributes)
$result = (new OpenApiBuilder())->addSource('src/')->build();
echo $result->toJson();
```

### Step 2: Migrate annotations (at your own pace)

```php
// Replace OA\* with Spec\* attributes in source files
// Builder auto-detects spec mode once all sources use Spec\*
```

### Step 3: Use spec-mode features

```php
// Now you have access to Specification, augmenters, compiler, etc.
$result = (new OpenApiBuilder())
    ->addSource('src/')
    ->addAugmenter(new SecurityAugmenter())
    ->build();

$spec = $result->specification();  // non-null in spec mode
```

## Comparison with Existing Patterns

| Concern | Generator (classic) | OpenApiBuilder |
|---------|--------------------|--------------------|
| Entry point | `$generator->generate($sources)` | `$builder->build()` |
| Returns | `OA\OpenApi` (or null) | `BuildResult` container |
| Pipeline config | `setConfig(['key.sub' => val])` | Typed methods + `addAugmenter()` |
| Step access | None (opaque) | `BuildResult` exposes all steps |
| Validation | Side-effect (logs warnings) | `$result->diagnostics()` (structured) |
| Output format | `$openApi->toJson()` / `$openApi->toYaml()` | `$result->toJson()` / `$result->toYaml()` |
| Dual mode | No | Yes — classic and spec behind same API |
| Extensions | Attachable + custom serialization | `CompilerExtension` on compiler |

## Migration from openapi-extras OpenApiBuilder

The existing `Radebatz\OpenApi\Extras\OpenApiBuilder` wraps Generator with fluent config. The new `OpenApi\Spec\OpenApiBuilder` subsumes both:

| Extras method | New equivalent |
|---------------|---------------|
| `operationIdHashing(bool)` | `addAugmenter(new OperationIdAugmenter(hash: true))` |
| `clearUnusedComponents(bool)` | Enabled by default; `removeAugmenter(CleanupAugmenter::class)` to disable |
| `extensionEnumNames(?string)` | `addAugmenter(new EnumAugmenter(enumNames: 'x-enum-names'))` |
| `addCustomizer(class, fn)` | `addAugmenter(new CallbackAugmenter(Schema::class, fn))` |
| `pathsToMatch(patterns)` | `addAugmenter(new PathFilterAugmenter(paths: [...]))` |
| `build()` → Generator | `build()` → BuildResult (final, no further generate() needed) |

In classic mode, existing extras processor config still works via `setConfig()` / `withProcessorPipeline()`.
