# Glossary

Canonical vocabulary for swagger-php. Terms are grouped by pipeline; the spec
pipeline (v6+) evolves the classic pipeline's concepts with narrower, more
precise names.

---

## Shared (both pipelines)

| Term | Definition | Avoid |
|------|-----------|-------|
| **OpenAPI spec** | The output document (JSON/YAML) conforming to the OpenAPI Specification. | "swagger file", "API docs" |
| **Reflector** | A PHP Reflection object (`ReflectionClass`, `ReflectionMethod`, `ReflectionProperty`, `ReflectionParameter`, `ReflectionClassConstant`) representing a source element. | "target", "element" (too vague) |
| **Source location** | File path + line + column where an attribute or annotation was declared. | "position", "origin" |
| **Builder** | Unified entry point that configures and runs either pipeline, returning a Result. | "runner" |

---

## Classic Pipeline

Terms from the existing annotation/processor architecture (v5–v6).

| Term | Definition | Avoid |
|------|-----------|-------|
| **Annotation** | An OpenAPI element declared as a PHP 8+ attribute or legacy docblock on a reflector. The base unit of the classic pipeline. | "attribute" (too narrow — also covers docblocks) |
| **Analysis** | The aggregate of all discovered annotations and their contexts, before processing. | "result", "scan output" |
| **Context** | Prototypically-inherited metadata bag describing where an annotation was found (file, namespace, class, method, property) and its relationships (`nested`, `annotations`). | "location" |
| **Generator** | Orchestrator that coordinates scanning, processing, and output. | "builder", "compiler" |
| **Processor** | A single ordered transformation step that converts raw Analysis into a valid spec. | "handler", "middleware" |
| **Analyser** | Reflects on PHP source files to discover annotations and produce an Analysis. | "scanner" (too narrow) |
| **AnnotationFactory** | Creates annotation objects from PHP attributes or docblock comments. | "builder", "constructor" |
| **Nesting** (`$_nested`) | Declarative parent→child type map on each annotation class defining which types can be placed inside. | "hierarchy" (ambiguous with class hierarchy) |
| **Merge** | Incorporating an annotation into its correct position within the OpenAPI object tree via `$_nested` rules. | "combine", "attach" |
| **Augment** | Filling in missing annotation fields with values inferred from code (type hints, defaults). | "enrich", "hydrate" |
| **Expand** | Resolving PHP inheritance by copying parent annotations into child schemas. | "inherit", "flatten" |
| **Unmerged** | An annotation discovered but not yet incorporated into the root OpenAPI object. | "pending", "orphaned" |
| **Component** | A reusable named definition in `#/components/` referenced via `$ref`. | "shared schema", "template" |
| **Ref** | A JSON Pointer (`$ref`) linking to another part of the spec. | "link" (means something else in OpenAPI) |

---

## Spec Pipeline

Terms from the new attribute architecture (v6 spec path, primary in v7).

### Core types

| Term | Definition | Avoid |
|------|-----------|-------|
| **Attribute** (spec) | A PHP 8+ attribute implementing `AttributeInterface`, declared in the `OpenApi\Spec\*` namespace. Pure DTO — no behaviour, no side effects on construction. | "annotation" (classic term; spec attrs have no docblock path) |
| **Specification** | Flat root container holding all collected attributes in typed collections. Not a DTO — provides finders, registry, and routing. Replaces `Analysis` in v7. | "analysis", "document" |
| **Assembler** | Collects attributes from reflectors and resolves them into root-level attributes ready for `Specification::add()`. Replaces `$_nested` + `merge()` + `matchNested()`. | "factory" (that's the layer below) |
| **Compiler** | Transforms a Specification into version-correct output (array/JSON/YAML). One compiler per OpenAPI version. All version-specific logic lives here. | "serializer", "renderer" |

### Assembly operations

These are the two resolution passes the Assembler performs:

| Term | Definition | Code | Avoid |
|------|-----------|------|-------|
| **Stack-resolve** | First pass: on a single reflector, sibling attributes are merged using `merge()` — e.g. a `Schema` adjacent to a `Property` on the same parameter fills the Property's `$schema` slot. | `resolveNesting()` | "pair", "match" |
| **Hierarchical absorb** | Second pass: attributes from inner reflectors (properties, parameters, constants) flow up into enclosing-level containers using `contains()` — e.g. `Property` instances from class members are absorbed into the class-level `Schema`. | `resolveHierarchy()` | "merge up", "bubble" |
| **Nest** | Place a child attribute into its parent's typed property or collection slot. The mechanical action after a match is found. | `nestChild()` | "assign", "attach" |

### AttributeInterface methods

| Method | Role | Description |
|--------|------|-------------|
| `isRoot()` | **Root declaration** | Whether this attribute is a top-level element that goes directly into the Specification (e.g. Schema, Operation, Info). Non-root attributes must be absorbed by a container. |
| `merge()` | **Same-reflector composition** | Returns sibling types this attribute can be nested into when they share the same PHP reflector. Drives the stack-resolve pass. |
| `contains()` | **Hierarchical absorption** | Returns child types this container can absorb from inner reflector levels. Drives the hierarchical-absorb pass. |

### Lifecycle stages

```
Reflectors → readAttributes → raw attribute list (per reflector)
         → resolveNesting (stack-resolve) → resolved siblings
         → resolveHierarchy (hierarchical absorb) → root attributes
         → Specification::add() → flat typed collections
         → Compiler::compile() → versioned OpenAPI document
```

| Stage | What happens |
|-------|-------------|
| **Read** | PHP attributes are instantiated from reflectors. Each receives its source reflector. No nesting, no resolution. |
| **Stack-resolve** | Sibling attributes on the same reflector are paired. A child declares via `merge()` which sibling types it can nest into. After this pass, only unabsorbed attributes remain. |
| **Hierarchical absorb** | Inner-level attributes flow into outer-level containers. A parent declares via `contains()` which child types it can accept. After this pass, only root attributes (`isRoot() = true`) remain. |
| **Collect** | Root attributes are added to the Specification's typed collections. |
| **Compile** | The Compiler walks the Specification, groups and nests as needed (e.g. operations by path), applies version-specific transformations, and produces the output array. |

---

## Cross-pipeline mapping

How classic concepts map to spec pipeline equivalents:

| Classic | Spec pipeline | Notes |
|---------|---------------|-------|
| Annotation | Attribute (spec) | DTOs only; no behaviour |
| Analysis | Specification | Flat collections, finders |
| Generator | Builder | Builder delegates to either pipeline |
| Processor | (Processor on Specification) | Same concept, different input type |
| `$_nested` map | `merge()` + `contains()` | Rules on the attribute, not a static array |
| `matchNested()` | `resolveNesting()` | Explicit: ambiguity is an error, not a heuristic |
| `merge()` (on AbstractAnnotation) | `nestChild()` | Places child into parent slot |
| Context | SourceLocation + Reflector | No prototypical inheritance; metadata is simpler |
| AnnotationFactory | `readAttributes()` | Thin: just `newInstance()` + assign reflector |
| `addAnnotation()` | `Specification::add()` | Routes by type |
| `jsonSerialize()` | `Compiler::compile()` | Version logic centralized |

---

## Naming conventions

### Method names in Assembler

| Method | Named after | Why |
|--------|-------------|-----|
| `collect()` | The public action: "collect attributes from reflectors" | Entry point; imperative verb matches `Generator::generate()` |
| `instantiate()` | Returns resolved attributes without adding to Specification | For callers that want DTOs without side effects |
| `resolveNesting()` | "resolve" + "nesting" (same-level sibling relationships) | Describes the stack-resolution pass |
| `resolveHierarchy()` | "resolve" + "hierarchy" (outer/inner level relationships) | Describes the hierarchical absorption pass |
| `nestChild()` | "nest" (the mechanical action of placing into a slot) | Low-level: finds the right property and assigns |
| `readAttributes()` | "read" (instantiate from reflector, nothing more) | Thin layer; no resolution logic |

### Terms to keep consistent

- **Root** (attribute): goes into Specification directly. Opposite of "absorbed" / "nested".
- **Container** (attribute): an attribute that absorbs children via `contains()`.
- **Sibling** (attribute): attributes on the same reflector (same PHP element).
- **Inner / outer**: relative reflector levels. Inner = property/parameter/constant; outer = class/method.
- **Slot**: a typed property or collection on a parent attribute where a child is placed.
