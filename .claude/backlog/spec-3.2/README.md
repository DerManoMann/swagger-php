# OpenAPI 3.2 field coverage

The field-by-field audit behind [PR 22](../../backlog.md). Every `src/Spec/` class and all
three `Compiler/OpenApi3xCompiler.php` classes were read against the
[3.2.0 spec](https://spec.openapis.org/oas/v3.2.0.html); this is what came out missing, what
turned out already done, and what needs a design decision before it can be done at all.

**Corrected once already, by machine.** The first pass was a read-through of the prose. Diffing
the published JSON Schemas instead — [3.1](https://spec.openapis.org/oas/3.1/schema/2022-10-07)
against [3.2](https://spec.openapis.org/oas/3.2/schema/2025-09-17), property names per
definition — found two fields the read-through had missed and one the schema claims but the
prose does not. Start there next time, and use the prose as the tiebreak: where they disagree,
the prose wins, and so does `redocly lint`, which follows it.

## Why a DTO field alone is not enough

`nullable` handling in `OpenApi30Compiler::compileSchema()` is the established pattern: the
DTO (`Schema`) carries every version's fields, and the per-version compiler
(`OpenApi30Compiler` / `OpenApi31Compiler` / `OpenApi32Compiler`) decides what to emit, warn
about, or translate. `Tag::$parent`/`$kind` already follow it —
`OpenApi32Compiler::compileTag()` emits them and validates dangling `parent` references;
`OpenApi31Compiler::compileTag()` does not emit them at all. Every gap below needs the same
pair: a field on the DTO, and a compiler method (new override, or a change to the shared
`OpenApi31Compiler` method plus an exclusion/warning in `OpenApi30Compiler`).

`OpenApi32Compiler` today is two methods long (`validate()` and `compileTag()`). Its class
docblock says "adds Tag summary/parent/kind and PathItem query" — the PathItem/query half
was never written.

## Confirmed missing

| DTO | Field(s) | Spec section | Notes |
|---|---|---|---|
| `OpenApi` | `$self` | [OpenAPI Object](https://spec.openapis.org/oas/v3.2.0.html#openapi-object) | self-assigned URI, also serves as base URI for relative refs |
| `Server` | `$name` | [Server Object](https://spec.openapis.org/oas/v3.2.0.html#server-object) | optional unique identifier for the server |
| `Example` | `$dataValue`, `$serializedValue` | [Example Object](https://spec.openapis.org/oas/v3.2.0.html#example-object) | before/after pair around encoding — `dataValue` is the in-memory value, `serializedValue` is it after applying parameter style / media-type rules. Distinct from the existing `value`/`externalValue` pair, which are the literal-or-external alternative |
| `ParameterStyle` (enum) | `Cookie = 'cookie'` | [Style Values](https://spec.openapis.org/oas/v3.2.0.html#style-values) | new in 3.2 — confirmed absent from [3.1.1's table](https://spec.openapis.org/oas/v3.1.1.html#style-values), which only has the other seven |
| `ParameterIn` (enum) | `Querystring = 'querystring'` | [Parameter Object](https://spec.openapis.org/oas/v3.2.0.html#parameter-object) | whole query string as one value, `content`-only (no `schema`/`style`/`explode`/`allowReserved`), at most one per operation, mutually exclusive with any `in: query` parameter on the same operation |
| `HttpMethod` (enum) | `Query = 'query'` | [Path Item Object](https://spec.openapis.org/oas/v3.2.0.html#path-item-object) | IETF draft `QUERY` method — safe, idempotent, takes a body |
| `PathItem`, `Operation` | `$additionalOperations` | [Path Item Object](https://spec.openapis.org/oas/v3.2.0.html#path-item-object) | `Map[string, Operation]` for verbs with no fixed field (e.g. `COPY`, `LOCK`). Spec: "MUST NOT contain any entry for the methods that can be defined by other fixed fields" |
| `MediaType` | `$itemSchema` | [Media Type Object](https://spec.openapis.org/oas/v3.2.0.html#media-type-object), §4.14.3.1 Sequential Media Types | per-item schema for streamed/sequential content (`text/event-stream`, `application/jsonl`, `application/json-seq`, `multipart/mixed`); applies to each stream item independently, unlike `schema` which applies to the whole stream as an array |
| `MediaType`, `Encoding` | `$itemEncoding`, `$prefixEncoding` | Media Type Object / Encoding Object, same streaming section | per-item and per-position encoding for multipart streams, alongside the existing `encoding` map. **Both objects carry the pair** — the first pass put it on `Encoding` only. Each is mutually exclusive with that object's `encoding` map |
| `FlowType` (enum), `Flow` | `DeviceAuthorization = 'deviceAuthorization'`, `$deviceAuthorizationUrl` | [OAuth Flows Object](https://spec.openapis.org/oas/v3.2.0.html#oauth-flows-object), [OAuth Flow Object](https://spec.openapis.org/oas/v3.2.0.html#oauth-flow-object) | new flow for limited-input devices (smart TVs, kiosks); `deviceAuthorizationUrl` sits alongside `tokenUrl` the way `authorizationUrl` does for the existing flows |
| `Security\Scheme` | `$oauth2MetadataUrl` | [Security Scheme Object](https://spec.openapis.org/oas/v3.2.0.html#security-scheme-object) | RFC 8414 OAuth 2.0 Authorization Server Metadata discovery URL |
| `Security\Scheme` | `$deprecated` | [Security Scheme Object](https://spec.openapis.org/oas/v3.2.0.html#security-scheme-object) | applies to every scheme type, not just oauth2. Missed by the prose read-through |
| `Response` | `$summary` | [Response Object](https://spec.openapis.org/oas/v3.2.0.html#response-object) | short summary alongside the existing `description`. Missed by the prose read-through |
| `Components` | `$mediaTypes` | [Components Object](https://spec.openapis.org/oas/v3.2.0.html#components-object) | reusable, `$ref`-able entries. Blocked — see "Phase 4" below. `$pathItems` is the same problem but a **3.1** bucket, not a 3.2 one, so it has been missing for longer |

## In the schema, not in the spec — do not add

`MediaType::$description`. The 3.2 JSON Schema lists `description` on the Media Type Object;
the [fixed-field table](https://spec.openapis.org/oas/v3.2.0.html#fixed-fields-15) in §4.14.1
does not. It was implemented on `feat/spec-3.2-fields`, `redocly lint` rejected it with
"Property `description` is not expected here", and the prose backs redocly — so it came back
out. Redocly 2.37 accepted every other field on that branch, which is what makes this a
schema bug rather than a tooling gap.

Also noted, not part of this audit's scope (they're **3.1** gaps, not 3.2 ones) but touching
the same mechanism:

- `Components::$callbacks` — 3.1 added `callbacks` as a components bucket; `Components` never
  gained the field.
- `OpenApi::$jsonSchemaDialect` — also 3.1, also never added.

Both would auto-wire through `Specification::addComponentsChildren()`, which matches
`Components` properties to `Specification` properties by name — the same reason adding
`Components::$pathItems` (once Q5 is settled) needs no change to that method, only to
`Components` and `Specification` themselves.

## Confirmed already correct — checked, no action needed

`Discriminator` (still just `propertyName`/`mapping`), `Info`, `License`, standard OAuth flow
fields (`authorizationUrl`/`tokenUrl`/`refreshUrl`/`scopes`), `Response`, `RequestBody`,
`Operation`'s own fixed fields, and the JSON Schema dialect/keyword set on `Schema` (3.2
stays on 2020-12, same as 3.1). `Tag::$parent`/`$kind` are done, per above.

## Phase breakdown

**Phase 1a — plain fields. Done on `feat/spec-3.2-fields`, unmerged.** `OpenApi::$self`,
`Server::$name`, `Response::$summary`, `Example::$dataValue`/`$serializedValue`,
`Security\Scheme::$deprecated`/`$oauth2MetadataUrl` — each a property on the DTO plus an
`OpenApi32Compiler` override that appends it to the parent's result, so 3.0 and 3.1 drop it
silently the way they already drop `Tag::$parent`. `deprecated` and `oauth2MetadataUrl` also
had to reach the `Security\Scheme\*` subtypes, which are the API the docs actually push.
Example's mutual-exclusion rules (§4.19.1) went in as a `validate()` warning, matching the
existing License `url`/`identifier` one. `$dataValue` defaults to `Undefined::UNDEFINED`
rather than `null` so `dataValue: null` is expressible — which makes it inconsistent with its
neighbour `$value`, and is a case for PR 8's null-vs-`Undefined` question to settle.

**Phase 1b — the rest of what looked plain, and is not.** `MediaType::$itemSchema` and the
`itemEncoding`/`prefixEncoding` pair on both `MediaType` and `Encoding` are object-valued, so
they need a slot to be authorable as nested attributes at all — a `Schema` nested in a
`MediaType` merges into `schema` today, and there is nothing to target `itemSchema` with
short of a typed subtype. They also carry the "MUST NOT be present if `encoding` is present"
rule. `FlowType::DeviceAuthorization` + `Flow::$deviceAuthorizationUrl` + a
`Flow\DeviceAuthorization` typed subtype (mirrors `Flow\Implicit` etc.) and
`ParameterStyle::Cookie` add *values* to existing enums, which changes what is valid per
version — validation work, not a compiler emit, and 3.0/3.1 should probably warn rather than
silently drop. Follow the omit-and-warn pattern in `OpenApi30Compiler::validateSchemas()`.

**Phase 2 — `querystring` parameter location.** `ParameterIn::Querystring`, a
`Parameter\Querystring` typed subtype, and a validation rule (at most one per operation,
mutually exclusive with `in: query` parameters) in the style of
`OpenApi31Compiler::validateOperations()`.

**Phase 3 — `query` method / `additionalOperations`.** `HttpMethod::Query`, an
`Operation\Query` typed subtype, `$additionalOperations` on `PathItem`/`Operation`, and
compiler changes to `compilePaths()`/`compileOperation()` routing non-standard methods into
`additionalOperations` instead of the fixed-method slot, plus a validation rule rejecting
standard method names inside that map.

**Phase 4 — `Components.pathItems` / `Components.mediaTypes`.** Blocked on Q5 (below).

## Phase 4: open design question (Q5)

`Parameter`/`Header`/`Link` are each both "usable inline" and "a reusable, `$ref`-able
component" via the same mechanism: a component-key constructor field (`parameter:`,
`header:`, `link:`) and an `isRoot()` that's true only when that key is set and `ref` is not
(`docs/dev/pipeline.md` — "Conditionally root"). `PathItem` and `MediaType` have no such key,
and adding `components.pathItems`/`components.mediaTypes` needs one.

**`PathItem`** is unconditionally root today (`docs/dev/pipeline.md` lists it under "Always
root"), and its `$path` is always a resolved URL — set by an augmenter or `HybridBridge`, per
the docblock on that property, never authored directly. There's nothing that would tell
"the shared metadata for `/pets/{id}`" (today's only use) apart from "a reusable path-item
template with no path of its own, meant to be `$ref`'d". Two shapes sketched, neither
implemented:

1. Give `PathItem` a `pathItem:` key field, same shape as `Parameter::$parameter`, and make
   `isRoot()` conditional on it (or on `path === null`, since a real path-bound `PathItem`
   always has one by the time it reaches the compiler).
2. Leave `PathItem` alone and add a distinct `PathItem\Reusable` (naming TBD) that overrides
   `contained()`/`merge()` to land in `Components::$pathItems` instead.

Option 1 matches the existing `Parameter`/`Header`/`Link` convention most closely. Option 2
avoids changing `isRoot()` semantics on a class three call sites already treat as
unconditionally root (worth grepping for before deciding).

**`MediaType`** is worse: `$mediaType` is simultaneously *the value* (`'application/json'`,
used as the map key wherever `MediaType` is nested — see `compileMediaTypes()`'s
`$mediaType->mediaType ?? 'application/json'`) and, if reused as a component lookup key, would
collide with that — two components can't both be named `application/json`. A reusable
component entry needs an identity independent of the media-type string, e.g. a new `$name`
(or `$mediaTypeComponent`) field distinct from `$mediaType`, with `$ref` resolution
substituting the schema at the *usage* site rather than the type string.

Neither sketch has been run past `merge()`/`contained()` implementation or tests; treat both
as starting points for a discussion, not a chosen design.
