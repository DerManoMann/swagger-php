# Active

Entries in progress or in review — one per open branch or pull request. If this file
grows past a handful of entries, that is itself the signal. Flow rules:
[README.md](README.md).

### PR 30 — hybrid runs two classic processors for a mapping the bridge already knows — **in review, #2184**

Branch: `refactor/hybrid-drop-classic-processors`.

`Builder::doHybridAssemble()` builds a `Generator` whose entire processor pipeline is
`MergeJsonContent` and `MergeXmlContent`. Those two rewrite a `JsonContent` or `XmlContent`
nested in a `Response`, `RequestBody` or `Parameter` into
`content['application/json'|'application/xml'] = MediaType(schema: ...)`, lift `example`,
`examples` and `encoding` off the schema, and drop the original annotation. `HybridBridge`
then reads `content` — its own docblock stated the dependency: "Expects only
MergeJsonContent/MergeXmlContent to have run".

The media type is fixed per annotation class, so nothing is being discovered. The bridge
could synthesize the `Spec\MediaType` directly in `convertResponse()`, `convertRequestBody()`
and `convertParameter()`, and hybrid's `Generator` call would reduce to scan and analyse with
no processor pipeline at all.

Why it is worth doing rather than tidy: **v7 makes hybrid the default mode** (see
[ROADMAP](../../ROADMAP.md)), so this overhead moves onto the path most projects take, and
PR 16 already measured hybrid at 1.46x slower than classic. This is one identified piece of
that, with fixed rules and no intermediate state.

Two things a change has to keep:

- `MergeJsonContent` warns when the content sits somewhere it cannot nest ("Unexpected
  ... must be nested"). Moving the mapping without the diagnostic makes that case silent —
  the failure mode PR 15 and #2162 both turned out to be.
- It clears `example`, `examples` and `encoding` on the schema after lifting them to the
  media type. Skipping that emits them in both places.

**#2184** moves the unwrapping into `HybridBridge::resolveContent()`, reading
`JsonContent`/`XmlContent` from `_unmerged`, and empties the processor pipeline in
`doHybridAssemble()`.

### hybrid comparison in the remaining test suites — **in review, #2183**

The tail of PR 35: hybrid ran in `ExamplesTest`, `DocSnippetsTest` and `CommandlineTest`
but was never compared against an expectation that would catch a deviation. **#2183**
makes `getSpecFilename()` and `DocSnippetsTest` prefer spec expectations for hybrid —
the precedence `ScratchTest` already uses — and lets `ExamplesTest` run spec sources in
hybrid mode (+58 tests). `CommandlineTest` was deliberately left out: it is a wrapper
around the same generator paths the other suites already compare.

### PR 42 — spec emits a duplicate `$ref` when the parent is named by class-string — **in review, #2185**

Branch: `fix/allof-ref-dedup`.

`Refs::dedupAllOfRefs()` compares `$ref` values as strings, and runs in the same pass that
later resolves class-strings — `__invoke()` calls it before `resolveFQCNRefs()`. So a schema
whose `allOf` carries a user-written `ref: Parent::class` alongside the
`#/components/schemas/parent` that `Inheritance\Schemas::addAllOfRef()` adds has two entries
that are not equal yet, both survive dedup, and both then resolve to the same pointer:

```yaml
update-user:
  type: object
  allOf:
    - $ref: '#/components/schemas/abstract-user'
    - $ref: '#/components/schemas/abstract-user'
```

Spec and hybrid both do it; classic emits one. Writing the same ref as a pointer rather than a
class-string dedups correctly, which is why `Scratch/DuplicateRef` — whose whole subject is
this — does not catch it: its `allOf` names `#/components/schemas/abstract-user` directly.

Two ways to fix, and the second is probably right: dedup after resolution instead of before,
or dedup on the resolved form by mapping class-strings through the same `$refMap`. Either way
the fixture wants a second class extending the same parent with `ref: Parent::class`.

**Found answering Q3** — the docs claimed the opposite (spec dedups, classic "may emit the
same `$ref` twice"), which is what prompted running it.

**#2185** takes the second option: `Refs::__invoke()` runs `dedupAllOfRefs()` after
`resolveRefRefs()` and `resolveFQCNRefs()`, so entries are compared once they carry their
final value. `Scratch/DuplicateRef` gains the class-string form, and the duplicate `$ref`
entry leaves the classic/spec differences in `guide/spec-attributes.md` — classic emits one.

### PR 43 — spec reverses trait property order — **in review, #2186**

Branch: `fix/trait-property-order`.

`Inheritance\Schemas::mergeMembers()` ends with

```php
$schema->properties = [...$merged, ...($schema->properties ?? [])];
```

so each source prepends. Over one trait that gives the intended result — inherited members
before the class's own — but `expandTraits()` calls it once per trait, so two traits land in
the reverse of their `use` order. Three traits reverse fully: declaring `T1`, `T2`, `T3`
emits `p3`, `p2`, `p1`, then the class's own properties. Classic and hybrid emit `p1`, `p2`,
`p3`.

Accumulate across the sources and prepend once, or track an insertion offset. Map key order
carries no meaning in OpenAPI, so nothing is *wrong* with the document — but reverse `use`
order is not a choice anyone made, and it is one of the five known classic/spec divergences
for no reason.

Costs a fixture update: `Scratch/MergeTraitsExtended-spec` pins the current order in its
expectation files, though `assertSpecEquals` compares maps order-independently, so nothing
fails when it changes and nothing would have caught the reversal either. A test that does
pin order is part of the work.

**Found answering Q3**, where the docs stated the rule backwards.

**#2186** accumulates merged members across parents, traits and interfaces and prepends the
block once, in visit order; `mergeMembers()` takes the accumulator by reference and loses its
unused `$schema` parameter. The order is pinned in `InheritanceBcTest` against a new
`ClassUsingOrderedTraits` fixture in both pipelines — `AssertsSchemaStructure` sorts before
comparing, so nothing else could have caught it. Consolidating that fixture into one file per
pipeline does not work: classic resolves fixture classes through PSR-4 autoloading, so each
type needs its own file.
