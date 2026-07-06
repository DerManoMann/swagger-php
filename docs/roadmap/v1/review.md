# Review: Roadmap vs Implementation

Assessment of `docs/roadmap` against `src/Spec` and `tests/Spec` on the `roadmap` branch.

## Overall Assessment

The implementation is well-aligned with the roadmap and proves the core architecture end-to-end. The three-layer design (DTOs → Specification → Compiler) works as described, with both bridge paths tested against real output.

## What's Implemented and Matches the Plan

| Roadmap component | Implementation | Verdict |
|---|---|---|
| DTOs (`OpenApi\Spec\*`) | 23 classes in `src/Spec/` | Complete for core types |
| `OpenApiAttributeInterface` | Full contract with `allowedParents`, reflector, sourceLocation, extensions, attachables | Matches |
| `AbstractAttribute` | Base class with protected fields, fluent setters | Matches |
| `SourceLocation` | Value object with `fromReflector()` | Matches |
| `Specification` | Flat typed collections + `add()` routing | Matches — finders deferred as noted |
| `Assembler` | Reflection-based, `allowedParents()` nesting, auto-collects methods/properties | Matches. No `AttributeStack` — deviation acknowledged |
| `SpecCompilerInterface` | `getVersion()`, `supports()`, `compile()`, `validate()` | Exact match |
| `OpenApi31Compiler` | Full declarative compilation with `filter()`, `UNDEFINED`-aware, extensions | Comprehensive |
| `OpenApi30Compiler` | Downgrade: type→nullable, exclusiveMin boolean, const→enum, strips 3.1+ | Solid |
| `OpenApi32Compiler` | Extends 3.1, version override, future-ready for Tag extensions | Minimal but correct |
| `SpecAnnotationFactory` (Phase 1 bridge) | New→classic conversion with full context propagation | Thorough |
| `SpecificationConverter` (Phase 2 bridge) | Classic→DTO with full API coverage | Thorough |
| `OpenApiBuilder` | Dual-mode, auto-detection, fluent config, augmenter pipeline, step-by-step | Matches design doc closely |
| `BuildResult` | Exposes files/spec/compiler/diagnostics/toArray/toJson/toYaml | Exact match |
| `SpecAugmenter` | Abstract base with `augment()` + `__invoke()` | Matches |

## Tested Paths (all passing)

47 tests, 324,458 assertions.

1. **Assembler → Compiler** — PetStore fixture, verified against expected YAML
2. **SpecAnnotationFactory → Classic pipeline** — PetStore through Generator
3. **ApiBridgeTest** — Full API example (controllers, enums, traits, callbacks, security) compared against reference spec
4. **SpecificationConverter → Compiler** — Classic API example through converter + compiler, compared against same reference
5. **OpenApiBuilder** — Both modes, output formats, augmenter pipeline operations, step-by-step control

## Deviations (Acknowledged in Status Doc)

Intentional simplifications, all documented in `v6-status.md`:

- **No `AttributeStack`** — Assembler works directly with flat lists; nesting via `allowedParents()` + reflection
- **No Specification finders** — Compiler accesses collections directly; finders not yet needed
- **`$security` on `OpenApi` DTO** — More consistent than a separate flat array
- **`UNDEFINED` on Schema** — Necessary for null-valid JSON fields (`example`, `default`, `const`)

## Issues and Observations

### 1. `OpenApiBuilder::detectClassic()` is shallow

Only scans sources that are strings and exist as files. Iterable sources and directories are skipped, so auto-detection falls back to classic mode if only directories are passed. Uses `str_contains($content, 'OpenApi\\Spec\\')` which could misfire on comments/strings that reference the namespace without using it.

**Location:** `src/Spec/OpenApiBuilder.php:320–338`

### 2. Classic mode test doesn't validate output correctness

`testBuildClassicMode` runs `PetStore.php` (which uses `Spec\*` attributes) through classic mode. Generator can't find `@OA\Info` and warns. The test validates the code path doesn't crash, but doesn't assert correct output for this fixture.

**Location:** `tests/Spec/OpenApiBuilderTest.php:39–50`

### 3. Dead code in `Assembler::nestChild()`

The property-level docblock parsing at line 173–177 has an empty if-block with a comment ("won't work here"). Harmless — the constructor-parameter fallback below handles it — but the dead path should be removed.

**Location:** `src/Spec/Assembler.php:173–177`

### 4. `OpenApi30Compiler` has stateful `$diagnostics` pattern

Uses a `$diagnostics` property set during `compile()` and read via `getDiagnostics()`. This means `compile()` must be called before `getDiagnostics()` works. The 3.1/3.2 compilers are stateless — this is inconsistent.

**Location:** `src/Spec/OpenApi30Compiler.php:22`, `src/Spec/OpenApi30Compiler.php:123`

### 5. `setMode()` vs `setClassic()` API divergence

Roadmap design doc specifies `setMode('spec')` / `setMode('classic')`. Implementation uses `setClassic(?bool)`. The implementation's approach is simpler but diverges from the documented API surface.

**Location:** `src/Spec/OpenApiBuilder.php:62–67` vs `docs/roadmap/v6-builder.md:50–59`

## What's Not Yet Touched

Confirmed absent from code, listed in status doc as remaining work:

- `SourceScanner` (dedicated class replacing analyser for new pipeline)
- Augmenter implementations (type resolution, docblock, enum, inheritance, cleanup, operationId)
- Specification finders (`schema()`, `operations()`, `find()`, `filter()`, `resolveRef()`)
- Schema registry / `$ref` resolution
- `CompilerExtension` wiring
- All v6 prep work (UNDEFINED extraction, TypeResolver decoupling, version branching removal)

## Test Quality

Strong. `AssemblerTest` does granular field-level verification (61+ assertions). Bridge tests compare full API output against reference YAML with property ordering tolerance. Compiler tests cover version-specific transformations, validation rules, and edge cases comprehensively.

## Summary

The prototype validates the roadmap's three-layer architecture. The core pipeline (DTOs → Assembler → Specification → Compiler → output) works end-to-end, both bridges work, and the Builder provides a clean unified entry point. The main gap is augmenters — `getDefaultAugmenters()` returns `[]`. That's the expected next phase after prep items unblock type resolution and docblock parsing for the new pipeline.