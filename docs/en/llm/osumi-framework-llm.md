# Osumi Framework – LLM Context (All-in-One)

**Purpose**  
This document provides compact but complete and authoritative context for any Large Language Model (LLM) to correctly understand, explain and generate code for **Osumi Framework**.

## 0. Framework Identity

- **Name:** Osumi Framework
- **Version:** 9.8.5
- **Language:** PHP
- **Minimum PHP Version:** 8.3+
- **Typing:** `declare(strict_types=1)` is mandatory
- **Style:** explicit, predictable, no hidden magic

## 1. Philosophy

Osumi Framework prioritizes explicit code, predictability, clear separation of concerns and composition.

## 2. Request Lifecycle

```text
Client Request
↓
Routing (ORoute)
↓
Filters (0..n)
↓
DTO hydration and validation (optional)
↓
Component::run()
↓
Template rendering
↓
Layout wrapping (optional)
↓
HTTP Response
```

- Filters execute before components.
- DTOs are created only when declared as a `run()` parameter.
- Templates render public component properties.

## 3. Main Building Blocks

### 3.1 Routing (ORoute)

Routes map URLs to components. GET, POST, PUT and DELETE are supported. Prefix and group nesting is supported and route URLs are normalized.

### 3.2 Filters

Filters handle authentication/authorization, request blocking and context loading. Filter output is available through `ORequest->getFilter('Name')`.

### 3.3 DTOs (ODTO)

- DTOs extend `ODTO`.
- Fields use `#[ODTOField]`.
- The framework instantiates, populates, validates and injects DTOs into `run()`.
- DTO detection is based on inheritance from `ODTO`, not namespace.
- DTOs should not contain business logic.

### 3.4 Components (OComponent)

**Purpose:** orchestrate request → services/models → prepare output → render.

- Public typed properties are exposed to templates.
- `run()` is optional.
- `run()` supports exactly one of these signatures:
  - `run(): void`
  - `run(ORequest $req): void`
  - `run(MyDTO $dto): void`, where `MyDTO` extends `ODTO`
- DTOs are detected by inheritance from `ODTO`, not by namespace.
- No other parameter types or signatures are supported.

Keep components thin; move business logic into Services.

### 3.5 Templates

Template files can be `.php`, `.html`, `.json` or `.xml`. Static templates use `{{ variable }}` and pipes.

### 3.6 Layouts

Layouts wrap rendered route component output with shared page structure.

### 3.7 Pipes

Templates support Angular-style pipes:

- `date` → format `Y-m-d H:i:s` to a mask (default `d/m/Y H:i:s`)
- `number` → `number_format` style formatting
- `string` → `urlencode` with quoted output
- `plain` → JSON-safe quoted string without URL encoding
- `bool` → `true | false | null`

### 3.8 Services (OService)

Services extend `OService` and contain reusable business logic.

## 4. ORM (OModel)

Models use PHP attributes. Every model must define at least one `#[OPK]`, exactly one `#[OCreatedAt]` and exactly one `#[OUpdatedAt]`.

## 5. CLI (OTask)

Tasks extend `OTask` and use `public function run(array $options = []): void`.

## 6. Plugins

Plugins are installed through Composer under `Osumi\OsumiFramework\Plugins`.

## 7. Configuration

JSON configuration files live in `src/Config/`.

## 8. Strict Conventions

- Always `declare(strict_types=1)`
- Classes: PascalCase
- Files: PascalCase.php
- Tables: snake_case
- Properties/fields: snake_case
- Prefer typed properties and explicit return types.

## 9. Do Not Assume

Do not assume automatic property DI, active-record relation loading, middleware pipelines, hidden serialization or undocumented helpers.

## 10. LLM Guidance

Use this document as authoritative framework context. Prefer explicit, typed code and do not invent undocumented behavior.
