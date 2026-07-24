# Spec Attributes Pipeline BETA Status

### Documentation

- Add docblocks to augmenter pipes describing their configuration options (for generated reference docs)

### Shortcut attributes

Re-evaluate support for convenience attributes that reduce boilerplate in common patterns:

- **`Items`** — shorthand for array item schema declaration; this probably should be extending `OA\Items` and get a dedicated `PipeInterface` augmenter.
- **`JsonContent`** / **`XmlContent`** — shorthand for wrapping a schema in a media type with the appropriate content type; a new `AttributeTranslatorInterface` should be implemented to handle the translation of these attributes.

## Future features

- Optional `OA\Property` if `OA\Schema` present and the default property name is used (empty `#[OA\Property])`)
- Adjust attribute parameter types to aid downstream projects?
- A `OA\Schema\Ref` attribute (with title/description 3.1.0+), $ref required attribute - extends `OA\Schema`
- Mixed hybrid example
- test to verify merges()/contains() consistency (with property type checks)
- attachable example/test
- docs-plan.md
