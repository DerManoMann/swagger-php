# Active

Entries in progress or in review — one per open branch or pull request. If this file
grows past a handful of entries, that is itself the signal. Flow rules:
[README.md](README.md).

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

### PR 18 — `AttributeGenerator` is the last generator rendering by hand — **in review, #2187**

Branch: `refactor/attribute-generator-sections`.

#2141 moved augmenters, spec attributes and processors onto the shared `Sections`
abstraction. `AttributeGenerator` still renders inline through `Renderer::classDescription()`
and `Renderer::references()`, which exist only for it.

Porting it would finish the job and let those two methods go, the way `processorOptions()`
and `indentedBr()` went. Against that: it generates the classic attributes and annotations
pages, and classic is removed in v8, so this may be work with a short life. Worth doing only
if something else needs to touch that generator anyway.

**The port had a decision inside it.** `AttributeGenerator` used five `Renderer` methods,
not the two this entry named, and nothing else called any of them. Three were byte-identical
to their section classes; two were not, so porting meant picking which rendering survives:

- `Renderer::parameters()` emitted a `<dl>`; `ParametersSection` emitted a markdown list
- `Renderer::references()` had no `↗`; `ReferencesSection` did

**The markdown list is broken, which settled it.** A blank line closes a list item, so every
paragraph after the first renders outside the entry — unindented, detached from its
parameter, taking the required flag with it. Classic descriptions are routinely
multi-paragraph, so a naive port would have wrecked `attributes.md` and `annotations.md`.
Types fail from the other side: `htmlentities()` output escaped again by the markdown code
span, which is why `spec-attributes.md` rendered `list&lt;Schema&gt;` on screen. That one was
live, not hypothetical.

So **#2187** pivots everything to the definition list rather than the reverse: one parameters
renderer, `Renderer` reduced to the page frame, and the spec pages restyled with the doubled
entities gone. Side-by-side of the two renderings, generated through the docs site's own
markdown renderer: https://claude.ai/code/artifact/213dce41-079b-4865-ac35-ef8cbf7092a8

Two things the entry's cost estimate missed. It is **not** short-lived work — `Sections` and
`ParametersSection` are shared with the spec pages, so only `AttributeGenerator` itself dies
with v8. And the section-marker counts make the 2000-line docs diff reviewable: identical on
every page, 522 list items in and 522 definition-list entries out.

### PR 23 — audit where classes ended up after the `Utils/` migration — **in review, #2189**

Branch: `refactor/utils-audit`.

#2039 moved `TokenScanner` and others into `OpenApi\Utils`, and it has been the default
landing spot for anything that isn't obviously `Spec\`/`Attributes\` vocabulary ever since.
Some of what's there is genuinely general-purpose (`TypedList`, `TokenScanner`,
`SourceLocation`), and some looks like it belongs to a subsystem that already has its own
directory:

- `AttributeFactory` — assembly-time attribute translation, driven by
  `AttributeTranslatorInterface` implementations; `src/Assembler/` already exists for exactly
  this (`AbstractAttributeTranslator`, `DefaultAttributeTranslator`,
  `OptionalPropertyAttributeTranslator`)
- `PipeInterface` — the augmenter extension-point interface `Builder::withAugmenters()`
  accepts. `src/Contracts/` holds every other public extension-point interface
  (`AttributeInterface`, `AttributeTranslatorInterface`, `CompilerInterface`,
  `ResolverInterface`); `PipeInterface` reads like the odd one out
- `SpecificationWalker` — walks a `Specification`; `src/Specification/` already exists for
  its collaborators (`ComponentIndex`)
- `CollectingLogger` — `src/Loggers/` already exists (`DefaultLogger`) and holds none of the
  other logger implementations

Not a decision, just a survey worth doing before the next class has to land in `Utils/` by
default because nobody checked whether it has a better home — which is how this came up:
picking a home for `Config` (PR 1) required reasoning it through from scratch instead of
following a clear convention.

Each move is small in isolation (rename, fix imports, likely a deprecated shim at the old
location the way `src/Pipeline.php` and `src/SourceFinder.php` already alias into `Utils/` —
several of these classes are used by downstream code, per the extension-points table in
PR 20) but touches call sites across `src/` and `tools/`, so worth batching into one pass
rather than doing it ad hoc mid unrelated PRs.

**The survey moved two of the four candidates, and added one this entry missed.**

- `TypeMapper` — not on the list above, and the clearest case of the lot: used only by
  `TypeResolverInterface` and `src/Type/*`, with `src/Type/` already there. Moved, with a
  deprecated subclass, since a custom type resolver reaches it through
  `AbstractTypeResolver`.
- `CollectingLogger` and `SpecificationWalker` — moved as proposed. `SpecificationWalker`
  loses the stutter on the way, as `Specification\Walker`. Neither is documented surface, so
  neither gets a shim.
- `AttributeFactory` — **stays.** Its own docblock says it is shared between the Assembler
  and augmenter pipes that manufacture spec objects from reflection, and the call sites agree:
  `Builder`, `Assembler`, and three `Augmenter\Inheritance*` classes. A class two subsystems
  share is not owned by either, so `src/Assembler/` would misname it.
- `PipeInterface` — **stays**, and the reasoning is the useful part. This entry argued from
  visibility: `Contracts/` holds every other public extension-point interface. But what
  `Contracts/` holds describes *OpenAPI concepts* — attribute, translator, compiler, resolver
  — and `PipeInterface` is `__invoke()` plus `group()`, a pipe in a generic pipeline. Swap
  `Utils\Pipeline` for a library and the interface leaves with it. Moved, then reverted.

**What `Utils/` is for is now written down**, in `docs/dev/pipeline.md` beside the existing
note on directory layout — which was the point of the entry, since the cost was never the
current placements but the next class defaulting there.

Two hazards worth knowing before the next move of this shape, both caught by tests rather
than by reading. Unqualified references that resolved inside `OpenApi\Utils` break silently
on the way out — `Walker` lost `JsonPointer`. Worse, a deprecated interface shim makes
`instanceof` lie: with `PipeInterface` moved, `Pipeline`'s bare reference resolved to the
shim, so pipes implementing the new interface failed the check and fell into the default
group. A class shim does not have this problem; an interface shim always does.
