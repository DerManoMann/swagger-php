# Extract Merge from Constructor — Detail

## Current constructor (simplified)

```php
public function __construct(array $properties)
{
    // 1. Capture context (read static global or use passed value)
    $this->_context = $properties['_context'] ?? Generator::$context ?? ...;
    $this->_context->annotations[] = $this;

    // 2. Assign properties + nest any AbstractAnnotation values
    $nestedContext = new Context(['nested' => $this], $this->_context);
    foreach ($properties as $property => $value) { ... }

    // 3. Merge 'value' key through $_nested rules
    if (is_array($properties['value'])) {
        $this->merge($annotations);
    }
}
```

All three steps happen atomically during `newInstance()`. No way to intervene.

## Proposed split

```php
public function __construct(array $properties)
{
    $this->captureContext($properties);
    $this->assignProperties($properties);
    $this->performMerge($properties);
}
```

The point is isolating `performMerge()` so the factory can skip it for the new
DTO path. The classic path still calls all three — identical behavior.

## Scope

- **v6.x (infrastructure):** Split internals so merge is a separate callable step.
  Classic path unchanged. This lets the factory skip merge/context for new-path DTOs.
- **v6.x (new DTOs):** New DTOs don't have this at all. PHP attribute constructors
  use named params — assignment is just constructor promotion. The assembler handles
  nesting externally, not via `$_nested` merge rules.
- **v7:** `AbstractAnnotation` gone, so the split is moot.

## Why not simplify further now?

The split only exists for the classic annotation path. New DTOs never need it —
PHP's named constructor params handle assignment, and the assembler replaces merge.
So this is a minimal bridge: just enough structure to let both paths coexist
without the new factory triggering context registration or merge side effects.

## Risk

Low. Pure internal refactor. The constructor's external contract is unchanged
(`new Schema(['type' => 'string', ...])` works identically). Only subclasses
that override `__construct` need review — but those are rare and should call parent.
