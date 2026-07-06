# Downstream Impact

This document covers library/framework authors. For end-user adoption guidance,
see [v6-user-migration.md](v6-user-migration.md).

## Projects

| Project | Role | Coupling |
|---------|------|----------|
| L5-Swagger | Laravel wrapper | Shallow — configures and invokes Generator |
| NelmioApiDocBundle | Symfony bundle | Deep — uses internals extensively |
| openapi-extras | Extension library | Deep — extends classes, uses merge/context |
| openapi-router | Routing from annotations | Medium — reads parsed tree + context |

## L5-Swagger

**Constraint:** `zircote/swagger-php: ^6.0`

Thin orchestration wrapper. Instantiates `Generator`, configures processors via
pipeline walk, sets version, delegates file scanning to `SourceFinder`.

Does not access `Context`, `$_nested`, `merge()`, `Generator::$context`, or `UNDEFINED`.

| Phase | Risk | Notes |
|-------|------|-------|
| v6.x | None | — |
| v7.0 | Low | Pipeline API might change |

## NelmioApiDocBundle

**Constraint:** `zircote/swagger-php: ^4.11.1 || ^5.0 || ^6.0`

Builds spec programmatically via Describers. Translates Symfony framework metadata
(routes, validation constraints, `#[MapQueryString]`, `#[MapRequestPayload]`) into
swagger-php annotations. Deep coupling to internals:

- `OpenApiPhp/Util.php` (638 lines) — wraps all swagger-php access via `$_nested`
- Sets `Generator::$context` directly
- Calls `merge()` / `mergeProperties()` on annotations
- Uses `Context` dynamic properties as cross-phase key-value storage
- Checks `Generator::UNDEFINED` / `Generator::isDefault()` (50+ occurrences)
- Iterates `attachables` to find/detach `Model` instances
- Custom attributes extend `Attachable` / `AbstractAnnotation`
- 3 processors using `__invoke(Analysis)`
- Uses `AttributeAnnotationFactory` directly

**Positive signal:** Recently migrated from `_unmerged` to `attachables` and added
guards for `swagger-php` 6 compatibility.

| Phase | Risk | Notes |
|-------|------|-------|
| v6.x (Phase 1) | None | Processors unchanged, new DTOs convert to classic |
| v6.x (Phase 2) | Low | Nelmio code unchanged; user support burden if they mix namespaces |
| v7.0 | **High** | Major refactor of `Util.php`, `SetsContextTrait`, `Model` |

**Mitigation:** v7 timeline must coordinate with Nelmio release cycle. `Specification`
replaces `Util.php`'s `$_nested`-walking patterns with direct typed access. Cross-phase
metadata moves from `Context` dynamic properties to `Attachable` instances.

See [details/nelmio-migration.md](details/nelmio-migration.md) for full analysis:
internals usage, what the new pipeline enables, migration path per phase.

## openapi-extras

**Features:** Controller defaults inheritance, JsonResponse/JsonRequestBody shortcuts,
Middleware attachment, Customizers, EnumDescription.

Uses `$_nested`, `allowedParents()`, `merge()`, `Context`, `Analysis` API. 6 processors
using `__invoke`.

**Candidate for absorption into core:**

| openapi-extras feature | New pipeline equivalent |
|------------------------|------------------------|
| Controller defaults | Class-level attribute + assembler inheritance |
| `JsonResponse` shortcut | `#[Returns(Foo::class)]` |
| `JsonRequestBody` | Parameter type inference |
| Middleware | `Attachable` + `#[AllowedParents]` (stays as extension) |
| `MergeControllerDefaults` | Built into assembler |
| `EnumDescription` | Built into enrichment |
| Customizers | Tree processor extension point |

| Phase | Risk | Notes |
|-------|------|-------|
| v6.x | Low | `#[AllowedParents]` adoption, features ported to core |
| v7.0 | N/A | Absorbed / archived |

## openapi-router

**Constraint:** `zircote/swagger-php: ^4.11.1 || ^5.0.2 || ^6.0`
**Also depends on:** `radebatz/openapi-extras: ^4.2`

### How it uses swagger-php

Scans source files via `Generator`, then reads the parsed annotation tree to
register routes with the framework (Laravel/Slim). It's a consumer of the
generated object model — not just the JSON output.

**Integration points:**
- Uses `Generator::generate()` and `withContext()` for scanning
- Navigates `$openapi->paths` → `PathItem` → `Operation` to extract routes
- Reads `$operation->_context` for class/method info (controller resolution)
- Reads `$operation->attachables` for `Middleware` annotations (from `openapi-extras`)
- Reads `$operation->parameters` for path parameters
- Checks `Generator::UNDEFINED` / `Generator::isDefault()`
- Has a custom processor (`VendorPropertyValidation`) using `__invoke(Analysis)`
- Uses `OpenApiBuilder` from `openapi-extras` as `Generator` wrapper

**Does NOT:**
- Extend `AbstractAnnotation` or `Attachable`
- Use `$_nested`, `$_parents`, or `merge()`
- Set `Generator::$context`
- Call `jsonSerialize()`

### Impact

| Phase | Risk | Notes |
|-------|------|-------|
| v6.x | None | Processors unchanged |
| v7.0 | Medium | Depends on `UNDEFINED`, `_context`, parsed tree structure |

**Key concern:** `openapi-router` extracts routing info from the parsed `OA\OpenApi`
object tree. In v7 the tree is gone — `Specification` replaces it.

**Mitigation:** `openapi-router` currently reads `$operation->_context` for controller
class/method. `SourceLocation` (on every DTO via `$_sourceLocation`) already provides
class, method, and file directly — a typed replacement for `_context`. Available in
v6.x, so `openapi-router` can adopt it before v7.

**Note:** `openapi-router` is the inverse of Nelmio — it reads the OpenAPI spec and
produces framework routes, where Nelmio reads framework metadata and produces the
spec. Both benefit from the new typed `Specification`: one writes to it, the other
reads from it.

## Risk Summary

| Project | v6.x | v7.0 |
|---------|------|------|
| L5-Swagger | None | Low |
| NelmioApiDocBundle | None | **High** |
| openapi-extras | Low | N/A |
| openapi-router | None | Medium |
