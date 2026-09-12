# Active

Entries in progress or in review — one per open branch or pull request. If this file
grows past a handful of entries, that is itself the signal. Flow rules:
[README.md](README.md).

### PR 40 — classic spec-compliance gaps — **sweep done, batch 1 in review, #2191**

The completing sweep ran 2026-09-12: every classic annotation's fields diffed against the
3.1 object tables, schema-first with the prose as tiebreak. Full findings, the cleared
false positives, and the six fix batches: [classic-compliance/README.md](classic-compliance/README.md).

Seven gaps. The three the entry started with (`Header`'s five fields plus its `Examples`
wiring, `mutualTLS`, and `Components.pathItems` by way of Q5) survived, and the sweep
added four: `Info.summary`, ten JSON Schema keywords on `Schema` (classic adopted the
3.1 keywords partway — `contains` without `minContains`, `unevaluatedProperties` without
`unevaluatedItems`), `OpenApi.jsonSchemaDialect`, and `Schema.contentSchema` — the last
two missing from the **spec pipeline as well**, invisible to PR 22's audit because that
diffed 3.1 against 3.2 and these are 3.1 gaps in both pipelines.

Fix batches land one branch each, smallest first, both pipelines wherever both lack the
field: Header → Parameter content wiring → mutualTLS → Info.summary → Schema keywords.
`jsonSchemaDialect` is parked (2026-09-12): optional, defined default, and only
meaningful with non-default schema dialects, which neither pipeline can express — the
sweep notes carry the reasoning and the revival trigger. `pathItems` stays parked behind
Q5 with PR 22 Phase 4.

**Batch 5 (Schema keywords) is in review — #2195, branch `fix/classic-schema-keywords`.**
The ten keywords everywhere classic repeats its schema surface (trait, template, six
attribute constructors), `contentSchema` spec-side, mirrored 3.0 warn/drop handling, and
three forced fixes — single-annotation nesting in the classic constructor, the bridge
tolerating array-form schema values, and an items-requirement exemption for 3.1 tuples.
Details in [classic-compliance/README.md](classic-compliance/README.md).

**Batch 4 (Info.summary) is in review — #2194, branch `fix/classic-info-summary`.**
Property, attribute parameter, bridge, and a silent 3.0 drop matching the
`License::$identifier` precedent — the warn-or-not question belongs to PR 25. New
`InfoObject` fixture pins the full Info Object in all three modes.

**Batch 3 (mutualTLS) is in review — #2193, branch `fix/classic-mutualtls`.** Enum entry,
3.0 warn-and-omit matching the spec compiler's message so one log expectation covers all
three modes, and the `Auth` fixture finally covers the scheme type. (Batch 3's suspected
hybrid loose end was investigated and retracted — it was default unused-component
cleanup, not a bridge bug; the sweep notes have the detail.)

**Batch 2 (Parameter content) is in review — #2192, branch
`fix/classic-parameter-content`.** One `$_nested` line plus the processor special-case removal it forces, with the
existing `ParameterContent` fixture extended by the parameter shape that used to vanish.

**Batch 1 (Header) is in review — #2191, branch `fix/classic-header-fields`.** The five
fields with a schema-XOR-content `validate()`, the merge processors accepting `Header`,
the `HybridBridge` carrying the fields across, and `Scratch/HeaderObject{,-spec}` pinning
all three modes against one shared expectation per version. Verifying it turned up gap 8
(classic `Parameter` silently drops a plain `MediaType` in `content`) and a Q5 correction
(spec `Header` has no `isRoot()`); both recorded.
