# Open questions

Design questions still needing an answer. A PARKED question names the trigger that
revives it; a RESOLVED question moves to [archive.md](archive.md) with its reasoning.
Flow rules: [README.md](README.md).

**Q3. Are the classic-vs-spec output differences real?** — PARKED (2026-08-28), now
answerable. Trigger: before spec becomes the default (v7).
Leave the hedged claims for now; spec mode is still optional/beta.

The evidence turns out to exist already and to be cheap to read: `ScratchTest` compares all
three modes against one shared expected document unless a `-spec.yaml` override exists, so
the five overrides in `tests/Fixtures/Scratch/` **are** the list of real divergences —
`Auth`, `DuplicateRef`, `MergeTraitsExtended`, `NullRef` (3.1/3.2 only) and
`MultiTypeProperty` (type-info resolver only). The other 34 families assert byte-identical
output. Reading those five diffs settles this without a survey; see PR 26 in
[archive.md](archive.md).

**Q4. How much of `docs/adr/` is actually an ADR?** — PARKED (2026-08-28).
Trigger: classic removed (v8).
Written as a reference/experiment to see if the format was useful. Both files describe the
*classic* pipeline only, so they stay as-is and serve as reference until classic is removed.
Condensing them into LLM context is acceptable in principle, just not now.

**Q5. How does a reusable, `$ref`-able `PathItem` or `MediaType` get a component identity?** —
OPEN (reopened 2026-09-03; opened 2026-09-01, parked 2026-09-02)
Parked with PR 22 on the grounds that only that entry's Phase 4 waited on it. That turned out
to be wrong: the same question governs `Response` in shipped code and the four component-key
types' `isRoot()` implementations — see below. It is now a live design question independent of
3.2. Answering it unparks PR 22.

`Parameter`/`Header`/`Link` solve this with a component-key constructor field (`parameter:`,
`header:`, `link:`) plus a conditional `isRoot()` that is true only when that key is set and
`ref` is not. `PathItem` has neither — it is unconditionally root, and its `path` is always a
resolved URL, never a component name, so nothing distinguishes "the shared metadata for
`/pets/{id}`" from "a reusable path-item template to `$ref` from elsewhere". `MediaType` is
worse: `$mediaType` is simultaneously the value (`'application/json'`) and, today, the only
thing that could serve as a lookup key — a named `components.mediaTypes` entry needs an
identity independent of the media-type string itself. Blocks Phase 4 of PR 22
(`Components.pathItems` / `Components.mediaTypes`). See the "Phase 4" section of
[spec-3.2/README.md](spec-3.2/README.md) for the two options sketched so far.

**No longer hypothetical, and no longer only about 3.2 (2026-09-03).** The shape Q5 describes
already ships in `Response`, and the four component-key types disagree with each other about
what it means. Found while adding response-key validation in #2154, which had to key off
position because the object could not be asked.

`Response::$response` is overloaded exactly the way `MediaType::$mediaType` is: it is the HTTP
status code when the response is nested in an operation, and the component name when the
response sits in the `responses` bucket. `isRoot()` cannot separate them — it only checks the
key is set. Two consequences, both verified:

- **A status-code response that fails to nest becomes a component named after the code.**
  Disable `Augmenter\Cleanup` and `components.responses.200` appears. **Classic does exactly
  the same** — `MergeIntoComponents` merges any non-nested annotation whose `$_parents`
  include `Components`. So the overload is inherited, and it originates in the document shape:
  the Response Object has no name of its own, its identity is always the key of the map holding
  it.
- **What spec changed is the discriminator, not the overload.** Classic recorded position as a
  fact and checked it (`_context->is('nested')`); spec infers rootness from field presence.
  That is the regression, and it is what a fix should restore.

Declaring both the key and `ref` then behaves four different ways:

| Declared on a class with key **and** `ref` | Result |
| --- | --- |
| `RequestBody(request: 'x', ref: …)` | `components.requestBodies.x = {$ref: …}`, accepted |
| `Response(response: 'x', ref: …)` | throws `Non-root attribute … remains after resolution` |
| `Parameter(parameter: 'x', ref: …)` | throws |
| `Link(link: 'x', ref: …)` | throws |

`RequestBody::isRoot()` is the only one without the `ref === null` clause, and
`docs/dev/pipeline.md` records that as a fact without a reason. It is tempting to call it the
odd one out and add the clause — but that is probably backwards:

- **`Response` needs the clause**, because key + `ref` is the *ordinary* pattern there:
  `responses: {200: {$ref: …}}` is `response: 200, ref: …`, which must nest rather than become
  a component. The clause is load-bearing precisely because the key doubles as a value.
- **`RequestBody` is right to omit it.** `$request` is documented as a component key and can
  never be a positional value, so key + `ref` can only mean "a component that aliases another",
  which is legal OpenAPI and what the code emits.
- **`Parameter` and `Link` have dedicated keys too** (`parameter`, `link`, distinct from
  `name` / `operationId`), so by that reasoning they should permit the alias as `RequestBody`
  does. They throw.

So three of the four are defensible alone and no two share a rule. The rule that would explain
all of them is **"does this key double as a value?"** — which is Q5's question, arriving in a
fourth place. Answering it settles, uniformly: whether key + `ref` is an alias or a
contradiction, what `PathItem` and `MediaType` need in order to be `$ref`-able, and whether
`Response` should ever have carried one field for two jobs.

The cheap piece independent of the answer — warn when a root `Response`'s key looks like a
status code — is **done in #2182**.