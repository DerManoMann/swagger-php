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

**Both are now settled**, in #2147 and #2148 respectively — see the two sections below.

Do this before or with the extraction, not after: extracting `TracksLogEntries` while the
overlap stands would just relocate it.

#### The PHPUnit event subsystem is the wrong tool — RESOLVED (2026-09-01)

`origin/expexts-logger-contains` carried an `ExpectsLoggerContains` trait plus a PHPUnit
extension and event subscribers, moving the assertions from `tearDown()` to
`afterTestMethodCalled`. That branch can be **dropped**; #2148 supersedes it, and the
timing change this entry previously called "the interesting part worth keeping" turns out
not to need the event system at all.

Measured against PHPUnit 12.5.34, because none of it is documented clearly enough to
predict:

| Event | Fires without a hook method? | Assertion raised inside it |
|---|---|---|
| `Test\AfterTestMethodCalled` | **no** — only when `tearDown()`/`#[After]` exists | attributed correctly, test fails, exit 1 |
| `Test\Finished` | yes | **aborts the whole run** — "An error occurred inside PHPUnit", no results |

So the branch had picked the only event that attributes correctly, and it worked purely
because `OpenApiTestCase` happens to declare `tearDown()`. Any test class without a hook
method would have been silently skipped. Two further traps in that implementation: it gated
subscribers on `in_array(..., class_uses($testCase))`, which does not see traits inherited
from a base class, and it kept expectations in **static** properties, so a subclass
overriding `setUp()` without `parent::` would leak state across tests
(`tests/Utils/TypeMapperTest.php` does exactly that today).

**What replaced it.** A trait can declare `#[Before]`/`#[After]` itself, which PHPUnit
invokes independently of `setUp()`/`tearDown()`. Verified: fires per test and per data set,
assertions attribute to the right test, composes with a base class's own hooks (order:
trait `#[Before]` → base `setUp` → body → base `tearDown` → trait `#[After]`), survives a
missing `parent::` call, and a test whose only assertions come from the hook is not flagged
risky. That is documented, stable API present in both PHPUnit 11.5 and 12.x, so the
future-proofing question disappears rather than needing management.

Net: ~365 lines of extension + subscriber + `phpunit.xml` wiring became a trait with two
attributed methods.

#### Where this leaves the two holdouts

`ScratchTest` needs six things from `OpenApiTestCase`. Four are now covered:

| Needs | Status |
|---|---|
| `getTrackingLogger()`, `assertOpenApiLogEntryContains()`, `ignoreLogEntries()` | `ExpectsLogEntries` (#2148) |
| `assertSpecEquals()` | `AssertsSpecEquals` (#2147) |
| `fixture()` | still on `OpenApiTestCase` |
| `getTypeResolvers()` | still on `OpenApiTestCase` |

Everything else it uses (`discoverFixtures`, `matrixCombinations`, `matrixKey`,
`mostSpecific`, `phpVersion`, `versions`) already lives in `GeneratesTestMatrix`. So two
small extractions stand between `ScratchTest` and `extends TestCase`.

Deliberately **not** being done now: the decision is that the base class stays as-is, new
tests adopt `ExpectsLogEntries`, and the remaining dependencies get dropped at v8 along with
classic. Migrating existing tests earns little while `OpenApiTestCase` still has to exist.

Note `getTypeResolvers()` is classic-flavoured, which is a fair objection to extracting it
into a spec-side concern — the justification is that *mode-spanning* tests need it
(`ScratchTest` and `ExamplesTest` both run classic, hybrid and spec), not that spec tests do.

#### `ScratchTest`'s tolerated diagnostics

PR 15 wants `$expectedLogs` keyed by mode. `ExpectsLogEntries` is half that fix — it
supplies the honest `expectLogEntry()`/`allowLogEntry()` split and drops the FIFO ordering —
but it does **not** change the key, so the three diagnostics currently tolerated in
`$ignoredLogs` only become real assertions when the mode-aware key lands too. Worth doing
as one change rather than two.
