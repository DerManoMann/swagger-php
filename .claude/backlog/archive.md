# Archive

Finished entries — done, closed, or not doing — with the reasoning that made them so.
The ledger below is for scanning; the entries after it are for reading when a number
comes up. Flow rules: [README.md](README.md).

## Ledger

Newest first. An entry number appears once per merge that contributed to it.

| Merged | Entry | What |
| --- | --- | --- |
| #2188 | PR 44 | what a `Changes` entry is for, in CONTRIBUTING and the template |
| #2184 | PR 30 | hybrid unwraps `JsonContent`/`XmlContent` without the classic processors |
| #2183 | PR 35 (tail) | hybrid held to spec expectations in `ExamplesTest` and `DocSnippetsTest` |
| #2182 | Q5 (partial) | warn when a root `Response`'s key looks like a status code |
| #2181 | PR 24 | commit subject format and allowed types in CONTRIBUTING |
| #2179 | — | `Items` constructor docblock; squash carried #2178 — **6.8.1** tagged here |
| #2178 | — | `@OA\Examples` under a schema (merged via #2179's squash) |
| #2176 | PR 39 | schema `examples` as list, classic — closes #1994; **6.8.0** tagged here |
| #2175 | PR 39 | schema `examples` as list, spec |
| #2174 | PR 37 | drop a nested map entry that has no key |
| #2173 | PR 38 | generic docblock resolves to the type it parameterises |
| #2172 | PR 36 | component key inferred from the class for every bucket |
| #2171 | PR 35 | hybrid on `ScratchTest`'s mode axis; eight defects found |
| #2170 | PR 28 | nested operations collected from the parent that owns them |
| #2169 | PR 32 | generated extension points reference |
| #2168 | PR 20 | extension points guide |
| #2167 | PR 8 | an explicit `null` suppresses an inferred value |
| #2166 | PR 7 | augmenter config documented in one place |
| #2165 | — | `TokenScanner` fixture and tool-exclusion audit |
| #2164 | PR 12 | stacked-attribute sweep |
| #2163 | PR 14 | significance-clause rule; writing rules cover commits |
| #2162 | PR 8 | method-level `Schema` crash and the slot-type invariant |
| #2161 | PR 29 | level 6 type annotations for the spec namespaces |
| #2160 | PR 15 | mode-aware `ScratchTest` log keys |
| #2159 | PR 27 | inner-to-outer sibling merge |
| #2158 | PR 3 | `DocsAccuracyTest` and `composer docs:check` |
| #2157 | PR 10 | `OpenApiTestCase` migrated to extracted traits |
| #2156 | PR 14 | `Server`/`ServerVariable` summaries |
| #2155 | PR 26 | ref escaping |
| #2154 | PR 26 | input validation and the `SpecificationWalker` fix |
| #2153 | PR 26, 14 | attribute targets, `AttributeTargetsTest`; writing-rules scope |
| #2152 | PR 19 | `$argv` guard |
| #2151 | PR 19 | phpstan pinned |
| #2150 | PR 8 | `Undefined::UNDEFINED` on every `mixed` property |
| #2148 | PR 10 | `ExpectsLogEntries` |
| #2147 | PR 10 | `AssertsSpecEquals` extracted, `AssertsBuilderResult` retired |
| #2146 | PR 1 | `#[Config]` attribute; PR description template |
| #2145 | PR 21 | `TypedList::clear()` |
| #2144 | PR 9 | scratch fixtures for Head/Options/Trace and Link |
| #2143 | — | rector 2.6.5, pinned tooling |
| #2142 | — | `.dist` convention for the phpstan config |
| #2141 | PR 4 | doc generator merge; phpstan covers `tools/` |
| #2140 | PR 15 | the `ScratchTest` failure #2137 and #2138 produced only once merged |
| #2139 | — | README corrections |
| #2138 | PR 13 | compiler diagnostics reaching the configured logger |
| #2137 | PR 8 | `ComponentIndex`, slot-target validation, uncompiled attributes |
| #2136 | — | developer docs, and the writing rules |
| #2135 | — | rector rule changes |
| #2134 | — | spec docs cleanup |
| #2130 | — | resolver step |

## Notes

**Release notes: `08c8a2d2` contains far more than its title says.** #2178 was reviewed as
its own pull request but never merged as one. #2179, a one-line docblock fix stacked on it,
was based on `master` — a cross-repo pull request cannot target a branch on the fork — so
its diff carried all six commits, and squash-merging it landed the lot under
`fix(Attributes): repair the Items constructor docblock (#2179)`. #2178 is closed as merged;
#2177 is closed against that commit.

**6.8.1 is tagged at `08c8a2d2`** (2026-09-09), and its notes describe the commit by its
contents rather than its title: `@OA\Examples` under a schema no longer demands an `example`
key or a `summary`, a schema's `examples` accepts plain values in both syntaxes, and two
diagnostics are added for cases that used to pass in silence. Patch won over minor —
accepting plain values is new input, but the release exists to repair a regression 6.8.0
shipped, and the upgrade note it carries is about 6.8.0's output change rather than the new
input.

**The stacking is the lesson.** GitHub will not take a fork branch as a base, so a stacked
pull request has to be based on `master` and carries its parent's commits whatever the
description says. Either wait for the parent to merge before opening the child, or expect
one merge to take both.

**The 2026-09-02 goal, closed.** "Make the spec pipeline as good as it can be at what it
already does" ran in three strands: the test migration finished with #2157 (PR 10); the
behaviour hunt ran out with PR 26 and its findings are fixed (#2154, #2155, #2159, #2162);
the documentation strand ended with #2168 and #2169. The behaviour strand reopened twice on
new instruments — #2171 compared what the pipelines *emit* rather than what their tests
assert, and PR 38 varied the docblock while holding the type. The lesson: a strand is
exhausted only for the comparison that was run; each new axis of comparison is a new
instrument.

---

## Entries

### PR 44 — a `Changes` entry has no stated altitude — **done, #2188**

CONTRIBUTING asked for a `Changes` list "kept high level" without saying what that rules out,
so the same detail kept coming back: conditions, counts, and behaviour the diff already
shows. Not wrong, just unreadable in bulk — they bury the one or two entries that carry the
shape of the change.

**#2188** states that an entry names what moved in one line, and that a condition, a count or
a signature is what the diff is for. The template comment carries the same test where it is
read while drafting.

Found by writing #2187's description badly twice, after the same note on #2185 and #2186.

### PR 20 — the NelmioApiDocBundle proof of concept, and the docs behind it — **done, #2168 + #2169; PoC at nelmio#2803**

The documentation half shipped: `guide/extension-points.md` (#2168) covers the hooks and how
they compose, and `reference/extension-points.md` (#2169) generates the list of what runs by
default. What remains is the proof of concept itself, which is outward-facing and a separate
decision.

Two halves, both worth keeping. The design docs are parked; the proof of concept is public
and has been introduced upstream (2026-09-11).

**The design docs** were on `feat/downstream-support`, 1632 lines across four files. That
branch has been dropped — almost everything it proposed has since shipped:

| Proposal | Status |
| --- | --- |
| widen `addSource()` to accept reflectors | shipped, `string\|\SplFileInfo\|\Reflector\|iterable` |
| reflector parameter on `AttributeTranslatorInterface::translate()` | shipped |
| add `Schema\Ref` | shipped, `src/Spec/Schema/Ref.php` |
| add `Pipeline::insertAfter()` | dropped — `TypedList::insert()` takes a callable returning an index, so this is redundant |
| document programmatic `Specification` population | becomes the extension points page below |
| document the Attachable metadata pattern | as above |
| document `Undefined` scope in spec attributes | as above |

Its tip was `d6a0ac5d` if any of the rest is ever wanted back.

What does not exist anywhere else is the set of design principles it recorded — constraints
on how the pipeline should *not* be extended, each with a reason:

- **Do not widen property types for downstream convenience.** The strong typing is the
  point. Downstream metadata belongs in Attachables, not in `$ref: string|object`.
- **Do not add framework-specific code.** swagger-php stays framework-agnostic; translators,
  augmenters and attachables are the contract.
- **Do not add event or listener patterns.** The pipeline is deterministic and debuggable.
  Events introduce non-obvious ordering and make testing harder.
- **Do not expose Assembler internals.** `collect()` is the contract; two-pass resolution is
  an implementation detail.

These belong in the extension points page — they answer "why can't I just…", which is the
question an integrator actually arrives with.

**The proof of concept** is `DerManoMann/NelmioApiDocBundle` at `spec-poc`, locally
`../NelmioApiDocBundleFork`. About 540 lines under `src/SpecPoC/` — three attributes, two
augmenters, five translators, plus `Run.php` and a `poc.php` entry point. It exercises the
extension points rather than being a real integration: attribute translators for Symfony's
routing and `MapRequestPayload` / `MapQueryParameter`, augmenters for models, and auto
annotation of classes and public properties via a translator. `Run.php` carries a note that
the `MapRequestPayload` handling is deliberately one of several possible approaches.

Reviewed, polished and published (2026-09-11). The branch is squashed to a single commit on
top of upstream 5.11.1 at
[`spec-poc`](https://github.com/DerManoMann/NelmioApiDocBundle/tree/spec-poc) in the public
fork, with the write-up at `src/SpecPoC/README.md`. `composer.json` now requires swagger-php
`^6.8` — the earlier `^5.7.8 || ^6.0` resolved to versions without the hooks the PoC uses.

Introduced to the Nelmio project as
[nelmio/NelmioApiDocBundle#2803](https://github.com/nelmio/NelmioApiDocBundle/issues/2803)
(2026-09-11) — deliberately an issue and not a pull request, since the point is an early
heads-up ahead of v7/v8 rather than a change to land.

**Generic instantiations stay out of core.** The PoC emits one schema for `GenericClass<T>`
where the bundle emits eleven, one per instantiation (`GenericClass`, `GenericClass2`, …).
That gap is a decision, not a todo:

- swagger-php already ships the mechanism. `Builder::withResolver()` is the hook, and the
  PoC's own README names a registry-backed resolver as the answer.
- There is no neutral naming scheme to ship. `GenericClass2` is positional and depends on
  encounter order — a presentation choice OpenAPI says nothing about, so core would own that
  scheme forever and anyone wanting a different one would have to fight it.
- `ModelRegistry` already is that naming layer, in the one project known to need it.

Revisit if a second consumer asks. Even then the reusable part is the traversal that expands a
generic into per-instantiation components, not the naming, and that could ship as an optional
package without core committing to a scheme.

Not to be confused with PR 38 — a generic *docblock* resolving to nothing. That one was a core
bug in both pipelines and shipped in #2173.

**Re-check the docs against master before showing anyone.** They were written before #2130
and several claims have moved:

- `downstream-integration.md` opens with "they don't scan files — they discover endpoints via
  framework routing and use reflection on known controller classes". That is now largely
  served by reflector sources plus the resolver, and PR 17 measures what it buys.
- `SWAGGER_PHP_NATIVE_SPEC_PLAN.md` argues against hybrid partly on "HybridBridge doesn't
  transfer attachables". `Spec\Attachable` and an `attachables` bucket on `Specification`
  exist now, so at minimum the wording needs revisiting even if the conclusion holds.
- Its other objection, that `HybridBridge` skips annotations without reflectors, still reads
  true — `HybridBridge` bails on `!$annotation->_context->reflector instanceof \Reflector`.

**An extension points page comes out of this.** Agreed as part of the work: a `docs/dev/`
page for developers integrating swagger-php into their own tooling, written from whatever
the PoC shows actually works rather than from the API surface alone. Nothing under `docs/`
addresses that audience today — `reference/builder.md` documents the hooks one at a time,
which is not the same as showing how they compose.

The surface it would cover, all already public:

| Hook | For |
| --- | --- |
| `Builder::addSource(\Reflector)` | seeding from framework routing instead of a directory scan (PR 17) |
| `AttributeFactory::withTranslators()` | `AttributeTranslatorInterface` — turning foreign attributes into spec ones |
| `Builder::withAugmenters()` | `PipeInterface` — enriching the specification, grouped into phases |
| `Builder::withResolver()` | `ResolverInterface` — supplying classes the specification refers to but does not contain |
| `Builder::withAttributeFactory()` | assembly-time control |
| `Builder::setCompiler()` | `CompilerInterface` — version-specific output |
| `Builder::withGenerator()` | the classic escape hatch, hybrid only |
| subclassing an `OpenApi\Spec` attribute | a constructor that derives the attribute's own arguments, typically by reflecting over a target class |

The PoC exercises the first four, which is the useful signal about what to write up first.
Worth capturing the dead ends too — `Run.php` already notes that its `MapRequestPayload`
handling is one of several approaches and probably not the best.

**Subclassing a spec attribute is a working extension point that nothing tests.** Found
while doing PR 15, which removed the `echo` that had been announcing the two `Scratch`
fixtures with no `-spec.php` counterpart. They turn out to mean opposite things:

- `ThirdPartyAnnotation` is **permanently classic-only**. The scenario is a Doctrine
  `@Annotation` class sharing a docblock with `@OA\Schema`; spec assembly never runs the
  annotation parser at all — `Assembler` goes straight to `AttributeFactory`, and docblocks
  are read only for `@var`/`@param` types. There is no spec analogue to write. Its other
  content, `Child extends SomeParent` with a schema on both, is already covered by
  `Docblocks-spec.php`.
- `ComplexCustomAttributes` is **a real gap, and belongs here rather than with the fixtures**.
  It is about user attributes extending `OAT\Schema`/`Property`/`Response` with constructors
  that reflect over a target class — an integrator question, not a classic-syntax one. Spec
  attributes are not `final`, and the pattern works: a subclassed `Spec\Schema` on a class
  and a subclassed `Spec\Response` on a method both resolve, emitting the derived `required`
  list and the `$ref`-ed response. Verified by hand, covered by nothing.

Do not transliterate the classic fixture when writing the spec one. It reads like a pasted
bug report — commented-out experiments, `list(): string` with no return — and its `Item`
subclass, `ref` alongside `title`/`description`, is the `$ref`-with-siblings case
`CompilerTest::test30RefStripsDescription` and `test31RefAllowsSiblings` already pin. What is
worth keeping is the shape: constructors deriving `required` from public properties, and
`#/components/schemas/{ShortName}` refs built from a `class-string`.

With the `echo` gone both fixtures now skip spec mode silently, which reads as an oversight
in the one case and nothing at all in the other. A suite-wide invariant over
`Scratch/*.php` — every fixture has a `-spec.php` or an allowlist entry carrying its reason
— would record the distinction. Deliberately not done in PR 15: the allowlist only earns its
keep once the `ComplexCustomAttributes` half is actually written.

**A fictive example under `docs/examples` is the better artefact for the docs.** Size fits:
the unit to compare against is one processor example — `schema-query-parameter` is 114 lines
plus a small `app/` fixture and an expected yaml — not the 8422-line `specs/` tree. A
synthetic framework integration would shed the Symfony routing and bundle wiring the real
PoC needs, and could plausibly land in 150-250 lines while still showing reflector sources,
a translator and an augmenter together.

The catch is that **`docs/examples/processors/` is verified by nothing**:

- `UsesExamples::examplePath()` hardcodes `docs/examples/specs/`, so `ExamplesTest` never
  sees it
- `composer redocly` lints `docs/examples/specs/**/*.yaml` and `tests/Fixtures/Scratch/*.yaml`,
  not the processor yamls
- only phpstan touches it, via the `docs/examples` path

Both processor examples therefore carry expected-output yaml that nothing compares against.
That is an argument for the approach rather than against it — the same captured-not-composed
problem as the CLI help text — but wiring `processors/` into the suite has to come with it,
or the new example rots the same way. Companion to PR 9, which widens what Redocly checks.

Suggested split: the fictive example carries the documentation and can be verified, so it is
what the extension points page links to and what the Nelmio project reads. The fork PoC stays
where it is as evidence the approach works against a real bundle, and is not polished into
documentation.

**Closed 2026-09-12.** Everything the entry wanted exists, and the last strand went a
different way than written:

- The design principles are in `guide/extension-points.md` under "Where the boundaries are",
  all four, each with its reason.
- The `ComplexCustomAttributes` gap is closed by the page's own snippets rather than by a
  `Scratch` fixture. `dto_attribute.php` is a `Dto extends OA\Schema` whose constructor
  derives `schema` and `required` by reflecting over a target class — the pattern the entry
  described — and `ExtensionPointsSnippetTest::testSubclassedAttributeDerivesSchemaAndRequired`
  pins it. A suite-wide `Scratch/*.php` invariant is therefore not owed anything here.
- **The fictive `docs/examples` integration was superseded.** Verified snippets under
  `docs/snippets/guide/extension-points/` took the job: `ExtensionPointsSnippetTest` runs
  them through the composed `buildSpec()` the page shows, and `composer redocly` lints their
  yaml. That is the verification the `docs/examples` route was going to have to build.
- The caveat that came with that proposal — `docs/examples/processors/` is compared by
  nothing — is not carried forward. Those examples are classic, and go when classic goes.
- The "re-check the docs against master" note is moot: `feat/downstream-support` was dropped,
  and what shipped was written against master.

The PoC is public at `spec-poc` and introduced as nelmio#2803, which is where a review would
now happen. Nothing here is waiting on this repository.

### PR 30 — hybrid runs two classic processors for a mapping the bridge already knows — **done, #2184**

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

### PR 35 (tail) — hybrid comparison in the remaining test suites — **done, #2183**

The tail of PR 35: hybrid ran in `ExamplesTest`, `DocSnippetsTest` and `CommandlineTest`
but was never compared against an expectation that would catch a deviation. **#2183**
makes `getSpecFilename()` and `DocSnippetsTest` prefer spec expectations for hybrid —
the precedence `ScratchTest` already uses — and lets `ExamplesTest` run spec sources in
hybrid mode (+58 tests). `CommandlineTest` was deliberately left out: it is a wrapper
around the same generator paths the other suites already compare.

### PR 24 — nothing says what a commit message should contain — **done, #2181**

**Mostly closed before it was picked up.** #2163 landed the bodies half while this entry sat
here: `docs/dev/writing-docs.md` now says the prose rules cover commit messages and that a
body documents what the diff does rather than why, and CONTRIBUTING carries the one-line
orientation pointing at it. So the gap below is narrower than written.

What was left is the subject line. CONTRIBUTING stated the shape for **pull request titles**
only; the shape for commits, and the list of allowed types, lived just in `AGENTS.md`, which
is written for agents rather than contributors. A human reading CONTRIBUTING end to end
learned how to title the pull request and not the commits inside it.

Adding it turned up a second thing: **the documented type list was missing `test`.** It is in
regular use and has been through review — #2158, #2165, #2167 and #2171 all merged under it —
so the list was wrong rather than the practice. Both files now name six types.

The original framing follows.

`.github/PULL_REQUEST_TEMPLATE.md` and CONTRIBUTING's "Pull request titles follow
`type(Scope): subject`" both govern pull requests. The only rule touching commits is one
line in `AGENTS.md` — the same `type(Scope): subject` shape and the list of allowed types —
and it says nothing about bodies.

That is a gap rather than a deliberate omission, because CONTRIBUTING leans on the commit
trail without ever describing it: "History that lives elsewhere — earlier attempts,
abandoned branches, related work in other pull requests — belongs in the issue or commit
trail, not here." Material is being routed somewhere the conventions never define.

The PR template's own guidance transfers almost unchanged — why the change exists rather
than how, identifiers in backticks, no code snippets, keep to the change at hand — so this
is a short addition to CONTRIBUTING next to the PR paragraph, not a new document.

Came up while splitting the `Undefined::UNDEFINED` work out of `feat/spec-3.2-fields`:
writing the message meant guessing at a convention that only exists for PR descriptions.

### PR 39 — `Schema::$examples` compiles to a map, and its own docblock says list — **done, #2175 + #2176**

In OpenAPI 3.1 a Schema Object is JSON Schema, where `examples` is **an array of example
values**. `OpenApi31Compiler` compiled it as a map of Example Objects, keyed like the `examples`
that hang off a parameter, header or media type — which genuinely are maps:

```yaml
    YoYo:
      examples:
        yo:
          summary: 'the yo'
          value: YoYo
```

**There is no design question here, which is what the first draft of this entry got wrong.**
`OA\Schema::$examples` already declares what it holds:

```php
@param list<mixed>|null $examples   A list of example values
```

Bare values, exactly what JSON Schema wants. So the property is not ambiguous and nothing has
to be decided about it. `compileSchema()` ignored that contract by passing the list through
`compileExamples()`, which read each element as an `OA\Example` and keyed by `->example`. The
`summary`, `description` and `externalValue` this entry first worried about have nowhere to go
because they were never meant to be there — a map of Example Objects is what a media type,
parameter or header takes, and one of those is where an author wanting them should be.

**One mistake, four symptoms, in a chain.** The `Examples` fixture passed `OA\Example` objects
into a `list<mixed>` that wants values. Nothing catches it: the runtime type is `?array` and
`mixed` accepts anything. 3.1 then compiled those objects into a map. Redocly flagged the map as
a `struct` error, and `.redocly.lint-ignore.yaml` carried
`#/components/schemas/YoYo/examples` for `tests/Fixtures/Scratch/Examples3.1.0.yaml`, so
`composer redocly` passed on a structurally wrong document. That is PR 31's shape inverted: not
an exclusion that stopped doing anything, but one doing exactly what it says and hiding a defect.

**And it left the 3.0 branch untested and worse.** `OpenApi30Compiler` honours the contract —
`$result['example'] = $schema->examples[0]`, a value — so fed an Example Object it serialized the
whole thing, swagger-php internals included:

```json
"example": { "x": null, "attachables": null, "example": "yo",
             "summary": "the yo", "description": null, "value": "YoYo",
             "externalValue": null, "ref": null }
```

No `Examples3.0.0.yaml` existed, so no fixture covered it. The two compilers disagreed about
what the property holds, and only one of them agreed with the docblock.

**It was already reported, as [#1994](https://github.com/zircote/swagger-php/issues/1994), open
and marked critical.** That issue states the rule from the specification — `Parameter.examples`
and `MediaType.examples` are maps of Example Objects, `Schema.examples` is the JSON Schema
keyword and takes an array of literal values — and cites 4.8.19.2 and the model-with-example
shape. Nothing here found anything the reporter had not, which is worth saying: the backlog
reasoned its way to a conclusion that was sitting in the issue tracker the whole time. **Search
the open issues when an entry is written, not when it is closed.**

The issue also names `Property`, which this entry did not. It needed no work but did need
checking, because "closes" is a claim: classic's `Property extends Schema` inherits
`jsonSerialize()`, and spec's `Property` wraps a `?Schema $schema` compiled through
`compileSchema()`. Covered by structure rather than by duplicated code.

**Done in two PRs, deliberately split.** #2175 fixes the spec pipeline and #2176 fixes classic,
because the second changes output in the *stable* pipeline and deserved to be revertable on its
own. Classic collected `@OA\Examples` under a schema and keyed them, so
`Schema::jsonSerialize()` now maps them to their values for 3.1 and later.

**Both redocly ignore entries are gone**, which was the point — 183 explicitly-ignored problems
down to 181. Before removing them each was checked by deleting it and re-running: they errored,
so they were load-bearing rather than stale. PR 31 argues for exactly that check on the way in;
this is the same check on the way out.

Two things the work turned up that the entry did not predict:

- **3.0 had no expectation at all**, which is why its branch — `$result['example'] =
  $schema->examples[0]` — went untested and serialized whole Example objects, `x`, `attachables`
  and `ref` included. It now has one. 3.0 keeps a `-spec` split legitimately: `examples` is not
  a schema field there, so classic drops it while spec carries the first value across as
  `example`. Both warn.
- **`ScratchTest` declared a log message that does not exist.** `'Examples-3.0.0'` expected
  `@OA\Schema() is only allowed as of 3.1.0`; the real text is `@OA\Schema::examples`. With no
  3.0 expectation the case never ran, so the declaration was never exercised and the wrong text
  never mismatched. A third species of dead configuration, after PR 31's tool exclusions and the
  redocly ignore above: an expectation nothing asserts.

### PR 37 — a nested map with no key still compiles to a JSON array — **done, #2174**

PR 36 fixed this for the `components` buckets and stopped there. The nested maps — a
`Response`'s `headers` and `links`, a `MediaType`'s `examples` and `encoding` — still went
through `compileNamedMap()`, whose fallback was `$item->$key ?? (string) $index`. An integer
key makes the whole map serialize as a JSON array, and OpenAPI requires
`Map[string, Object]` in every one of those positions:

```json
"headers":  [ { "description": "nameless nested header" } ],
"examples": [ { "summary": "nameless nested example" } ]
```

**Spec was worse than classic here**, which is the part that decided it. Classic rejects the
same input — `@OA\Header() is missing key-field: "header"`, asserted in
`AbstractAnnotationTest` — while spec emitted a document no validator will accept and said
nothing. That is the failure mode PR 15 and #2162 both turned out to be, in a third place.

Found while writing PR 36's fixture; scoped out of it deliberately, because the entry was
about component identity and this is about a map key that never had one.

**Done. The three fallbacks split the way the entry guessed, and a fourth call site turned up
that fits none of them.** Seven maps were name-keyed and now drop an unnamed entry through
`compileKeyedMap()`, with `validateNestedNames()` reporting it — a response's `headers` and
`links`, a media type's `examples` and `encoding`, an encoding's `headers`, and a parameter's
and a header's `examples`. The status-code and content-type maps keep their fallbacks, being
values. The link's `operationId` fallback went, as predicted.

The fourth is `Schema::$examples`, which shares the helper and is neither: in 3.1 it is the
JSON Schema keyword, which takes **a list, not a map**. It compiled to a map. Left alone
here — an unnamed example is the *normal* case for a list, so dropping one would delete
intent rather than protect it. See PR 39.

Where the warning goes turned out to be the smaller half. `SpecificationWalker::visit()`
recurses through every property, so `visit(OA\Response::class, ...)` reaches a response wherever
it sits, and a `container => [property => key field]` table covers all five containers.
`Builder` captures `validate()`'s return before calling `compile()`, so a diagnostic raised
during compilation never reaches `Result`, which is why this had to live in validation rather
than beside the code that drops the entry.

### PR 38 — a generic docblock resolves to nothing, in both pipelines — **done, #2173**

Three properties of the same class type, differing only in their docblock, run through all three
modes. Reproduced on `origin/master` at `bfa6b0ce`, so both findings predate #2171 and PR 36:

| declared as | classic | hybrid | spec |
| --- | --- | --- | --- |
| `public Target $native` | resolves | resolves | resolves |
| `/** @var Target<string> */` | **`{}`** | **`{}`** | **`{}`** |
| `/** @var Target */` | resolves | resolves | resolves |

The script behind the table is in [docblock-types/](docblock-types/README.md).

**The generic docblock is the finding, and it belongs to both pipelines.** A property typed
`public Target $x` resolves on its own; adding `/** @var Target<string> */` above it made the
resolver return nothing, with no fall back to the native type. So a generic docblock was strictly
worse than writing none, which is the opposite of what a docblock is for.

Taken in time for the Nelmio PoC: #2173 shipped in 6.7.2 on 2026-09-08, three days before the
branch went public, so the PoC's README no longer has to call it out. It is exactly what made
that bundle's `GenericTypesController` come out with every property empty.

**A second, much narrower one, spec only.** In the *global namespace*, a short-name docblock
compiled to a `$ref` with a leading backslash, which never matches the ref map —
`ComponentIndex` keys on `getClassName()`, which has none:

| in the global namespace, spec mode | |
| --- | --- |
| `/** @var GTarget */` | `$ref: \GTarget` — unresolved |
| `/** @var \GTarget */` | resolves |
| no docblock | resolves |

Inside a namespace the short name resolves against it and comes out clean, which is why the
table above does not show this. Classic got the global case right, so unlike the generic
docblock there was a correct implementation to compare against rather than design.

That makes it the shape PR 26 went looking for — behaviour classic asserts and spec does not —
turning up after that hunt was declared closed, and after #2171 found eight more by comparing
outputs rather than tests. Third time. The lesson is not that the survey was sloppy; it is that
each new axis of comparison is a new instrument, and this one (vary the docblock, hold the type)
had never been pointed at anything.

**The cause was one missing arm, and the table above was reading a symptom.** `Type\TypeResolver`
— the mapper `TypeInfoTypeResolver` delegates to, distinct from the `TypeResolverInterface`
implementations — matches `BuiltinType`, `ObjectType`, `IntRangeType`, `ExplicitType`,
`ArrayShapeType` and `CollectionType`, and had nothing for `GenericType`. It fell through to an
empty `SchemaType`, so `Types::augmentProperty()` bailed and the property kept no type.

So "both pipelines, older than either" was wrong twice over. symfony/type-info parses the generic
correctly and hands back a `GenericType` wrapping the parameterised type; swagger-php discarded
it. And `LegacyTypeResolver` reaches `TypeMapper` without touching this class, so it was never
broken — classic looked broken only because `TypeInfoTypeResolver` is the default. Three of the
four mode/resolver cells failed, and all three failed for the same one reason.

The lead came from asking whose code reads docblock generics. It is type-info's, which made
"swagger-php is mishandling what it gets back" the first thing to check rather than the last.
Worth keeping as a habit: when a bug sits on an integration seam, establish which side produced
the wrong value before reasoning about either.

The global-namespace half was a second missing normalisation in the same method — an `ObjectType`
class name kept the leading slash type-info gives it there. Both fixes are in #2173, with
`DocblockGenerics` covering all four cells against one expected document and `GlobalNamespaceTypes`
covering the case no `Scratch` fixture can reach.

**cs-fixer deleted the docblocks the fixtures exist for.** `no_superfluous_phpdoc_tags` reads
`@var Target` above `public Target $x` as redundant, which it is everywhere except here. The
tests still passed and proved nothing. Both fixtures are now in the cs-fixer filter with the
reason stated, next to the entries PR 31 audited — a live exclusion rather than a dead one.

### PR 36 — `Names` infers a component key from the class for some component buckets only — **done, #2172**

`Augmenter\Names` filled a missing component key from the declaring class, but only for schemas,
parameters and — since #2171 — request bodies. Nothing did the same for responses, headers,
examples or links.

The consequence was not cosmetic. `OpenApi31Compiler` keyed an unnamed component positionally —
`fn (OA\RequestBody $body, int $index): string => $body->request ?? 'body' . $index` — so a
class-level attribute with no explicit key compiled to `body0`, and because
`ComponentIndex::buildRefMap()` skips any component whose name is null, a `$ref` given as a
class name never resolved either. Both symptoms showed up together in #2171, where a bare
`#[OAT\RequestBody]` on a class produced `$ref: OpenApi\Tests\Fixtures\Scratch\RequestBodyRef`
against a component called `body0`.

**The rule holds everywhere, and the question answered itself once the code was read** — there
were already *two* rules, not one. Schema and RequestBody took the class name; Parameter took
`name` and Link took `operationId`, which is "the key comes from the field that already
identifies it". Responses, headers and examples have no such field, which is why they fell
through. Both rules stand: a class-declared component takes the class short name, and
`Parameter` keeps `name` ahead of it.

Three things the entry did not predict, each found by making the fix rather than by the survey
that wrote the entry:

- **The positional fallback emitted invalid documents, not merely odd names.**
  `components.headers` and `components.examples` compiled to JSON *arrays* where OpenAPI
  requires `Map[string, Object]`, and `components.responses` took an empty-string key from
  `(string) null`. Reproducible in spec mode through `#[OA\Components]` stacked on a class,
  so it was never hybrid-only.
- **The key logic was duplicated three times, not two.** `Augmenter\Cleanup` carried a third
  copy beside the compiler's and `ComponentIndex`'s, and all three disagreed — a link with no
  `link` compiled under its `operationId`, indexed as nothing, and was pruned as unreferenced.
  `Specification\ComponentName` is now the single answer, and the compiler's invented
  fallbacks went with it: an unnamed component is dropped and reported, and a key claimed
  twice is reported too.
- **`compileExample()` never emitted `$ref`**, unlike `compileHeader()` and `compileLink()`,
  so an example could be named and still never referenced.

Two `HybridBridge` bugs came with them, surfaced by the new duplicate-key warning rather than
looked for. A `SecurityScheme` nested in `Components` was converted twice — its match arm was
the only one carrying no nested guard. Guarding it exposed why that had gone unnoticed: a
`Components` merged into `OpenApi`, which is what the *attribute* form always produces, was
never converted at all, so every component declared that way was silently dropped and the
unguarded arm had been accidentally rescuing the schemes.

That is #2171's lesson a second time. The instrument that finds bugs in both pipelines need
not be a survey — here it was one new warning and one fixture whose classic and spec halves
had to agree.

**Classic does not hold this rule and should not be made to.** A class-level `@OA\Response`
without a key is an error there, and `@OA\Header`, `@OA\Link` and `@OA\Examples` are not
valid on a class at all. So `ComponentNames` spells the keys out on the classic side and omits
them on the spec side, and the single expected document asserts that inferring and naming by
hand produce the same thing.

### PR 35 — `ScratchTest` never runs hybrid, and 14 fixtures disagree when it does — **done, #2171**

`ScratchTest`'s mode axis was `CLASSIC` and `SPEC`. Hybrid was exercised only by `ExamplesTest`,
`DocSnippetsTest` and `CommandlineTest`, none of which compared it against the classic document
it is supposed to reproduce. That is how the nested-operation bug in PR 28 survived: hybrid
emitted `/nested: []` where classic emitted the operation, and nothing looked.

Adding `Builder\Mode::HYBRID` to the axis needed no fixture work — the source selection only
swaps in `-spec.php` for `SPEC`, so hybrid reads the classic file as it should. Measured on
2026-09-06, that gave **463 cases and 38 failures across 14 fixtures**, roughly one per
version:

| Failures | Fixture |
| --- | --- |
| 6 | `RequestBody` |
| 3 | `UsingRefs`, `ThirdPartyAnnotation`, `Security`, `NestedSchema`, `NestedAdditionalProperties`, `MergeTraitsExtended`, `Encoding`, `DuplicateRef` |
| 2 | `NullRef`, `MultiTypeProperty`, `Examples` |
| 1 | `Tags`, `Docblocks` |

So **24 fixtures already matched** and were pinned the moment the mode was added.

**Do not paper the other 14 over with `-hybrid.yaml` overrides.** The lookup supports them, so
it was the tempting move, and it would enshrine whatever the bridge currently drops. The one
sampled — `Tags` — failed on a missing `summary`, which is the same species as PR 28: a field
the bridge does not carry across. Each of the 14 was a finding until shown otherwise.

**#2171 did all of it**: the mode is in the matrix at 466 cases with nothing excluded. The
premise above was two-thirds right. Eight of the 14 were defects — six in the bridge (seven
JSON Schema keywords, `summary`/`parent`/`kind` on a tag, `headers` on an encoding, a
property's own encoding, nested-only schema types collected as class schemas, class-level
`Parameter`/`RequestBody` skipped) and two in the spec pipeline, which the hybrid comparison
found by accident.

The other six were **classic quirks, not hybrid faults** — a `description` duplicated beside a
`oneOf`, `type: [string]` where the spec compiler writes `type: string`. Hybrid feeds the spec
compilers, so it is now held to the spec expectation where a fixture has a spec pair and to
classic's otherwise. Only `ThirdPartyAnnotation` needed a `-hybrid.yaml` override, for a
genuine rendering difference: spec puts `type: object` on a class-derived schema that only
composes an `allOf`, classic does not.

So the "each of the 14 is a finding until shown otherwise" rule was worth holding, but the
conclusion it implied — that every disagreement is a dropped field — was not. Comparing two
pipelines finds bugs in both, and sometimes the fixture is the thing that is wrong.

`Auth` lost its split `-classic.yaml`/`-spec.yaml` expectations along the way: they existed
only because `Auth-spec.php` declared a `mutualTLS` scheme classic cannot express, which
`CompilerTest` already covers end to end.

Worth knowing: a reflector source yields nothing in classic or hybrid, since both scan
files — `addSource(new \ReflectionClass(...))` silently produces an empty document rather
than failing. It cost a false-passing test while writing PR 28's coverage.

The remaining tail — hybrid uncompared in `ExamplesTest` and `DocSnippetsTest` — is #2183.

### PR 34 — `--prefer-lowest` means something different in every CI cell — **closed, not doing**

**CLOSED (2026-09-11).** The entry ended with "needs confirming rather than assuming". It was
confirmed, and both halves of the premise are false. Kept for the numbers, so the pin is not
proposed again.

The claim was that `composer.json` sets no `config.platform.php`, so the five `lowest` cells
each resolve a different dependency set against whatever PHP is running, and pinning to
`8.2.0` would make one reproducible set.

**There is only ever one set.** Resolving `--prefer-lowest --prefer-stable` against each
simulated platform gives a byte-identical 74-package list on 8.2, 8.3, 8.4, 8.5 and 8.6, and
adding the pin changes nothing on any of them. Floors have no upper PHP bound, so the host
version never enters the lowest solution. Nothing was unreproducible.

**The pin does reach the `highest` cells, and that is the whole problem.**
`config.platform.php` constrains every resolution, not the `--prefer-lowest` one. Measured on
PHP 8.5, adding it to `composer.json` moves the highest set backwards:

| | without the pin | with `platform.php: 8.2.0` |
| --- | --- | --- |
| `phpunit/phpunit` | 13.3.3 | 11.5.56 |
| `symfony/console` | v8.1.6 | v7.4.18 |
| `symfony/yaml` | v8.1.6 | v7.4.18 |

PHPUnit 13 and Symfony 8 both need PHP ≥ 8.4, so the pin excludes them everywhere. The
`highest` cells on 8.4, 8.5 and 8.6 are the only thing exercising the `^8.0` half of
`symfony/console`, which is declared support. The change costs that and buys nothing.

**What the four extra `lowest` cells are actually for** — since they resolve identically,
their value is runtime rather than resolution: floor dependencies running on a newer PHP.
That is coverage a resolution diff cannot see, and it is the reason not to trim them either.

The re-run remains worth doing whenever a floor is raised, as PR 33 says. It needs no config
change — `composer update --prefer-lowest --prefer-stable --dry-run` is the whole procedure.

### Q3. Are the classic-vs-spec output differences real? — **RESOLVED (2026-09-11)**

Yes, five of them, and the answer came from the fixtures rather than a survey. `ScratchTest`
holds every mode to one shared expectation unless a mode-specific file exists, so the override
files **are** the divergence list. Read after #2171 put hybrid on the mode axis: 43 classic
families, 40 with a spec pair, 494 cases green.

| Fixture | Scope | Difference |
| --- | --- | --- |
| `DuplicateRef` | all versions | spec emits `type: object` on the `allOf` schema |
| `MergeTraitsExtended` | all versions | same `type: object`, plus nullability inferred from `?\DateTime` |
| `ThirdPartyAnnotation` | all versions, `-hybrid` | same `type: object` — a classic-only fixture, so hybrid diverges from classic |
| `NullRef` | 3.1 / 3.2 | classic repeats `description` outside `oneOf`; spec emits it once |
| `MultiTypeProperty` | 3.1 / 3.2, type-info resolver | classic emits `type: [string]`, spec `type: string` |
| `Examples` | 3.0 only | `Schema::examples` is 3.1+: classic drops it, spec downgrades to `example`. Both warn |

Five distinct causes, not six files — `type: object` accounts for three of them. The version
and resolver scoping is the part the entry previously guessed at: `NullRef` and
`MultiTypeProperty` agree at 3.0, `Examples` only differs at 3.0, and `MultiTypeProperty`
differs under the type-info resolver alone.

**`Auth` is off the list, and how it left matters.** It was the first of the five, and #2171
resolved it by **deleting the `mutualTLS` case from both fixtures** (`bfa6b0ce`) — the
pipelines did not converge, the coverage did. That divergence was a fixture-source difference
all along (classic's `OAT\SecurityScheme` validates `type` against four values and cannot
express `mutualTLS`), not a pipeline one, which is why it could be edited away. The capability
gap is still real and now nothing pins it.

**What this settles for `docs/guide/spec-attributes.md` § Other differences**, which is where
the hedged claims live:

- **`type: object` on `allOf` schemas** — confirmed. Not "in some cases": every class-level
  schema with `allOf`.
- **Nullable `$ref` does not duplicate `description`** — confirmed, and 3.1+ only.
- **Nullable inference from PHP types** — confirmed by `MergeTraitsExtended::$deleted_at`;
  "classic may not infer in all cases" can name the case.
- **Single-element `type` arrays reduced to string** — confirmed, but narrower than written:
  the type-info resolver at 3.1+.
- **Duplicate `$ref` deduplication in `allOf`** — **documented backwards.** The fixture named
  for it emits one `$ref` in both modes. Running the scenario found duplication in the other
  direction: spec and hybrid emit the `$ref` twice when the explicit one is written as a
  class-string, classic once. That is PR 42, and the claim left the page.
- **Trait property ordering** — **real, and the stated rule was backwards.** Nothing in the
  suite pins it (`assertSpecEquals` compares maps order-independently), so it had to be run:
  classic follows `use` declaration order, spec reverses it. Documented as measured; the
  reversal itself is PR 43.
- **No `requestBody` on `Get`/`Head`/`Options`/`Trace`** — an API-surface difference, not an
  output one; no fixture can show it, and none should.
- **Missing: `Schema::examples` at 3.0.** Classic drops, spec downgrades to `example`. Real,
  documented nowhere.

The trigger (before spec becomes the default) did not need waiting for — the instrument that
answered this was #2171's mode axis, which arrived for other reasons.

`docs/guide/spec-attributes.md` was rewritten against this (2026-09-11): hedges dropped,
version and resolver scoping added, the `Schema::examples` case written up, the dedup claim
removed, trait ordering corrected. Three follow-ups fall out — PR 42, PR 43, and `mutualTLS`
losing its fixture.

**The two claims nobody could act on were both wrong, and each hid a defect.** Neither was a
case of documentation drifting away from working code: the hedge was the tell that the
comparison had been reasoned about rather than run, and running it is what produced PR 42 and
PR 43. A hedge is worth treating as an unreviewed bug report rather than as sloppy prose.

### Q1. What replaces the DTO tree in `architecture.md`? — **RESOLVED (2026-08-28)**

Delete it. The generated `reference/spec-attributes.md` already lists every attribute with
its "Allowed in" containment relationships and parameters, and cannot rot. `architecture.md`
should link there instead of maintaining a parallel tree by hand.

### Q2. Should object-valued augmenter settings be documented as config? — **RESOLVED (2026-08-28)**

No. Config is **a constructor parameter that is not object typed** — ctor params are the
public API by convention; factories and resolvers are collaborators. Implemented as
`DocGenerator::configurableParameters()`, used by both reference generators. This dropped
`inheritance.attributeFactory`, `types.typeResolver`, `docblocks.parser` and four classic
`*.generator` entries, and `reference/augmenters.md` now matches `-D` exactly.

Interim solution — see follow-up PR 1.

### Not doing: `generator.ignoreOtherAttributes` has no documented home

Dropped 2026-08-28 — classic-only by construction (`Generator::getDefaultConfig()` and
`Analysers/AttributeAnnotationFactory`, neither used by the spec pipeline), so spec `-D`
correctly never reports it and the flag has no meaning there. Residual gap accepted: a
classic user running `-D` sees a key that no reference page explains. Not worth fixing for
a pipeline removed in v8.

### Not doing: `Operation::$operationId` is documented `@var string` but treated as nullable

Dropped 2026-08-28 — classic-only, and classic is removed in v8, so it is not worth the
churn. The rector skip for `IfToNullCoalescingAssignRector` on
`src/Processors/OperationId.php` therefore stays permanently; `rector.php` carries an
inline comment explaining why.

Note the systemic cause is still live and worth remembering: `composer.lock` is gitignored,
so CI always resolves the latest dependencies. A new rector or cs-fixer release can turn
every branch's `code-style` job red with no change to the repo — which is how this
surfaced (see PR #2135).

### PR 28 — `HybridBridge` converts a webhook's operation twice — **done, #2170**

The duplicate was the harmless half. The unguarded `Annotations\Operation` branch also
collected operations nested in a `PathItem`, and those carry no path of their own — it
belongs to the `PathItem` — so it produced them with `path` unset and `compilePaths()` dropped
them. Classic emitted the operation; hybrid emitted `/nested: []`. This entry called the branch
"benign today", which held only for the webhook copy.

**The fix this entry proposed would have been wrong.** It suggested one guard matching the
neighbouring branches. On its own that removes the duplicate and leaves the empty path item,
because `convertPathItem()` never carried operations across. The entry was right to flag that
as the thing to check first — the answer turned out to be that the flat branch was the *only*
route for those operations, and a broken one.

What shipped instead: `collectPathItem()` takes a `PathItem`'s operations across with its
path, as `convertWebhook()` already did for webhooks, both sharing a `nestedOperations()`
traversal. Only then is the `is('nested')` guard safe on the flat branch.

`HybridBridgeTest` compares hybrid against classic for both shapes. That comparison did not
exist anywhere — `ScratchTest` runs classic and spec only — which is the systemic gap, now
[PR 35](#pr-35--scratchtest-never-runs-hybrid-and-14-fixtures-disagree-when-it-does).

### PR 32 — nothing lists the extension points that ship — **done, #2169**

`ExtensionPointGenerator` produces `reference/extension-points.md` from the live defaults —
`AttributeFactory::getTranslators()` and `Resolver::withResolvers()` — the same discovery
`AugmenterGenerator` uses on `Builder::getAugmenters()`. Walking the defaults rather than a
directory answers the open-set question the entry raised: the page lists what actually ships,
in run order, and cannot drift.

The three questions the entry said to settle first, as resolved:

- **One page, not sections.** `guide/spec-attributes.md` and `reference/spec-attributes.md`
  were the precedent — a how-to paired with a generated list under the same name.
- **Augmenters and compilers are linked, not repeated.** Augmenters already had a generated
  page; the compiler table in `reference/architecture.md` is hand-written and verified by
  `DocsAccuracyTest::testCompilerTableMatchesDocs()`, so generating it a second time would
  have duplicated detail and bought nothing.
- **No `getResolvers()` was added.** `Resolver::withResolvers()` already hands the list to a
  callable, so the generator captures it there rather than widening `src/` for a docs change.
  The asymmetry with `AttributeFactory::getTranslators()` remains.

Two things the work turned up. The site build catches a dead *page* link but not a dead
*fragment*, which is how `#property-spec-only` survived in `spec-attributes.md` after #2162
corrected the page half of the same link — the real slug is `oa-property-spec-only`. And a
generated reference makes a hand-written mention of the same fact a drift risk: the guide's
description of `OptionalPropertyAttributeTranslator` became a link once the reference carried
it.

### PR 7 — augmenter configuration is documented on two pages — **done, #2166**

"Configuring augmenters" deleted from `reference/architecture.md`. `reference/builder.md`
already showed the same four operations plus `PathFilter`, and `withAugmenters()` is a
`Builder` method, so the architecture page keeps the phases and how to write an augmenter
while the builder page keeps the wiring — the split #2130 applied to the resolver.

"Declaring configuration" had pointed at the deleted section with a link that was already
loose: it said `#[Config]` makes a parameter settable via `-D`/`-c`, but pointed at a block
showing the programmatic form. It now names both routes.

### PR 8 — remaining spec test gaps — **done, #2137 + #2150 + #2162 + the null rule**

Closed in four parts. #2137 took `ComponentIndex`, slot-target validation and the attributes
nothing was compiling. #2150 settled the narrow half of the null question — a `mixed` property
defaults to `Undefined::UNDEFINED`, because `null` is a legal value there and so cannot also
mean "not set" — and `UndefinedDefaultsTest` pins it. #2162 ported the half of classic's
`ValidateRelationsTest` that transfers; the bidirectional half has no spec analogue, since
spec declares nesting once and the two halves cannot disagree.

**The last item turned out not to be a decision.** It was framed as "should a *nullable*
property also avoid a `null` default", by analogy with classic's
`AnnotationPropertiesDefinedTest`. Measured, that would have meant changing 574 constructor
parameters against 26 already using the sentinel — and the wrong 574, because the real rule is
narrower and the code already followed it:

> A field an augmenter can infer defaults to `Undefined::UNDEFINED`, so that an explicit
> `null` can suppress the inference. Everything else keeps `null`.

`Undefined::isDefault()` is true only for the sentinel, never for `null`, so
`if (!Undefined::isDefault($schema->description)) return;` means `description: null` is an
explicit "no description" and the docblock is left alone. That is classic's "the annotation
always wins", preserved. The 26 exceptions are exactly `summary` and `description` on the
operations, parameters and schemas — the fields `Docblocks` and `EnumDescriptions` fill.

Worth keeping: the capability was real but neither documented nor tested, and the failure mode
is silent in both directions. Guard a field that still defaults to `null` and the guard is true
for every attribute, so the inference never runs; widen `isDefault()` to accept `null` and
suppression stops working. `DocblocksTest` now pins both halves and `dev/pipeline.md` states
the rule.

Deliberately not done: direct tests for `DefaultAttributeTranslator`,
`OptionalPropertyAttributeTranslator` and `SourceLocation`. Both translators run on every spec
build and report 100% line coverage through it, and all three are small enough that a unit test
would restate the implementation rather than pin behaviour.

### PR 15 — make `ScratchTest` log expectations mode-aware — **done, #2160**

Keys gained an optional `-{mode}` suffix; `{fixture}-{version}` still applies to every mode,
and both contribute when both are present. The three tolerated diagnostics — mutualTLS on
3.0, `const` on 3.0, and the orphaned tag parent — became expectations, and `$ignoredLogs`
went with them.

Promoting the `const` one failed immediately: `OpenApi30Compiler::validate()` called
`validateSchemas()` directly as well as through `parent::validate()`, so every 3.0 schema
diagnostic was reported twice. `CompilerTest::testValidation` asserts with `assertContains`,
which duplicates pass, so nothing was watching. The entry's premise proving itself —
tolerating a diagnostic asserts nothing, including that it fires once.

The entry's second question, whether other fixtures provoke diagnostics the logger had been
swallowing, is answered: since #2148 made `ExpectsLogEntries` strict an undeclared entry
already fails, so nothing is being swallowed. The debug `echo` naming fixtures without a
`-spec.php` counterpart went at the same time; what the two remaining ones mean is recorded
in PR 20.

### PR 27 — sibling merge depends on declaration order, and loses attributes silently — **done, #2159**

Resolved with the largest of the three options the entry listed: `resolveNesting()` now defers
an attribute while another pending sibling names its type as a merge target, so chains resolve
inner-to-outer whichever way they are declared. The documented ordering rule the entry
proposed as the cheap first fix was not needed as a result.

Mutual merge targets cannot be ordered that way and fall back to declaration order rather than
deadlocking; a fixture with two mutually-targeting attachables pins that fallback. The flat
form stays limited to unambiguous stacks — a `MediaType` next to both a `Response` and a
`RequestBody` fails with a deterministic `Ambiguous merge` error instead of depending on
order.

Worth keeping from the original entry, because the misreading is easy and cost a round trip:
**bubbling is not a bug.** `pipeline.md` states it plainly — "If a level has no containers at
all, unmatched attributes pass through to the level above" — and the
`!in_array($attribute, $outer, true)` exemption in `AttributeFactory::fromReflector()` is what
implements it. Translators rely on it to inject attributes upward.

Why it stayed hidden also held up: every fixture built these nested types as constructor
arguments, sidestepping sibling merge entirely. The `Response`, `RequestBody` and `Encoding`
scratch fixtures now declare part of their trees as stacked siblings, one container-first, so
the path is covered rather than avoided.

### PR 3 — keep derivable documentation in sync automatically — **done, #2158**

`DocsAccuracyTest` verifies five hand-written documentation claims against the codebase by
reflection and command output. `composer docs:check` runs it.

| Documented fact | Where | Verified against |
|---|---|---|
| `openapi -h` output | `guide/generating-openapi-documents.md` | actual CLI output |
| root / conditionally-root / never-root lists | `dev/pipeline.md` | `isRoot()` implementations |
| compiler ↔ version table | `reference/architecture.md` | `CompilerInterface` implementations |
| `Result` method listing | `reference/builder.md` | `Result` public methods |
| no `requestBody` on Get/Head/Options/Trace | `guide/spec-attributes.md` | constructor parameters |

The `-D` default config candidate had no block in the docs to verify — skipped. The
classic-processor → spec-augmenter mapping table and classic-vs-spec behaviour claims remain
human-review only (see Q3).

Already covered by other mechanisms and not duplicated: augmenter phase/order list
(`reference/augmenters.md` is generated), doc snippets (`DocSnippetsTest`), example specs
(`ExamplesTest`), generated reference pages (`composer docs:gen`).

PR 6 (generate code fragments) stays conditional on this — `DocsAccuracyTest` is the
verify-first approach PR 6's own entry suggested weighing before generating.

### PR 14 — apply the documentation rules to docblocks — **done, #2153 and #2156**

`docs/dev/writing-docs.md` was written for markdown pages, but most of what it says is about
precision and economy, and those apply to docblocks unchanged. Docblocks in `src/Spec/`,
`src/Augmenter/`, `src/Processors/` and `src/Annotations/` are additionally *spliced into the
generated reference pages*, so an imprecise one ships to users as published documentation.

Every defect the rules exist to catch has already turned up in a docblock during this work:

- **Stale references** — `AttributeFactory` cited `ExpandHierarchy`, a class that no longer
  exists, and `contains()` for a method actually named `contained()`
- **Inverted precision** — `AttributeInterface::contained()` described the slot as living on
  the declaring attribute; it lives on the parent
- **Self-description** — a fixture docblock explained that it "doubles as a worked example …
  the translation people usually have to guess at"
- **Filler** — "Convenience empty/noop base imlementation", typo included

Rules that transfer directly: do not claim what you have not verified; do not cite line
numbers; detail in exactly one place; no volatile values; no open-ended enumerations; do not
describe the thing you are writing in. Rules that do not: site-absolute links, the
generated-page conventions.

**The scoping half is done in #2153**, which settled the second question the way this entry
predicted: one document. `writing-docs.md` now names docblocks, pull request descriptions and
commit messages as surfaces its rules cover, and lists the three page-only sections.

What is left is the sweep itself — and `src/Spec/` is where to spend it, since those docblocks
become the Spec Attributes reference and so ship to users.

**Swept 2026-09-03, and it found almost nothing — which is the useful result.** The four
defects above were all fixed by intervening work, so the entry's motivating evidence had gone
stale. Running the checklist's mechanical checks over all 61 files in `src/Spec/`:

| Check | Result |
| --- | --- |
| line-number citations | none |
| marketing filler | none |
| self-description | none |
| stale class references | none |
| `@param` descriptions restating the parameter name | 0 of ~400 |
| class summaries restating the class name | 2 |

#2156 fixes the two — `Server` ("Represents a Server.") and `ServerVariable` — both of which
shipped to `reference/spec-attributes.md`.

One near-miss worth recording: `Tag`'s summary reads oddly ("Adds metadata to a single tag
used by the Operation Object") and looks like a candidate, but it is near-verbatim from the
OpenAPI spec's own Tag Object description. Canonical wording, left alone. Check the spec text
before rewording anything that came from it.

The checks above are cheap to re-run and came back clean, so a future sweep of `src/Spec/`
needs a reason beyond routine. `src/Augmenter/`, `src/Processors/` and `src/Annotations/` were
**not** swept — the entry named them, and only `src/Spec/` reaches users through a generated
page, so the others were left.

### PR 10 — extract the pipeline-agnostic half of `OpenApiTestCase` into concerns — **done, #2147 + #2148 + #2157**

`tests/OpenApiTestCase.php` was 332 lines with 52 test classes extending it, but most of it
was classic. The goal: extract the pipeline-agnostic members into `tests/Concerns/` traits so
removing `OpenApiTestCase` becomes a straight deletion when classic goes, and so new spec
tests use the traits directly rather than inheriting classic plumbing.

Also settled which of three overlapping diagnostic-assertion mechanisms to standardise on:
the PSR logger via `ExpectsLogEntries`, since `CollectingLogger` forwards to it and
`Result::warnings()` is just a view over the same stream.

Delivered across three PRs:

- **#2147** — extracted `AssertsSpecEquals`, retired `AssertsBuilderResult`
- **#2148** — added `ExpectsLogEntries` trait (order-independent, `#[Before]`/`#[After]`
  hooks, no `setUp`/`tearDown` coupling)
- **#2157** — extracted `UsesFixtures`, composed all three traits into `OpenApiTestCase`,
  migrated all 15 test files from the old logger API (`assertOpenApiLogEntryContains`,
  `ignoreLogEntries`, `getTrackingLogger`) to `ExpectsLogEntries` (`expectLogEntry`,
  `allowLogEntry`, `trackingLogger`), removed dead `initializeProcessors()`, moved
  `SourceScannerTest` off the base class to plain `TestCase`

What remains on `OpenApiTestCase` is classic-only: `getContext`, `getAnalyzer`,
`processorPipeline`, `analysisFromFixtures`, `annotationsFromDocBlockParser`,
`createOpenApiWithInfo`, `allAnnotationClasses`, `allAttributeClasses`, `getTypeResolver`,
`getTypeResolvers`. All deletable with classic in v8.

One subtlety worth recording: PHP attributes are not inherited by overrides. The old logger
silently dropped "Analysing source:" and "JetBrains" debug messages; `ExpectsLogEntries`
records everything. The fix is a separate `#[Before]` method (`allowClassicDebugNoise`) on
`OpenApiTestCase` rather than overriding the trait's `resetLogEntryExpectations` — overriding
loses the `#[Before]` attribute and PHPUnit stops calling it.

The event-subsystem approach on `origin/expexts-logger-contains` was investigated and
rejected. Full plan and measurements:
[testcase-concerns/README.md](testcase-concerns/README.md).