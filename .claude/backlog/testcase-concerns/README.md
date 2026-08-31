# `OpenApiTestCase` concerns

The full write-up behind PR 10 in the [backlog](../../backlog.md).

### PR 10 — extract the pipeline-agnostic half of `OpenApiTestCase` into concerns

`tests/OpenApiTestCase.php` is 332 lines and 52 test classes extend it. Most of it is
classic — `getAnalyzer()`, `initializeProcessors()`, `processorPipeline()`,
`analysisFromFixtures()`, `allAnnotationClasses()`, `allAttributeClasses()`,
`annotationsFromDocBlockParser()`, `getContext()`, `createOpenApiWithInfo()` — and can be
deleted with classic in v8.

But some of it is not classic at all, and today the only way to reach it is by extending a
classic-flavoured base class.

The spec side has already mostly escaped: all sixteen augmenter tests, plus
`SlotMapConsistencyTest`, `AssemblerTest` and `CompilerTest`, extend plain `TestCase` and
compose traits from `tests/Concerns/`. **Only two hold out**, and between them they need
five members:

| Test | Needs |
|---|---|
| `ScratchTest` | `getTrackingLogger()`, `assertOpenApiLogEntryContains()`, `assertSpecEquals()` |
| `BuilderTest` | the same three, plus `getAnalyzer()` and `getTypeResolver()` |

So the extraction is small and well-bounded:

- **`TracksLogEntries`** — `getTrackingLogger()` + `assertOpenApiLogEntryContains()`, plus the
  `setUp`/`tearDown` that manage the captured entries. Overlaps `AssertsBuilderResult`; see
  below, the overlap now has an answer.
- **`AssertsSpecEquals`** — `assertSpecEquals()`. Overlaps `AssertsSchemaStructure`, which
  compares `allOf` refs and property names order-independently. Same question.
- **`ProvidesTypeResolvers`** — `getTypeResolver()` / `getTypeResolvers()`, used as a data
  provider by both pipelines.
- **`UsesFixtures`** — `fixture()` / `fixtures()`. Path helpers, wanted everywhere.

`getAnalyzer()` is classic and should stay behind; `BuilderTest` needs it only for the
classic-mode cases.

Do the overlap review as part of this rather than after: `tests/Concerns/` has grown to six
traits and at least two of them cover ground `OpenApiTestCase` also covers. Extracting
without reconciling would just move the duplication.

Once the two holdouts are converted, `OpenApiTestCase` is classic-only and its removal is a
straight deletion when classic goes, rather than an untangling.

#### Which log mechanism wins

There are now three overlapping ways to declare what a test expects from diagnostics:

| Mechanism | Channel | Semantics |
|---|---|---|
| `AssertsBuilderResult::expectResultWarnings()` | `Result::warnings()` | tolerance — may appear |
| `assertOpenApiLogEntryContains()` | PSR logger | expectation — must appear, in order |
| `ignoreLogEntries()` | PSR logger | tolerance — may appear |

**Consolidate on the logger.** `Utils\CollectingLogger` both records to `entries()` *and*
forwards to its delegate, and `CompilerInterface::validate()` returns those same entries. So
since #2138, `Result::warnings()` and the PSR logger are two views of one
stream, not two sources. The logger is the general capture point; `Result::warnings()` is a
convenience over it.

The cost of keeping both is already visible: `ExamplesTest` declares the same two compiler
warnings twice, once per mechanism, because they arrive on both channels.

Prior art on a stale branch: `origin/expexts-logger-contains` has an `ExpectsLoggerContains`
trait plus a PHPUnit event subscriber that moves assertions from `tearDown()` to
`afterTestMethodCalled`. That timing change is the interesting part and worth keeping.
Treat it as reference only — it predates `doBuildSpec($hybrid)` so it is based on an old
master, and the implementation is not known to be settled.

Do this before or with the extraction, not after: extracting `TracksLogEntries` while the
overlap stands would just relocate it.
