# Osumi Framework – LLM Context (All-in-One)

**Purpose**
This document provides a compact but complete, authoritative context for any Large Language Model (LLM) to correctly understand, explain, and generate code for **Osumi Framework**.

It is compatible with **ChatGPT, GitHub Copilot, Gemini, Claude**, and other assistants.

> Treat this document as the **source of truth**.
> If something is not described here, do **not** assume it exists.

---

## 0. Framework Identity

- **Name:** Osumi Framework
- **Version:** 9.8
- **Language:** PHP
- **Minimum PHP Version:** 8.3+
- **Typing:** `declare(strict_types=1)` is mandatory
- **Style:** explicit, predictable, no hidden magic

---

## 1. Philosophy (CRITICAL)

Osumi Framework favors:

- Explicit code over magic
- Predictability over convenience
- Clear separation of concerns
- Composition over complex inheritance trees

Do **not** invent helpers, shortcuts, or lifecycle hooks.

---

## 2. Request Lifecycle (AUTHORITATIVE)

Every HTTP request follows this pipeline:

```
Client Request
  ↓
Routing (ORoute)
  ↓
Filters (0..n)
  ↓
DTO hydration & validation (optional)
  ↓
Component::run()
  ↓
Template rendering
  ↓
Layout wrapping (optional)
  ↓
HTTP Response
```

### Key rules

- **Filters always execute before components**.
- If a filter fails → request stops (403 or redirect).
- DTOs are created only if declared as the `run()` parameter.
- Templates render public component properties.

---

## 3. Core Building Blocks

### 3.1 Routing (ORoute)

Routes map URLs to Components.

- HTTP verbs: GET, POST, PUT, DELETE
- Supports route params via `:name`
- Supports:
    - Prefix groups (`ORoute::prefix()`)
    - Layout groups (`ORoute::layout()`)
    - Combined groups (`ORoute::group(prefix, layout, fn)`)

Examples:

```php
ORoute::get('/', HomeComponent::class);
ORoute::get('/user/:id', UserComponent::class);
```

---

### 3.2 Filters

**Purpose:** authentication/authorization, request blocking, context loading.

#### Contract

```php
public static function handle(array $params, array $headers): array
```

Return value must include:

```php
['status' => 'ok' | 'error']
```

Optional:

- `return` → redirect URL
- Any other contextual properties

#### Behavior

- Filters run in order.
- First failing filter stops the request.
- On failure:
    - Redirect if `return` exists
    - Otherwise HTTP 403

Filter outputs are available later via `ORequest->getFilter('Name')`.

---

### 3.3 DTOs (ODTO)

**Purpose:** typed request input + validation, before business logic.

- DTOs extend `ODTO`
- Fields declared with `#[ODTOField]`
- Framework instantiates, loads values, validates, then injects into `run()`.

#### Data source priority

1. Filter result (`filter` + `filterProperty`)
2. Header (`header`)
3. Request params (typed getters)

#### Validation

- `required`
- `requiredIf`

Always check:

```php
if (!$dto->isValid()) {
  $errors = $dto->getValidationErrors();
}
```

DTOs should not contain business logic.

---

### 3.4 Components (OComponent)

**Purpose:** orchestrate request → services/models → prepare output → render.

- Public typed properties are exposed to templates.
- Optional `run()` method.
- `run()` may accept either:
    - a DTO (typed input)
    - an `ORequest` (raw access to params/headers/filters/files)

Keep components thin; move business logic to Services.

---

### 3.5 Templates

Template files can be:

- `.php`
- `.html`
- `.json`
- `.xml`

Static templates (`.html/.json/.xml`) use curly output:

```
{{ variable }}
{{ value | pipe }}
```

**JSON templates must always output valid JSON**.

---

### 3.6 Layouts

**Purpose:** wrap the rendered output of a route component with a shared page structure.

#### Authoritative render flow

1. Route component is executed and rendered
2. If a layout is defined for the route, the layout receives:
    - `title` (default page title)
    - `body` (rendered output from the route component)
3. The layout template is rendered as the final response

Layouts are the natural place for global structure and asset injection.

#### Default layout

New projects include a default layout component with `title` and `body`, and a template that injects them (typically into `<title>` and `<body>`).

#### Defining a custom layout in routing (IMPORTANT)

You can define a custom layout from routing:

```php
ORoute::layout(MainLayoutComponent::class, function() {
  ORoute::get('/home', HomeComponent::class);
  ORoute::get('/contact', ContactComponent::class);
});
```

Or combine prefix + layout:

```php
ORoute::group('/admin', AdminLayoutComponent::class, function() {
  ORoute::get('/dashboard', DashboardComponent::class);
});
```

---

### 3.7 Pipes

Templates support Angular-style pipes:

- `date` → format `Y-m-d H:i:s` to a mask (default `d/m/Y H:i:s`)
- `number` → `number_format` style formatting
- `string` → `urlencode` with JSON-safe quoting
- `bool` → `true | false | null`

---

### 3.8 Services (OService)

**Purpose:** business logic and reusable operations.

- Services extend `OService`.
- They can access config/log/cache via base helpers.
- Injection is done in the **constructor** (PHP cannot call `inject()` in property declarations).
- Services should avoid rendering and stay as stateless as possible.

---

## 4. ORM (OModel)

Osumi Framework includes a lightweight ORM using PHP Attributes.

### Mandatory rules

Every model **must** define:

- ≥ 1 `#[OPK]`
- exactly 1 `#[OCreatedAt]`
- exactly 1 `#[OUpdatedAt]`

### Attributes

- `OPK` (primary key; supports composite PK)
- `OField` (normal column)
- `OCreatedAt` (mandatory created timestamp)
- `OUpdatedAt` (mandatory updated timestamp)
- `ODeletedAt` (optional soft delete timestamp)

### Types (OField constants)

- `OField::NUMBER`
- `OField::TEXT`
- `OField::LONGTEXT`
- `OField::FLOAT`
- `OField::BOOL` (stored as `TINYINT(1)`)
- `OField::DATE` (datetime string)

### Behavior

- Table name inferred from class name using `snake_case`.
- `save()` chooses INSERT vs UPDATE based on PK/state.
- `validate()` runs on save; failures throw exceptions.
- `ref: 'table.field'` is used to generate SQL foreign keys but **does not** auto-load relations.

---

## 5. CLI (OTask)

Osumi Framework is CLI-first.

- Entry point: `php of`
- Tasks extend `OTask`
- Signature:

```php
public function run(array $options = []): void
```

Used for scaffolding, maintenance, schema export, and reverse engineering.

---

## 6. Plugins

Plugins are installed via Composer and exposed under:

```
Osumi\OsumiFramework\Plugins
```

Examples: `OToken`, `OEmail`, `OImage`, `OWebSocket`, etc.

Plugins are optional and independent.

---

## 7. Configuration

- JSON files in `src/Config/`
- Supports environment override files (e.g. `Config_prod.json`)
- Access via `OConfig`:

```php
$this->getConfig()->getExtra('secret');
$this->getConfig()->getDir('uploads');
$this->getConfig()->getDB('name');
```

---

## 8. Conventions (STRICT)

- Always `declare(strict_types=1)`
- Classes: PascalCase
- Files: PascalCase.php
- Tables: snake_case
- Properties/fields: snake_case
- Prefer `public ?Type $prop = null` to avoid uninitialized typed property errors

---

## 9. What NOT to Assume (IMPORTANT)

Do **not** assume:

- automatic DI in property declarations
- active-record relationship loading
- middleware pipelines
- hidden serialization magic
- undocumented helpers

Only documented behavior exists.

---

## 10. How an LLM Should Answer Questions

When answering about Osumi Framework:

- Use this document as the authority
- Prefer explicit, typed code
- Respect architecture boundaries (Filters vs DTOs vs Services vs Components)
- If unsure, ask for missing details instead of inventing

---

## End of Context

This file is intentionally compact, explicit, and deterministic.
It is suitable for direct ingestion by any LLM.
