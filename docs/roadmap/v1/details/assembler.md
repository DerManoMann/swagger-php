# Assembler — Detail

## Role

The assembler replaces `merge()` + `$_nested` + `matchNested()` + the factory's
parent-child detection. Same job — build a document tree from annotations — but
with rules externalized and no side effects during construction.

## Comparison with current approach

| | Current | Assembler (new) |
|--|--|--|
| Where are nesting rules? | `$_nested` static array on each annotation class | External slot map in the assembler |
| When does tree building happen? | During construction (`merge()` in constructor) | Separate pass after all DTOs are instantiated |
| Side effects during construction? | Yes (context registration, merge) | None — pure value assignment |
| Ambiguity resolution | `matchNested()` positional matching | Explicit nesting in constructor args |
| Extension point | Override `$_nested` on subclass | `#[AllowedParents]` + slot map entry |

## Input shapes

DTOs reach the assembler in two forms:

### Stacked (multiple attrs on same target)

```php
#[Property]
#[Schema(minLength: 3, maxLength: 50)]
public string $name;
```

Assembler pairs them: one structural attr + one `Schema` on the same PHP target →
`Schema` belongs to the structural attr. Unambiguous by convention.

### Nested (in constructor args)

```php
#[Get('/users/{id}')]
#[PathParam('id', schema: new Schema(type: 'integer', minimum: 1))]
#[Returns(User::class)]
```

Already in the right place. PHP assigned `new Schema(...)` to the `$schema`
property during construction — the assembler doesn't touch these at all.

## Disambiguation rule

When multiple structural attrs exist on the same target, stacked `Schema` is
ambiguous:

```php
// Ambiguous — does Schema belong to PathParam or the operation?
#[Get('/users/{id}')]
#[PathParam('id')]
#[Schema(type: 'integer', minimum: 1)]
```

Resolution: **nest explicitly**. The assembler rejects ambiguous stacking with a
diagnostic. Users resolve by nesting in the constructor arg:

```php
#[PathParam('id', schema: new Schema(type: 'integer', minimum: 1))]
```

This is a deliberate design choice — explicit over magic. The current `matchNested()`
positional matching is one of the hardest patterns to understand.

## Precedent in other languages

Every major OpenAPI tooling ecosystem uses explicit nesting for structural
relationships:

- **Java** (swagger-core, springdoc): always nested —
  `@Parameter(schema = @Schema(type = "integer", minimum = "1"))`
- **C#** (Swashbuckle): stacking is fine for validation attrs on a single field
  (unambiguous target), structural relationships are explicit
- **Python** (FastAPI): constraints inline on the parameter definition —
  `Path(ge=1, description="...")`

The common pattern: stacking is natural when there's exactly one target (constraints
on a property/field). Structural relationships (which param, which response) are
always explicit. No ecosystem has a `matchNested`-style proximity rule.

swagger-php's stacking aligns with C# for the simple case (`#[Property]` +
`#[Schema]` on a PHP property). The ambiguous case follows Java/Python — nest
explicitly. No magic needed.

## Slot map

The assembler knows which DTO types slot into which parent properties:

```php
class Assembler
{
    private array $slotMap = [
        Operation::class => [
            Parameter::class => 'parameters',
            Response::class  => 'responses',
            Security::class  => 'security',
            Tag::class       => 'tags',
        ],
        Schema::class => [
            Schema::class => 'properties', // nested schemas
        ],
        // ...
    ];
}
```

No `PathItem` entry — the assembler returns `Operation` directly (each carries
`$path` + `$method`). The compiler groups operations by path into `PathItem`
objects when producing output. `PathItem` is an output structure, not a root DTO.
```

Custom `Attachable`s don't need a slot map entry — they always go into the
`attachables` collection on their allowed parent(s).

## Assembly pass

The assembler processes one attribute stack at a time and returns root-level DTOs.
It doesn't know about `Specification` — just resolves a stack into structured output.

```php
class Assembler
{
    /**
     * @return object[] Root-level DTOs (Operation, Schema, Response, etc.)
     */
    public function assemble(AttributeStack $stack): array
    {
        $structural = $this->findStructural($stack->dtos);
        $schemas = $this->findSchemas($stack->dtos);
        $attachables = $this->findAttachables($stack->dtos);

        // Pair stacked schemas with their structural attr
        $this->pairSchemas($structural, $schemas);

        // Nested DTOs (via constructor args) are already in place — skip them

        // Attach custom Attachables to structural attr
        foreach ($attachables as $attachable) {
            $structural->attachables[] = $attachable;
        }

        return [$structural]; // ready for $spec->add()
    }
}
```

The caller adds results to `Specification`:

```php
foreach ($factory->discover($file) as $stack) {
    $dtos = $assembler->assemble($stack);
    foreach ($dtos as $dto) {
        $spec->add($dto);  // identity (path, name) is on the DTO
    }
}
```

## Relationship to processors

The assembler runs before processors. It produces root-level DTOs that get added
to `Specification`. Processors then enrich the spec (type inference, `$ref`
resolution, defaults, validation).

```
Factory → attribute stacks + SourceLocation + Reflector
    ↓
Assembler → root-level DTOs
    ↓
Specification::add() → organized in typed containers
    ↓
Processors → enrich via Specification (types inferred, refs resolved)
    ↓
Compiler → walks Specification, produces version-specific JSON/YAML
```

## Why not just keep `$_nested`?

- Rules scattered across 30+ classes → hard to understand, impossible to override
- Positional matching is implicit magic that surprises users
- Subclasses must replicate parent's `$_nested` or break
- Custom attributes can't participate without editing core
- Testing nesting rules requires instantiating full annotation trees

The assembler centralizes this into one testable, overridable component.
