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
[`backlog/testcase-concerns/README.md`](testcase-concerns/README.md).
