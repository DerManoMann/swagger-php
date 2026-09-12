# Backlog

Decisions taken, questions still open, and follow-up work — across the docs, the tooling,
the test suite and the spec pipeline.

Facts about the codebase belong in `docs/dev/` (`pipeline.md`, `testing.md`,
`docs-toolchain.md`, `writing-docs.md`), terminology in `CONTEXT.md`, and the pre-PR
checklist in `CONTRIBUTING.md`. Those are the source; add to them, not here. What stays
here is the reasoning behind decisions — including the ones not to do something, which
the code and the docs do not record.

## The files

| File | Holds |
|---|---|
| [planned.md](planned.md) | entries with agreed direction, not started — including parked ones, with their revisit trigger |
| [active.md](active.md) | entries in progress or in review, one per open branch/PR |
| [questions.md](questions.md) | open design questions, each OPEN / PARKED (trigger) / RESOLVED |
| [archive.md](archive.md) | finished entries — done, closed, not doing — and the merge ledger |

Supporting material lives in a folder per topic, named for the topic rather than the
entry number so it survives renumbering: [benchmarks/](benchmarks/README.md),
[performance/](performance/README.md), [testcase-concerns/](testcase-concerns/README.md),
[nelmio-poc/](nelmio-poc/README.md), [spec-3.2/](spec-3.2/README.md),
[docblock-types/](docblock-types/README.md).

`chore/backlog` stays checked out in a worktree at `.claude/worktrees/chore+backlog` rather
than being created and removed per change. It changes often enough to earn the slot, and
keeping it there means it can be read or reviewed at any point without checking anything out.

## Flow

1. A new entry gets the next PR/Q number in `planned.md`. Numbers are never reused.
2. Search open GitHub issues before writing an entry — cite any match. (PR 39 reasoned
   its way to a conclusion that was sitting in the tracker as #1994 the whole time.)
3. When work starts, the entry moves to `active.md`, gaining branch and PR links.
4. When it merges or closes, it moves to `archive.md` with the outcome recorded
   (`done #NNNN` / `closed, not doing` / the reasoning for either) and one line added
   to the ledger table. An entry moves only when nothing in it is still work.
5. An entry needing scripts, measurements, or more than ~2 screens gets a topic folder
   with a `README.md`; the entry keeps a one-paragraph summary plus the link.
6. Questions live in `questions.md` until RESOLVED, then move to `archive.md`. A PARKED
   question names the trigger that revives it.
7. Cross-references use entry numbers (`PR 30`, `Q5`) — they survive moves; links point
   at the file the entry currently lives in.

## Where this stands

**Two known bugs**, both spec-side, both small: PR 42 (a duplicate `$ref`, **#2185**) and
PR 43 (reversed trait property order, **#2186**). Both are fixed and in review — see
[active.md](active.md). The rest is improvement work and Q5.

- **#2183** (hybrid comparison in `ExamplesTest`/`DocSnippetsTest`) and **#2184**
  (PR 30, removing the classic processors from hybrid) are merged.
- Four entries are in review at once, which is more than this file usually holds — see
  [active.md](active.md).
- **6.8.1** is the latest release, tagged at `08c8a2d2` (2026-09-09); the release-notes
  story behind that commit is in [archive.md](archive.md).
- **Q3** is answered (2026-09-11): five real classic-vs-spec output differences, and the
  two doc claims that could not be acted on were each hiding one of the bugs above.
- **Q5** is the live design question — it governs `Response` in shipped code, and
  answering it unparks PR 22.
- **PR 12** is ongoing by design — the next fixture comes from whatever the next
  coverage run shows thin.

pcov is installed locally and CI runs `--coverage-text`, so coverage numbers are real
rather than inferred. phpstan covers `tools/` since #2141.