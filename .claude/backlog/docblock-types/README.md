# Docblock types

Reproduction for PR 38 in the [backlog](../archive.md) — a generic docblock resolves to
nothing, in classic and spec alike.

```shell
XDEBUG_MODE=off php .claude/backlog/docblock-types/reftypes.php
```

`reftypes.php` builds `Fixture.php` (classic attributes) and `Fixture-spec.php` (spec
attributes) in all three modes and reports what each of three properties compiled to. The
properties differ only in their docblock; the PHP type is the same on all three. On
`origin/master` at `bfa6b0ce`:

```
property    classic         hybrid          spec
----------  --------------  --------------  --------------
native      resolves        resolves        resolves
generic     {} nothing      {} nothing      {} nothing
docblock    resolves        resolves        resolves
```

Fixed in #2173; every cell reads `resolves` from that commit on. Kept so the case can be
re-checked rather than trusted — the failure was silent, and a regression would be too.

The middle row failed in classic only because `TypeInfoTypeResolver` is the default. Pass the
legacy resolver and classic passes on `origin/master` too, which is why the script names modes
rather than resolvers: it reports what a user gets, not where the defect lives.

## The second finding is not in this table

PR 38's spec-only leading-backslash bug needs a class in the **global namespace**, and both
fixtures here are namespaced — deliberately, because that is what real code looks like and it
keeps the main table about the generic docblock alone.

To see it, put a class in the global namespace and give a property a short-name docblock:

```php
#[OA\Schema(schema: 'Target')]
class GTarget { #[OA\Property] public string $name; }

#[OA\Schema(schema: 'Holder')]
class GHolder
{
    /** @var GTarget */
    #[OA\Property] public GTarget $short;   // spec: $ref: \GTarget — unresolved

    /** @var \GTarget */
    #[OA\Property] public GTarget $fq;      // resolves

    #[OA\Property] public GTarget $native;  // resolves
}
```

Classic resolves the first one. Inside a namespace, so does spec.

## Notes

Neither script is a test and nothing runs them automatically. They exist so the claims can be
re-checked rather than trusted, and so a fix has something to prove itself against.

The fixtures are not in composer's autoload map, so `reftypes.php` registers a loader for them —
both pipelines reflect on the classes, so they have to be loadable rather than merely scanned.
