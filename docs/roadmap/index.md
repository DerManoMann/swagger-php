# Roadmap

## Why

Annotations currently serve three roles simultaneously: declaration (what the user writes), spec model (internal representation), and serialization (version-aware JSON/YAML output).
This forces version differences, nesting rules, merge logic, and context management all into one class hierarchy.

The result is fragile patterns (static globals, dynamic properties, convention-based nesting) that make extension difficult and version support brittle.

The planned refactor separates these into distinct layers — thin DTOs for declaration, a flat Specification container for the model,
and dedicated Compilers for version-specific output. Key is that the new attributes (DTOs) are independent of the actual spec version.

This makes each concern independently testable, lets new OpenAPI versions ship as a new compiler without touching the model,
and opens clean extension points (augmenters, compiler extensions) without requiring deep framework knowledge.

## Terminology

| Classic (current) | Spec (new) | Role |
|-------------------|------------|------|
| `Generator` | `OpenApiBuilder` | Entry point |
| `OpenApi\Annotations\*` / `OpenApi\Attributes\*` | `OpenApi\Spec\*` DTOs | User-facing declaration |
| `Analysis` | `Specification` | Root container for the spec model |
| Processors (`__invoke(Analysis)`) | Augmenters (`augment(Specification)`) | Enrich/transform the model |
| `jsonSerialize()` + `isVersion()` branching | Compilers (one per version) | Version-specific output |
| `Context` | `SourceLocation` + `Reflector` | Source metadata |
| `$_nested` / `$_parents` / `merge()` | `allowedParents()` + Assembler | Structural nesting |
| `Generator::UNDEFINED` | `null` (except null-valid fields) | "Not set" sentinel |

## Current: [v2](v2/index.md)

Refined plan based on validated prototype. High-level phases and remaining work.

## Archive: [v1](v1/index.md)

Original detailed planning documents. Includes design explorations and rejected approaches.
