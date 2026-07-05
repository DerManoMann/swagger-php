# CompilerExtension — Detail

## Purpose

Custom `Attachable`s need a way to produce spec output. Since `Attachable`s are DTOs
(no serialization logic), the compiler needs to be told what to emit.

## Interface

```php
interface CompilerExtension
{
    /**
     * Which Attachable class(es) this extension handles.
     */
    public function handles(): array;

    /**
     * Compile the Attachable into spec output.
     *
     * Returns key-value pairs merged into the parent's output object.
     */
    public function compile(object $attachable, CompilerContext $ctx): array;
}
```

## CompilerContext

```php
class CompilerContext
{
    public function __construct(
        public readonly string $version,
        public readonly array $parentOutput,
        public readonly ?SourceLocation $location,
        public readonly SchemaRegistry $registry,
    ) {}
}
```

- `version` — target OpenAPI version (for version-aware extensions)
- `parentOutput` — the parent node's compiled output so far
- `location` — where the `Attachable` was declared
- `registry` — resolve class names to `$ref` paths

## Registration

```php
$compiler = new OpenApi31Compiler();
$compiler->addExtension(new RateLimitExtension());

// Or via Generator:
$generator->addCompilerExtension(new RateLimitExtension());
```

Explicit registration — no magic discovery.

## Example

```php
#[Attribute(TARGET_METHOD)]
#[AllowedParents(Operation::class)]
class RateLimit extends Attachable
{
    public function __construct(
        public int $requests = 100,
        public ?string $window = '1m',
    ) { parent::__construct([]); }
}

class RateLimitExtension implements CompilerExtension
{
    public function handles(): array
    {
        return [RateLimit::class];
    }

    public function compile(object $attachable, CompilerContext $ctx): array
    {
        return [
            'x-rate-limit' => [
                'requests' => $attachable->requests,
                'window' => $attachable->window,
            ],
        ];
    }
}
```

Output:
```yaml
paths:
  /users:
    get:
      x-rate-limit:
        requests: 100
        window: 1m
```

## Unhandled `Attachable`s

If no `CompilerExtension` is registered for an `Attachable` class, the compiler
silently omits it (same as today's `$_blacklist` behavior). Optionally emit a
diagnostic warning.

## Version-aware extensions

Extensions can emit differently per version:

```php
public function compile(object $attachable, CompilerContext $ctx): array
{
    if (str_starts_with($ctx->version, '3.0')) {
        return ['x-rate-limit' => $attachable->requests];
    }
    return ['x-rate-limit' => ['requests' => $attachable->requests, 'window' => $attachable->window]];
}
```
