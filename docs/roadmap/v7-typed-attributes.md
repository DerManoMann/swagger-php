# Typed Attribute Classes for v7 Spec Pipeline

## Motivation

The current attribute classes mirror the OpenAPI spec as flat bags of properties. Many spec objects
have polymorphic shapes where a discriminator field (`type`, `in`, parent context) determines which
other fields are valid. This means users can construct attributes with invalid field combinations
that only fail at serialization or validation time.

Typed subclasses eliminate impossible states at the attribute level — each class only exposes
the fields that make sense for its variant. This also improves IDE autocomplete, static analysis,
and makes the attribute API self-documenting.

## Prior Art: Java/Spring (swagger-core v3)

Java's `io.swagger.v3.oas.annotations` does **not** use typed subclasses for any of these patterns.
Their approach for every case is a single annotation with a discriminator enum/string field and
runtime validation of field combinations:

| Concept | Java approach |
|---------|-------------|
| Security Scheme | Single `@SecurityScheme` + `SecuritySchemeType` enum — no subclasses |
| OAuth Flows | Container `@OAuthFlows` with named fields (`implicit()`, `password()`, `clientCredentials()`, `authorizationCode()`) — flow type is determined by position, not a field on `@OAuthFlow` |
| Parameter | Single `@Parameter` + `ParameterIn` enum — no `@PathParameter` etc. |
| Schema | Single `@Schema` (~60+ fields) + only `@ArraySchema` as a companion (workaround for Java's inability to nest an annotation inside itself for `items`) |
| Link | Single `@Link`, mutually exclusive string fields |
| Example | Single `@ExampleObject`, mutually exclusive string fields |

**Key takeaway:** The typed subclass approach proposed here would be novel — no major OpenAPI
tooling ecosystem has done this. Java annotations have structural limitations (no nesting, no
named arguments) that PHP 8 attributes don't share, which partly explains why they didn't go
this route. PHP's `new Foo(...)` nesting in attributes and named parameters make subclasses
far more ergonomic than they'd be in Java.

This means we'd be pioneering this DX pattern. That's a strength (better DX than any existing
tool) but also a risk (no proven track record, users coming from other ecosystems won't
recognize the pattern).

---

## Category 1: Type-Discriminated Unions

These are the strongest candidates — a field value determines which other fields are valid.

### 1.1 Security Scheme (`type` field → 5 variants)

**Current state:** Single `SecurityScheme` class with all fields; `type` validated as enum at runtime.

**Proposed:**

```
SecurityScheme (abstract or interface)
├── ApiKeyScheme         → name, in
├── HttpScheme           → scheme, bearerFormat
├── MutualTlsScheme      → (no extra fields)
├── OAuth2Scheme         → flows
└── OpenIdConnectScheme  → openIdConnectUrl
```

Shared fields: `securityScheme` (key/name), `description`

**Example usage:**

```php
#[OAT\ApiKeyScheme(
    securityScheme: 'api_key',
    name: 'X-API-Key',
    in: 'header',
)]
class Security {}

#[OAT\OAuth2Scheme(
    securityScheme: 'oauth2',
    flows: [
        new OAT\AuthorizationCodeFlow(
            authorizationUrl: 'https://example.com/auth',
            tokenUrl: 'https://example.com/token',
            scopes: ['read:pets' => 'Read pets'],
        ),
    ],
)]
class Security {}

#[OAT\HttpScheme(
    securityScheme: 'bearer',
    scheme: 'bearer',
    bearerFormat: 'JWT',
)]
class Security {}
```

### 1.2 OAuth Flow (discriminated by parent field → 4 variants)

**Current state:** Single `Flow` class with `flow` string enum and all URL fields; rules about
which URLs are required vary per flow type.

**Proposed:**

```
OAuthFlow (abstract or interface)
├── ImplicitFlow              → authorizationUrl (REQUIRED), refreshUrl, scopes
├── PasswordFlow              → tokenUrl (REQUIRED), refreshUrl, scopes
├── ClientCredentialsFlow     → tokenUrl (REQUIRED), refreshUrl, scopes
└── AuthorizationCodeFlow     → authorizationUrl (REQUIRED), tokenUrl (REQUIRED), refreshUrl, scopes
```

Shared fields: `refreshUrl` (optional), `scopes` (required)

**Example usage:**

```php
#[OAT\OAuth2Scheme(
    securityScheme: 'petstore_auth',
    flows: [
        new OAT\ImplicitFlow(
            authorizationUrl: 'https://petstore.swagger.io/oauth/authorize',
            scopes: ['write:pets' => 'Modify', 'read:pets' => 'Read'],
        ),
        new OAT\AuthorizationCodeFlow(
            authorizationUrl: 'https://petstore.swagger.io/oauth/authorize',
            tokenUrl: 'https://petstore.swagger.io/oauth/token',
            scopes: ['admin' => 'Full access'],
        ),
    ],
)]
```

### 1.3 Parameter (`in` field → 4 variants)

**Current state:** We already have `PathParameter` as a subclass. The others exist implicitly.

**Proposed:**

```
Parameter (abstract or interface)
├── PathParameter      → required=true (forced), style ∈ {simple, matrix, label}
├── QueryParameter     → allowEmptyValue, allowReserved, style ∈ {form, spaceDelimited, pipeDelimited, deepObject}
├── HeaderParameter    → style = simple (fixed)
└── CookieParameter    → style = form (fixed)
```

Shared fields: `name`, `description`, `deprecated`, `schema`/`content`, `example`/`examples`

**Example usage:**

```php
#[OAT\Get(path: '/pets/{id}')]
public function get(
    #[OAT\PathParameter] int $id,
    #[OAT\QueryParameter(description: 'Filter by status', style: 'form')]
    ?string $status = null,
    #[OAT\HeaderParameter(name: 'X-Request-ID')] ?string $requestId = null,
) {}
```

### 1.4 Schema (`type` → keyword groups)

**Current state:** Single `Schema` class with all validation keywords. Also `Property` as a
contextual subclass.

**Proposed (conservative — optional typed helpers, base Schema remains):**

```
Schema (base — composition, references, universal keywords)
├── StringSchema       → minLength, maxLength, pattern, format, contentEncoding, contentMediaType
├── NumberSchema       → minimum, maximum, exclusiveMinimum, exclusiveMaximum, multipleOf
├── IntegerSchema      → (same as NumberSchema)
├── ArraySchema        → items, prefixItems, contains, minItems, maxItems, uniqueItems
├── ObjectSchema       → properties, patternProperties, additionalProperties, required, minProperties, maxProperties
└── BooleanSchema      → (no type-specific keywords)
```

**Note:** This is the most controversial. Schema composition (`allOf`/`oneOf`/`anyOf`) means real
schemas often don't fit a single type cleanly. Subclasses would be *helpers* for the simple cases,
not forced upon users.

**Example usage:**

```php
#[OAT\ObjectSchema]
class Pet {
    #[OAT\StringSchema(minLength: 1, maxLength: 100)]
    public string $name;

    #[OAT\ArraySchema(items: new OAT\Schema(ref: Tag::class), minItems: 1)]
    public array $tags;

    #[OAT\IntegerSchema(minimum: 0, format: 'int64')]
    public int $id;
}
```

---

## Category 2: Mutually-Exclusive Field Groups

Lighter patterns where "either A or B" could be modeled as distinct classes or union constructors.

### 2.1 Link Object (`operationRef` vs `operationId`)

**Option A — two classes:**
```
Link (abstract)
├── LinkByRef    → operationRef, parameters, requestBody, description, server
└── LinkById     → operationId, parameters, requestBody, description, server
```

**Option B — keep one class, enforce at construction** (simpler, less DX friction)

### 2.2 Example Object (`value` vs `externalValue`)

```
Example (abstract or interface)
├── InlineExample    → summary, description, value
└── ExternalExample  → summary, description, externalValue
```

### 2.3 License Object (`identifier` vs `url`)

```
License (abstract)
├── SpdxLicense  → name, identifier
└── UrlLicense   → name, url
```

### 2.4 Parameter/Header content mode (`schema` vs `content`)

Could be modeled as constructor variants or separate subclasses. Given that `schema` mode is 99%
of usage, this is probably not worth splitting — just enforce mutual exclusion in the constructor.

---

## Category 3: Context-Dependent Behavior

These are structures where the same object means different things based on where it appears.

### 3.1 Encoding Object

Behavior depends on parent media type (`application/x-www-form-urlencoded` vs `multipart/*` vs other).
Fields `headers`, `style`, `explode`, `allowReserved` only apply in certain contexts.

**Recommendation:** Keep as one class. Context validation belongs in the compiler, not the attribute.

### 3.2 Schema as Property vs Top-Level

Already handled: `#[Schema]` on a class = top-level schema definition; `#[Schema]` on a property
= nested property schema. The processor/compiler infers from placement.

---

## Category 4: Composition & Polymorphism Keywords

### 4.1 Schema Composition

```php
// allOf — merge schemas (inheritance)
#[OAT\Schema(allOf: [new OAT\Schema(ref: Pet::class), new OAT\Schema(required: ['id'])])]

// oneOf — exactly one matches (tagged union)
#[OAT\Schema(oneOf: [new OAT\Schema(ref: Cat::class), new OAT\Schema(ref: Dog::class)],
             discriminator: new OAT\Discriminator(propertyName: 'type'))]

// anyOf — one or more match
#[OAT\Schema(anyOf: [new OAT\Schema(ref: Cat::class), new OAT\Schema(ref: Dog::class)])]
```

**These don't need subclasses** — they're orthogonal combinators that can appear alongside any schema.
Keep them as properties on the base Schema class.

### 4.2 Discriminator Object

Small, flat object (`propertyName`, `mapping`). No polymorphism internally. Keep as-is.

---

## Priority & Effort Assessment

| Target | Impact | Effort | Risk | Priority |
|--------|--------|--------|------|----------|
| Security Scheme variants | High — very clear type discrimination, common source of user errors | Low — small number of fields per variant | Low | **P1** |
| OAuth Flow variants | High — required fields differ per flow, very error-prone today | Low | Low | **P1** |
| Parameter variants | Medium — PathParameter already exists, pattern proven | Medium | Low | **P2** |
| Schema type variants | High DX improvement but complex | High — composition complicates things | Medium — might box users in | **P3** |
| Link/Example/License | Low — rarely misused | Low | Low | **P4** |

---

## Open Questions

1. **Abstract class vs interface?** Interface allows implementing alongside other attributes;
   abstract class gives shared field storage. PHP 8 attributes can extend classes — does the
   spec pipeline's DTO layer prefer one?

2. **Keep the generic parent usable?** E.g., should `#[SecurityScheme(type: 'apiKey', ...)]` still
   work for users who prefer the flat style, or should we force subclasses? Backwards compat
   argues for keeping both during v6→v7 transition.

3. **Schema subclasses — helpers or primary API?** The `StringSchema` etc. could be optional
   convenience classes that still compile to the same DTO, with bare `Schema` remaining the
   canonical way. Or they could be the recommended path.

4. **Naming convention** — `HttpScheme` vs `HttpSecurityScheme` vs `BearerAuth`? Shorter is
   better for attributes but clarity matters when scanning code.

5. **Validation enforcement** — Should impossible field combinations be prevented at the PHP
   constructor level (type errors), or validated by the compiler (runtime errors with messages)?
   Constructor-level is stricter but less flexible for edge cases.

6. **`#[Schema]` on properties vs `#[Property]`** — If we adopt Java's pattern of using Schema
   everywhere, do we deprecate Property or keep it as an alias? Schema-everywhere is more
   spec-accurate but Property is more readable for the "this is a property" intent.

7. **Unified "key" property name (suggestion)** — Currently every attribute uses a different
   property name for what is conceptually the same thing: the key in the parent map/JSON structure.

   Current inconsistency:

   | Class | Key property | Meaning |
   |-------|-------------|---------|
   | SecurityScheme | `securityScheme` | Key in `#/components/securitySchemes` |
   | Response | `response` | HTTP status code / key in responses map |
   | Property | `property` | Key in schema `properties` map |
   | Schema | `schema` | Key in `#/components/schemas` |
   | Parameter | `parameter` | Key in `#/components/parameters` |
   | Link | `link` | Key in MediaType links map |
   | RequestBody | `request` | Key in `#/components/requestBodies` |
   | Examples | `example` | Key in `#/components/examples` |
   | ServerVariable | `serverVariable` | Key in Server variables map |
   | MediaType | `mediaType` | Media type string (e.g. `application/json`) |

   Could we unify to a single property name like `key` everywhere? The spec uses `title` and
   `description` for human-readable labels, so `name` is available in most cases — but it
   already means something else on SecurityScheme (the API key header/query param name) and
   Parameter (the actual param name in the URL).

   `key` avoids all clashes and is semantically accurate — it's literally the key in the
   parent structure. Trade-off: `key` is less self-describing than `securityScheme` for someone
   reading the attribute in isolation. But with typed subclasses (`#[ApiKeyScheme(key: 'api_key')]`)
   the context is already clear from the class name.

   **Before:**
   ```php
   #[OAT\SecurityScheme(securityScheme: 'bearer', type: 'http', scheme: 'bearer')]
   #[OAT\Response(response: 200, description: 'OK')]
   #[OAT\Schema(schema: 'Pet')]
   ```

   **After:**
   ```php
   #[OAT\HttpScheme(key: 'bearer', scheme: 'bearer')]
   #[OAT\Response(key: 200, description: 'OK')]
   #[OAT\Schema(key: 'Pet')]
   ```

   Or with `name` where no clash exists, falling back to `key` only where needed? Mixed
   approach might be worse than picking one.

8. **Nested namespaces for attribute groups** — The typed subclass families map naturally to
   sub-namespaces, keeping the top-level namespace clean while making discoverability obvious:

   ```
   OpenApi\Attributes\
   ├── Security\
   │   ├── ApiKeyScheme
   │   ├── HttpScheme
   │   ├── OAuth2Scheme
   │   ├── OpenIdConnectScheme
   │   └── MutualTlsScheme
   ├── OAuth\
   │   ├── ImplicitFlow
   │   ├── PasswordFlow
   │   ├── ClientCredentialsFlow
   │   └── AuthorizationCodeFlow
   ├── Parameter\
   │   ├── PathParameter
   │   ├── QueryParameter
   │   ├── HeaderParameter
   │   └── CookieParameter
   ├── Schema\
   │   ├── StringSchema
   │   ├── NumberSchema
   │   ├── IntegerSchema
   │   ├── ArraySchema
   │   ├── ObjectSchema
   │   └── BooleanSchema
   ├── Schema          (base, top-level)
   ├── Response
   ├── Info
   ├── ...
   ```

   Usage with aliased imports:

   ```php
   use OpenApi\Attributes as OA;
   use OpenApi\Attributes\Security;
   use OpenApi\Attributes\OAuth;

   #[Security\HttpScheme(key: 'bearer', scheme: 'bearer', bearerFormat: 'JWT')]
   #[Security\OAuth2Scheme(
       key: 'petstore_auth',
       flows: [new OAuth\AuthorizationCodeFlow(authorizationUrl: '...', tokenUrl: '...', scopes: [...])]
   )]
   ```

   Or fully qualified for one-offs:

   ```php
   #[OA\Security\ApiKeyScheme(key: 'api_key', name: 'X-API-Key', in: 'header')]
   ```

   This keeps the root namespace (`OA\Schema`, `OA\Get`, `OA\Response`) uncluttered for the
   common cases while making the variants discoverable via IDE autocomplete on the sub-namespace.

---

## Comparison: Current vs Proposed DX

### Security (before)
```php
#[OAT\SecurityScheme(
    securityScheme: 'bearer',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
)]
```

### Security (after)
```php
#[OAT\HttpScheme(securityScheme: 'bearer', scheme: 'bearer', bearerFormat: 'JWT')]
```

### OAuth (before)
```php
#[OAT\SecurityScheme(
    securityScheme: 'oauth2',
    type: 'oauth2',
    flows: [new OAT\Flow(flow: 'authorizationCode', authorizationUrl: '...', tokenUrl: '...', scopes: [...])]
)]
```

### OAuth (after)
```php
#[OAT\OAuth2Scheme(
    securityScheme: 'oauth2',
    flows: [new OAT\AuthorizationCodeFlow(authorizationUrl: '...', tokenUrl: '...', scopes: [...])]
)]
```

---

## Next Steps

- [ ] Gather feedback on priority and naming
- [ ] Decide abstract class vs interface for base types
- [ ] Decide whether flat `SecurityScheme(type: ...)` remains valid alongside subclasses
- [ ] Prototype SecurityScheme + OAuth Flow variants as first implementation
- [ ] Evaluate Schema subclasses separately (larger scope, more controversial)