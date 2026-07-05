# SpecCompilerInterface — Detail

## Interface

```php
interface SpecCompilerInterface
{
    public function getVersion(): string;
    public function compile(OA\OpenApi $openApi): array;
    public function validate(OA\OpenApi $openApi): CompilerDiagnostics;
}
```

This is the initial form — accepts the existing `OA\OpenApi` annotation tree (same
input as `jsonSerialize()` today). After Phase 2, the signature changes to
`compile(Specification $spec)` — see [v6-compilers.md](../v6-compilers.md) for
the final form.

## Generator integration

```php
class Generator
{
    protected ?SpecCompilerInterface $compiler = null;

    public function setCompiler(?SpecCompilerInterface $compiler): static { ... }

    public function generate(...): ?OA\OpenApi
    {
        // ... existing analysis + processing ...

        // Output: use compiler if set, otherwise jsonSerialize (BC)
        if ($this->compiler) {
            return $this->compiler->compile($openApi);
        }
        return $openApi; // caller uses jsonSerialize() as before
    }
}
```

## What moves into compilers

All version-branching currently in `AbstractAnnotation::jsonSerialize()`:

### Nullable handling
- 3.0: `nullable: true` keyword
- 3.1+: `type: ["string", "null"]` array

### `exclusiveMinimum`/`Maximum`
- 3.0: boolean flag + `minimum`/`maximum` value
- 3.1+: numeric value (the exclusive bound itself)

### `$ref` siblings
- 3.0: `$ref` replaces the object
- 3.1+: `summary`/`description` can coexist

### Feature gating
- 3.0: strip webhooks, `unevaluatedProperties`, `const`, `examples` array, etc.
- 3.2: allow `Tag` `summary`/`parent`/`kind`, `PathItem` `query`

### Annotation-specific
- `License`: `identifier` (3.1+ only)
- `Schema`: `examples` array, `const` (3.1+ only)
- `OpenApi`: webhooks (3.1+ only)

## Validation

```php
class CompilerDiagnostics
{
    /** @var list<Diagnostic> */
    public array $errors = [];

    /** @var list<Diagnostic> */
    public array $warnings = [];
}

class Diagnostic
{
    public function __construct(
        public readonly string $message,
        public readonly ?SourceLocation $location = null,
        public readonly ?string $path = null,
    ) {}
}
```

Errors: feature that cannot be represented in target version (e.g. if/then/else → 3.0).
Warnings: lossy conversion (e.g. examples array → single example in 3.0).

## Testing

The compiler must produce byte-identical output to `jsonSerialize()` for all existing
test fixtures. This is the correctness proof before switching over.

Run both paths in CI, diff the output. Only when diff is empty is the compiler considered
ready for that version.
