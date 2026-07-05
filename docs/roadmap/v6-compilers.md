# Compiler Layer

## Responsibility

The compiler transforms the document tree into version-correct JSON/YAML.
All version-specific knowledge lives here — not in attributes, not in processors.

## Interface

```php
interface SpecCompilerInterface
{
    public function getVersion(): string;
    public function compile(Specification $tree): array;
    public function validate(Specification $tree): CompilerDiagnostics;
}
```

One compiler per major spec version: `OpenApi30Compiler`, `OpenApi31Compiler`, `OpenApi32Compiler`.

## Internal Model Semantics

The document tree uses 3.1+ (JSON Schema 2020-12) as canonical representation:

| Concept | Internal form | 3.0 compiler output |
|---------|--------------|---------------------|
| Nullable string | `type: ["string", "null"]` | `type: "string", nullable: true` |
| Exclusive min 5 | `exclusiveMinimum: 5` | `minimum: 5, exclusiveMinimum: true` |
| Constant value | `const: "foo"` | `enum: ["foo"]` |
| Examples | `examples: [...]` | `example: examples[0]` (warn) |
| Nullable ref | `oneOf: [{$ref}, {type: "null"}]` | `\$ref + nullable: true` |

Rationale: 3.1+ is a superset. Storing in the most expressive form means no
information loss. Downgrade compilers handle lossy conversion with warnings.

## Version Differences Handled by Compilers

### 3.0.x specific
- `type` must be string (not array)
- `nullable` is a keyword
- `exclusiveMinimum`/`Maximum` is boolean
- No `$ref` siblings (strip `summary`/`description`)
- No webhooks, no `const`, no `examples` array
- No `unevaluatedProperties`, `if`/`then`/`else`, `prefixItems`
- No `Tag` `summary`/`parent`/`kind`, no `PathItem` `query`

### 3.1.x additions
- `type` can be array
- `nullable` replaced by type array with `"null"`
- `exclusiveMinimum/Maximum` is number
- `$ref` can have siblings
- Webhooks, `const`, `examples` array supported
- JSON Schema 2020-12 keywords available

### 3.2.x additions
- Tag `summary`/`parent`/`kind`
- PathItem `query` operation

## CompilerExtension

How custom `Attachable`s produce output:

```php
interface CompilerExtension
{
    public function handles(): array;  // list of Attachable class names
    public function compile(object $attachable, CompilerContext $ctx): array;
}
```

Registered on the compiler. Unhandled `Attachable`s are silently omitted.
Extensions receive a `CompilerContext` with version, parent output, location,
and schema registry.

See [details](details/compiler-extension.md).

## Diagnostics

```php
class CompilerDiagnostics
{
    public array $errors = [];    // Cannot compile (incompatible feature)
    public array $warnings = [];  // Lossy conversion (feature omitted)
}
```

Users get clear feedback when targeting 3.0 with 3.1+ features, instead of
silent data loss during serialization.

## Output format

`compile()` returns a PHP array (the spec as a nested associative structure).
The `Generator` is responsible for the final serialization step:

```php
$array = $compiler->compile($spec);
$json = json_encode($array, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
$yaml = Yaml::dump($array, ...);
```

This keeps compilers format-agnostic (testable via array comparison) and lets the
Generator control encoding options (flags, indentation, etc.).

## Transition Strategy

1. v6.x (early): Introduce `SpecCompilerInterface` alongside `jsonSerialize()`
2. v6.x (Phase 2): Compilers become primary output path; `jsonSerialize()` delegates
3. v7: `jsonSerialize()` removed from annotations
