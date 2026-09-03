

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
