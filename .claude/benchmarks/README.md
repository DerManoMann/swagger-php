# Benchmarks

Throwaway scripts behind the numbers in [PR 16 and PR 17](../llm-docs-backlog.md). They are
not tests and nothing runs them automatically — they exist so the measurements can be
re-run rather than trusted.

Run any of them directly:

```shell
XDEBUG_MODE=off php -d memory_limit=4G .claude/benchmarks/tokencost.php
```

Fixtures are generated into the system temp directory on first run and reused after that.
Delete `sp-*` there to force a rebuild.

## Runs anywhere

| Script | Measures |
| --- | --- |
| `modebench.php` | classic vs hybrid on identical sources, same document out |
| `cleanupiso.php` | classic `CleanUnusedComponents` timed on its own, across sizes |
| `tokencost.php` | what reading and tokenizing costs per file |

## Needs the resolver

Merged in #2130, so these run on `master`. They exit with a message on any branch predating
it.

| Script | Measures |
| --- | --- |
| `resolverbench.php` | scanning a directory vs seeding with controller reflectors |
| `noisebench.php` | the same, with the API surface fixed and non-API code growing |
| `phasebench.php` | scan / tokenize+collect / resolve / augment, split by phase |
| `itercount.php` | how many iterations `Resolver::resolve()` takes, and what the sweeps cost |
| `resolverdebug.php` | schemas reached from a directory vs from one reflector |
| `resolvertrace.php` | which FQCNs the resolver is asked for, and which it resolves |

## Reading the results

Two traps cost real time while these were being written, both worth knowing before trusting
a number:

- **`TokenScanner` memoises per file.** A warmed scanner measures zero. `tokencost.php`
  builds a fresh one per repetition for this reason.
- **Deltas between two large timings are noise.** Measuring cleanup as
  *generate-with* minus *generate-without* produced negative values at large sizes. Time the
  component directly, as `cleanupiso.php` does.

`phasebench.php`'s split is approximate: collect cost is very uneven between controllers and
models, and autoloading lands in whichever phase touches a class first.
