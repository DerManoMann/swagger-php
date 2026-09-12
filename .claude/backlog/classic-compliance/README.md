# Classic spec-compliance sweep

## Why classic gets fixed at all

The closed-area rule in AGENTS.md carried a blanket "no new features" until #2190
(2026-09-12) carved out spec-compliance defects — an OpenAPI construct classic claims to
model but cannot express. What changed the answer is the packagist major-version data
(share of monthly downloads):

| Month | v3 | v4 | v5 | v6 |
|---|---|---|---|---|
| 2025-08 | 10.2% | 53.0% | 29.6% | — |
| 2026-02 | 8.4% | 43.5% | 38.0% | 5.1% |
| 2026-08 | 5.8% | 32.7% | 22.5% | 35.8% |

Majors decay slowly — v4 still carries a third of all downloads, v3 ~6% years after
replacement — and v6 is the last major where classic is the primary API. Whatever shape
classic is in when v7 forks off is the shape the long tail lives with, effectively
permanently. Fixes shipped in 6.x do reach users: within-major upgrades are the ones
people actually take. The carve-out is deliberately narrow — new *capabilities* stay
closed, and internal quality (PR 29's classic annotation debt) stays parked for v8.

Field-by-field diff of every classic annotation against the OpenAPI 3.1 object tables,
run 2026-09-12 for PR 40. Method: reflect the public properties of each
`OpenApi\Annotations` class, diff against the published 3.1 JSON Schema
(`spec.openapis.org/oas/3.1/schema/2022-10-07`), and settle each hit against the prose —
the same schema-first, prose-as-tiebreak method PR 22 used against 3.2, and it earned its
keep the same way here (see "cleared", below: the schema lied twice).

Baseline is 3.1 because classic declares `SUPPORTED_VERSIONS` up to 3.2.0 but 3.2 fields
are parked with PR 22/PR 25 for both pipelines; what classic *claims to model today* and
cannot express is the 3.0/3.1 surface.

## Gaps — classic

| # | Object | Missing | Since | Spec pipeline has it? |
|---|---|---|---|---|
| 1 | `Header` | `style`, `explode`, `example`, `examples`, `content` | 3.0 | yes — all five |
| 2 | `Examples::$_parents` / `Header::$_nested` | the wiring for #1's `examples` | 3.0 | n/a |
| 3 | `SecurityScheme` | `mutualTLS` in the `type` enum (`$_types` lists four) | 3.1 | yes — `SchemeType::MutualTLS`, `MutualTls` subclass, 3.0 compiler warns+omits |
| 4 | `Info` | `summary` | 3.1 | yes |
| 5 | `Schema` (+ `Items`, `Property`, `JsonContent`, `XmlContent`, `AdditionalProperties`) | `if`, `then`, `else`, `prefixItems`, `unevaluatedItems`, `maxContains`, `minContains`, `dependentRequired`, `dependentSchemas`, `contentSchema` | 3.1 | nine of ten — `contentSchema` missing there too |
| 6 | `OpenApi` | `jsonSchemaDialect` | 3.1 | **no** |
| 7 | `Components` | `pathItems` | 3.1 | **no** — blocked on Q5 in both pipelines |
| 8 | `Parameter` | a plain `MediaType` in `content` is **silently dropped** — the docblock documents the field, but `$_nested` lacks the `MediaType => ['content', 'mediaType']` wiring `Response` has, so it lands in `_unmerged` and vanishes without a diagnostic (`JsonContent`/`XmlContent` work, via their processors) | 3.0 | yes |

Partial adoption is the story of #5: classic already has `const`, `contains`,
`contentMediaType`, `contentEncoding`, `patternProperties`, `propertyNames` and
`unevaluatedProperties`, so someone added the 3.1 keywords once and stopped partway —
`contains` without `minContains`/`maxContains`, `unevaluatedProperties` without
`unevaluatedItems`.

## Findings beyond classic

- **`contentSchema` and `jsonSchemaDialect` are missing from the spec pipeline too.**
  PR 22's audit diffed 3.1 against 3.2, so a field absent from *both* pipelines at 3.1
  was invisible to it. New spec-side work, small, and each fix branch should take both
  pipelines in one pass.
- **`Components.pathItems` is a 3.1 field, not 3.2.** PR 22 lists it beside
  `Components.mediaTypes` as Q5-blocked, which is right about the blocker but files it
  under 3.2 — the gap exists in every supported version today. Q5's answer gets a bit
  more urgent.

## Cleared — looked like gaps, are not

- **`Link.body`**: in the 3.1 JSON Schema, not in the prose (the prose field is `server`,
  which classic has). Same schema-over-prose trap as `MediaType::$description` in PR 22,
  and PR 25 already noted 3.2 deleting it from the schema.
- **`Parameter`/`MediaType` `style`/`explode`/`example`/`examples`**: present in classic;
  the schema hides them under `dependentSchemas`/`allOf` composition, so a top-level
  properties read reports them as missing. Read the composed schema before believing it.
- **Classic already carries some 3.2**: `Tag::$summary/$parent/$kind`,
  `PathItem::$query`/`Operation` query method.
- **Classic-only leftovers, not gaps but noted**: `Schema` still declares draft-4/Swagger-2
  keywords the 3.x spec never had — `additionalItems`, `dependencies`, `collectionFormat`
  — plus `nullable` (real, 3.0-only). What classic emits for the three dead ones at 3.x is
  unverified; worth a look while in the file.

## Found while fixing

- **Gap 8 came from verifying rather than reading**: before wiring Header's `content`,
  running the `Parameter` equivalent showed the documented field silently dropping. The
  sweep's table rows were checked by reflection; the *behavioural* claims still need a
  build each.
- **Spec `Header` has no `isRoot()` at all.** Q5's entry claims Parameter/Header/Link
  share the component-key-plus-conditional-`isRoot()` pattern; Header actually inherits
  `isRoot(): false` and becomes a component *positionally* — stacked with
  `#[Components]`, or alone on a class with the key inferred (#2172). A standalone
  spec `Header` with an explicit key and no `Components` sibling throws
  `Non-root attribute … remains after resolution`. One more shape for Q5's answer to
  unify; noted there.
- **`ScratchTest` regeneration self-compares.** With `file_put_contents` uncommented,
  every mode overwrites the shared expectation and then asserts against its own output —
  the last writer wins, so classic/spec divergence hides. Only the expected-logs
  assertion still bites (it caught an empty spec document here). Always follow a
  regeneration run with a second run, regeneration off. Noted in PR 12's mechanics.

**Loose end, not investigated:** `SecurityScheme` attributes stacked on the same class
as an operation are emitted by classic but silently dropped by hybrid — all of them,
not just mutualTLS. The `Auth` fixture's own-class arrangement works in every mode, so
this is about the stacked-with-an-operation shape reaching the bridge as `nested`.
Worth a look with the other bridge collection conditions.

## Fix batches

Each is one branch, smallest first; both pipelines wherever both lack the field:

1. **Header** — the five fields, `Examples::$_parents` + `Header::$_nested` wiring,
   fixture in both modes (gaps 1-2) — **in review, #2191**: the five
   properties with a schema-XOR-content `validate()`, the attribute constructor,
   `JsonContent`/`XmlContent` accepted under `Header` in both merge processors, and the
   `HybridBridge` carrying the new fields across. `Scratch/HeaderObject{,-spec}` pins all
   five in all three modes against **one shared expectation per version** — the fixture
   payoff, first data point.
2. **Parameter content wiring** — gap 8 — **in review, #2192**:
   the `$_nested` line, `Parameter` added to `MediaType::$_parents`, and the merge
   processors now set `mediaType` for parameter content too. The special case was a
   2020 workaround (`113c00a5`, "Improve OAS3 compatibility"): without the nested
   config, serialization leaked `mediaType:` *inside* the media type object, and the
   guard suppressed the leak by never setting the field. With the config present the
   serializer keys the map off the field and strips it — and the guard flips to
   actively wrong, since the key-field validation now requires the field set. Every
   existing expectation is byte-identical, and the regenerated fixture shows no leak. The pre-existing
   `Scratch/ParameterContent` fixture (which covered only the working `JsonContent`
   shortcut) gains the plain-`MediaType` parameter that used to vanish.
3. **mutualTLS** — **in review, #2193**: the `$_types` enum entry, a
   `validate()` warning at 3.0.x with the spec compiler's message text (one shared
   `$expectedLogs` key covers all three modes — `str_contains` matching), and a
   `Components::jsonSerialize()` override that drops mutualTLS schemes from 3.0
   documents, since a scheme can't remove itself from the parent map. The `Auth`
   fixture now covers it in both syntaxes; 3.0 omits, 3.1/3.2 carry it.
4. **Info.summary** — one field, drop at 3.0 with a warning (gap 4)
5. **Schema keywords** — ten fields classic-side, `contentSchema` spec-side too;
   3.0 handling per keyword follows the existing warn/drop table in PR 25's entry (gap 5)
6. **jsonSchemaDialect** — both pipelines, 3.1+ only (gap 6)
7. **pathItems** — parked behind Q5, with PR 22 Phase 4 (gap 7)

Expected fixture payoff: divergent `-spec.yaml` expectations collapse where the divergence
was an expression gap, checked per batch rather than promised up front.
