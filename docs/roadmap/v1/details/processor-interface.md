# ProcessorInterface — Detail

## Current state (v6.x)

Processors are callables with `__invoke(Analysis)`:

```php
class AugmentSchemas
{
    public function __invoke(Analysis $analysis): void
    {
        // logic
    }
}
```

This stays unchanged through v6.x — including after the Phase 2 flip. Classic
processors continue working against `Analysis` regardless of which internal
pipeline is active.

## v6.x: No processor changes

New DTOs convert to classic annotations (Phase 1) or classic annotations convert
to new DTOs (Phase 2) — either way, the processor contract is unchanged. Processors
don't know or care about the internal pipeline direction.

## v7: New interface, new surface

v7 is the clean break — `Specification` becomes the only working surface.
Processors migrate from `__invoke(Analysis)` to `process(Specification)`:

```php
interface ProcessorInterface
{
    public function process(Specification $spec): void;
}
```

### Core processors

```php
class AugmentSchemas implements ProcessorInterface
{
    public function process(Specification $spec): void
    {
        foreach ($spec->find(Schema::class) as $schema) {
            // enrich from $_reflector, resolve types, etc.
        }
    }
}
```

### Downstream migration

Processors rewrite from `Analysis` (annotation tree navigation) to `Specification`
(flat collections with finders):

```php
// v6.x (classic):
public function __invoke(Analysis $analysis): void
{
    foreach ($analysis->annotations as $annotation) {
        if ($annotation instanceof OA\Schema) { ... }
    }
}

// v7 (new):
public function process(Specification $spec): void
{
    foreach ($spec->find(Schema::class) as $schema) { ... }
}
```

The jump is significant but clean — `Specification` finders are more ergonomic
than `SplObjectStorage` iteration + `instanceof` checks.

## Pipeline

```php
// v7 pipeline
foreach ($processors as $processor) {
    $processor->process($spec);
}
```

Plain callables no longer supported — implement the interface. This is a clean
break at a major version boundary.

## Optional: `ConfigurableProcessorInterface` (v7)

```php
interface ConfigurableProcessorInterface extends ProcessorInterface
{
    public function priority(): int;
    public function supports(string $version): bool;
}
```

Allows processors to declare ordering preferences and version applicability.
Pipeline sorts by priority and filters by version before executing.

## Timeline summary

| Version | Processor contract | Surface |
|---------|-------------------|---------|
| v6.x | `__invoke(Analysis)` | `$analysis->openapi` (annotation tree) |
| v7.0 | `ProcessorInterface::process(Specification)` | Flat collections + finders |

One migration, at the major version boundary. No intermediate steps.

## Known downstream processors

| Project | Processors | v7 Migration |
|---------|-----------|--------------|
| `NelmioApiDocBundle` | `NullablePropertyProcessor`, `MapQueryStringProcessor`, `MapRequestPayloadProcessor` | Rewrite to `Specification` API |
| `openapi-extras` | `MergeControllerDefaults`, `AugmentJsonResponse`, `AugmentJsonRequestBody`, `WrapJsonResponseContent`, `Customizers`, `EnumDescription` | Rewrite (or absorbed into core) |
| L5-Swagger | User-provided via config (callable) | Implement `ProcessorInterface` |
