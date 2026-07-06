# Testing, Documentation, and CI

## Testing Strategy

### Existing tests are the BC contract

Any fixture that produces a valid spec today must produce the same spec after
changes. The test suite IS the backward compatibility proof.

### Phase-specific testing

**Infrastructure:** Zero test changes needed. Additional tests for new infrastructure
(`SourceLocation`, compiler output comparison).

**Phase 1:** Parallel fixture set for new attributes. Where both classic and new
fixtures exist for the same API, generated specs must be byte-for-byte identical.
Enricher testing: unit tests per enricher (input attribute + stack → expected DTOs),
plus integration tests that verify enricher-produced output matches hand-written
equivalent attributes.

**Compiler validation:** Snapshot tests, round-trip tests (classic path → new
compiler must match), and cross-version tests (verify known differences).

**Converter testing (Phase 1):** Each converter is tested by round-tripping: create
a new DTO, convert to classic annotation, verify the annotation produces the same
spec output as hand-written classic equivalent. This proves the bridge is lossless.
Converters are throwaway code (deleted in v7) but critical for Phase 1 correctness.

### Spec validation in CI

Currently manual via `composer spectral` / `composer redocly`. Move to CI:
- Validates generated YAML against OpenAPI spec rules
- Catches spec-invalid output that PHP tests wouldn't flag
- Add compiler-comparison job (proves new compiler matches old output)

## Documentation Strategy

### Parallel docs

Users on classic path need stable docs. Users adopting new path need both new way
and migration guide. VitePress version switcher for parallel sidebars.

### Structure

- Shared concepts (installation, what is swagger-php)
- Classic section (classic attributes, processors, extending)
- New section (new declarations, compilers, extending)
- Migration guide (equivalence tables, processor migration, extension migration)
- Auto-generated reference covers both attribute sets

### Timeline

| When | What |
|------|------|
| v6.x (early) | Add docs for new infrastructure (`SourceLocation`, `SpecCompilerInterface`, `#[AllowedParents]`) |
| v6.x (Phase 1) | Draft new-attribute docs + adoption guide |
| v6.x (Phase 2) | Full parallel docs, deprecation notices on classic |
| v7.0 | Archive classic docs (accessible, not default) |

## CI Additions

| Phase | Addition |
|-------|----------|
| v6.x (early) | Spec validation workflow (spectral + redocly) |
| v6.x (Phase 1) | Converter round-trip tests |
| v6.x (Phase 2) | Compiler comparison job (new compiler output == old `jsonSerialize()`) |
| v6.x (Phase 2) | Mixed-mode tests (old + new attrs in same project) |
