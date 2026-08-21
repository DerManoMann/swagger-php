# ADR-003: Unresolved Type Resolution

## Status

Proposed

## Context

When processing sources via reflection, not all referenced classes may be known upfront. A controller might reference a DTO class via `ref: Product::class`, but if that class wasn't in the scanned source set and has no OpenAPI annotations, it won't appear in the specification. The same applies to property/parameter type hints that reference model classes.

The initial approach (`AutoAnnotate` augmenter) attempted to solve this inside the augmenter pipeline. This caused several problems:

- **Pipeline ordering**: The augmenter runs after `Names` and `Types`, so it must re-invoke those augmenters on newly added schemas.
- **Transitive discovery**: Schemas added by the augmenter may reference further types, requiring their own type resolution and ref wiring.
- **Re-invocation fragility**: Running augmenters a second time works because they use `??=`, but this is an implicit contract, not an architectural guarantee.
- **Cleanup interference**: The `Cleanup` augmenter removes schemas that appear unreferenced, even if they were just added by the augmenter before `Types` could wire up refs.

The core issue: discovery of new types is an assembly-time concern, not an augmentation-time concern. Augmenters should operate on a complete specification.

## Decision

Introduce a **resolution phase** in the builder between assembly and augmentation. This phase discovers unresolved FQCNs and delegates their handling to user-registered resolvers.

### Builder Pipeline

```
1. Collect sources → Assembler → Specification       (existing)
2. Resolve unresolved types → loop until stable      (NEW)
3. Augmenters → Compiler → Result                    (existing)
```

### UnresolvedTypeResolver Interface

```php
interface UnresolvedTypeResolver
{
    /**
     * Attempt to resolve an unresolved FQCN.
     *
     * The resolver may collect/assemble the class and merge results
     * into the specification directly.
     *
     * @return bool true if handled (stops further resolvers for this FQCN)
     */
    public function resolve(string $fqcn, Specification $specification): bool;
}
```

- Self-contained: each resolver owns its internal assembler and attribute factory
- First resolver to claim a FQCN wins (chain of responsibility)
- Writes into the specification via a new `Specification::merge()` method

### Builder Resolution Loop

```php
do {
    $unresolved = $this->findUnresolved($specification);
    $found = false;
    foreach ($unresolved as $fqcn) {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->resolve($fqcn, $specification)) {
                $found = true;
                break;
            }
        }
    }
} while ($found);
```

The loop converges when no resolver claims any new FQCN. This handles transitive discovery naturally: resolving class A may add a schema with a property typed as class B, which the next iteration discovers.

### Discovery: What Counts as "Unresolved"

`Builder::findUnresolved()` collects FQCNs from two sources:

1. **Ref values**: Walk `eachRef`, collect values that don't start with `#/` and pass `class_exists()`
2. **Property/parameter types**: Walk schema properties, inspect reflectors for non-builtin types not yet represented in the specification

Only FQCNs without an existing schema in the specification are returned.

### Specification::merge()

A new method on `Specification` that transfers all components from another specification instance:

```php
public function merge(Specification $other): static
{
    // Iterates all typed lists (schemas, responses, requestBodies, parameters,
    // headers, securitySchemes, links, examples, pathItems, operations, etc.)
    // and calls $this->add() for each item.
}
```

This allows resolvers to produce any component type, not just schemas.

### AutoAnnotateResolver (Reference Implementation)

```php
class AutoAnnotateResolver implements UnresolvedTypeResolver
{
    protected Assembler $assembler;

    public function __construct()
    {
        $attributeFactory = (new AttributeFactory())
            ->withTranslators(
                fn (TypedList $translators): TypedList => $translators
                    ->add(new AutoSchemaTranslator())
            );

        $this->assembler = new Assembler(attributeFactory: $attributeFactory);
    }

    public function resolve(string $fqcn, Specification $specification): bool
    {
        $this->assembler->collect(new \ReflectionClass($fqcn));
        $specification->merge($this->assembler->getSpecification());
        $this->assembler->getSpecification()->reset();

        return true;
    }
}
```

- Reuses a single internal assembler across all iterations
- `AutoSchemaTranslator` generates `OA\Schema` / `OA\Property` for unannotated classes
- Claims all FQCNs unconditionally (catch-all resolver, should be registered last)

### Builder Registration

```php
$builder->addResolver(new AutoAnnotateResolver());
```

## Consequences

### Benefits

- **No pipeline ordering issues**: Resolution completes before augmenters start; `Names`, `Types`, `Refs` see all schemas in a single pass.
- **Single-pass augmenters**: No re-invocation, no implicit idempotency contract.
- **Extensible**: Users register custom resolvers for domain-specific discovery (e.g., only resolve certain namespaces, resolve from external config, produce request bodies instead of schemas).
- **Transitive discovery is natural**: The convergence loop handles arbitrary depth without explicit recursion in the resolver.
- **Resolver is self-contained**: Owns its assembly strategy internally, no factory swapping on the builder's assembler.

### Trade-offs

- The discovery logic (`findUnresolved`) lives in the builder rather than being pluggable. Users can't customize *what* counts as unresolved, only *how* to handle it.
- Resolvers that unconditionally claim all FQCNs (like `AutoAnnotateResolver`) must be registered last in the chain.
- Adds a loop to the build process. In pathological cases (deeply chained transitive types), this could mean many iterations. In practice, most models are 1-2 levels deep.

### What Gets Removed

- `Augmenter\AutoAnnotate` — replaced by `AutoAnnotateResolver` + builder loop
- The `inferNames`, `inferPropertyRef`, re-invocation of `Names`/`Types` hacks
- The type-walking helpers (`collectReferencedTypes`, `getTypedReflectors`, `extractNamedTypes`) move to `Builder::findUnresolved()`

### What Stays

- `Assembler\AutoSchemaTranslator` — unchanged, assembly-time translation concern
- All existing augmenters — unchanged, run once after resolution


================================================

        public ?string $kind = null,                  // ComponentIndex bucket; null = any
      ) {}
}

?\Reflector is the right type — ReflectionProperty and ReflectionParameter both implement it, so a provider gets the specific property or parameter, not just the declaring class. That's what makes inspection actually useful: the provider can read attributes
on the property, nullability, the docblock (Collection<Foo>), default values. Field named $reflector to match Schema::getClassReflector().

Fill it from what each collection source already has on hand: collectFromReflectors() has the \ReflectionProperty/\ReflectionParameter in scope, collectFromRefs() has the attribute. Neither needs new plumbing.

2. Builder\ComponentProviderInterface — one method:

/** @return list<AttributeInterface> empty if not handled */
public function provide(MissingComponent $missing, Specification $specification): array;

3. Builder\ComponentProviders — extends TypedList, holds the list, the loop, and the finding (rename Discovery.php to this; findUnresolved/collectFrom* become protected helpers returning MissingComponents).

And drop the ComponentKind enum for now — ?string $kind against the existing BUCKET_MAP keys does the job, and you can promote it to an enum later if ComponentIndex ever gets typed the same way. That was the piece that was genuinely scope creep; the DTO
isn't, it's just string $fqcn grown two context fields.

Net change from what's on disk: one file renamed, two small files added, one getComponentProviders()/withComponentProviders() pair on Builder, one call in doBuildSpec().

The findSchema() → find() fix in the refs path is worth doing regardless of how the rest lands — that one's a bug, not a design question.

