# Active

Entries in progress or in review — one per open branch or pull request. If this file
grows past a handful of entries, that is itself the signal. Flow rules:
[README.md](README.md).

### PR 30 — hybrid runs two classic processors for a mapping the bridge already knows — **in review, #2184**

Branch: `refactor/hybrid-drop-classic-processors`.

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

### hybrid comparison in the remaining test suites — **in review, #2183**

The tail of PR 35: hybrid ran in `ExamplesTest`, `DocSnippetsTest` and `CommandlineTest`
but was never compared against an expectation that would catch a deviation. **#2183**
makes `getSpecFilename()` and `DocSnippetsTest` prefer spec expectations for hybrid —
the precedence `ScratchTest` already uses — and lets `ExamplesTest` run spec sources in
hybrid mode (+58 tests). `CommandlineTest` was deliberately left out: it is a wrapper
around the same generator paths the other suites already compare.