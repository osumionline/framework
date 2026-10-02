# Osumi Framework – LLM Context (All-in-One)

**Purpose**

This document provides compact but authoritative context for AI systems that explain or generate code for **Osumi Framework 9.9**.

When framework behavior is not documented here, do not invent it.

## 0. Framework Identity

- **Name:** Osumi Framework
- **Version:** 9.9.0
- **Language:** PHP
- **Minimum PHP:** 8.5+
- **Typing:** use `declare(strict_types=1);`
- **Style:** explicit, predictable and strongly typed wherever practical

## 1. Philosophy

Osumi Framework favors:

- explicit code
- predictable lifecycle
- clear separation of responsibilities
- small components
- reusable services
- typed DTOs
- explicit Middleware phases
- minimal hidden behavior

## 2. Main Request Lifecycle

For a normally matched route:

```text
Client request
↓
Routing (ORoute)
↓
before Middlewares
↓
Component / ORequest / DTO
↓
Component rendering
↓
afterRender Middlewares
↓
Layout rendering
↓
afterResponse Middlewares
↓
HTTP response
```

Important:

- DTOs are instantiated only when a component declares an `ODTO` subclass as its `run()` parameter.
- `before` runs before the component.
- `afterRender` runs after the component body exists and before the layout.
- `afterResponse` runs after the final body exists.
- `afterResponse` also runs after a `stop` from `before` or `afterRender`.
- 404, 405 and OPTIONS handling are outside this matched-route Middleware pipeline.

## 3. Routing (`ORoute`)

Routes map HTTP methods and URLs to components or static views.

Supported route methods include:

- `get()`
- `post()`
- `put()`
- `delete()`
- `view()`

Grouping methods include:

- `prefix()`
- `layout()`
- `group()`

Prefixes are cumulative and nestable. URLs are normalized.

Route and group Middleware definitions are arrays grouped by phase.

Example:

```php
ORoute::get(
	'/profile',
	ProfileComponent::class,
	[
		OMiddleware::PHASE_BEFORE => [
			LoginMiddleware::class
		],
		OMiddleware::PHASE_AFTER_RESPONSE => [
			AuditMiddleware::class
		]
	]
);
```

Nested route groups accumulate Middleware definitions.

Within each phase, execution order is:

```text
global
↓
outer group
↓
inner group
↓
route
```

## 4. Middlewares (`OMiddleware`)

Middlewares are the 9.9 request/response interception mechanism.

### 4.1 Phases

```php
OMiddleware::PHASE_BEFORE
OMiddleware::PHASE_AFTER_RENDER
OMiddleware::PHASE_AFTER_RESPONSE
```

### 4.2 Middleware Class Contract

```php
final class ExampleMiddleware {
	/**
	 * Handle a middleware execution phase.
	 *
	 * @param string $phase Current middleware phase.
	 * @param array<string, mixed> $data Current middleware pipeline data.
	 *
	 * @return array<string, mixed> Middleware result.
	 */
	public static function handle(
		string $phase,
		array $data
	): array {
		return [];
	}
}
```

### 4.3 Result Keys

A Middleware may return:

- `context`: `array<string, mixed>`
- `body`: string
- `headers`: `array<string, string>`
- `status_code`: integer from 100 to 599
- `stop`: boolean
- `message`: string used for stop/error response

An empty array means no pipeline changes.

### 4.4 Context

Context is published under the Middleware public name.

`LoginMiddleware` becomes:

```text
Login
```

Example:

```php
return [
	'context' => [
		'id' => 42,
		'role' => 'admin'
	]
];
```

### 4.5 `body`

`body` is meaningful in:

- `afterRender`: replaces the component body before layout rendering.
- `afterResponse`: replaces the final response body.

### 4.6 `stop`

A stop may return:

```php
return [
	'stop' => true,
	'status_code' => 401,
	'message' => 'Unauthorized'
];
```

Semantics:

- `before` stop:
  - remaining `before` Middlewares are skipped
  - component is skipped
  - layout is skipped
  - typed error body is generated
  - `afterResponse` still runs

- `afterRender` stop:
  - remaining `afterRender` Middlewares are skipped
  - layout is skipped
  - typed error body is generated
  - `afterResponse` still runs

- `afterResponse` stop:
  - remaining `afterResponse` Middlewares are skipped
  - its typed error body replaces the final body
  - `afterResponse` is not run recursively

If omitted for a stop:

- `status_code` defaults to `500`
- `message` defaults to `Middleware stopped execution.`

### 4.7 Error State Available to `afterResponse`

After an earlier stop, phase data includes:

```php
$data['is_error']
$data['error_phase']
$data['error_status_code']
$data['error_message']
```

This allows auditing/logging Middlewares to inspect how the request ended.

### 4.8 Global Middlewares

Application-wide Middlewares are configured in:

```text
src/Middleware/Middlewares.php
```

Example:

```php
OMiddleware::setGlobal([
	OMiddleware::PHASE_BEFORE => [],
	OMiddleware::PHASE_AFTER_RENDER => [],
	OMiddleware::PHASE_AFTER_RESPONSE => []
]);
```

## 5. Request (`ORequest`)

`ORequest` provides typed accessors for request parameters, headers, files and Middleware context.

Middleware context:

```php
$login = $req->getMiddleware(
	'Login'
);

$id = $req->getMiddlewareValue(
	'Login',
	'id'
);
```

Behavior:

- missing Middleware context → `[]`
- missing context property → `null`

## 6. DTOs (`ODTO`)

DTOs extend `ODTO` and use `#[ODTOField]`.

The framework:

1. instantiates the DTO
2. loads field values
3. validates it
4. injects it into the component `run()` method

DTO detection is based on inheritance from `ODTO`, not namespace.

### 6.1 Request Sources

When no explicit source is defined, field values are read from request parameters according to their declared type.

### 6.2 Header Source

```php
#[ODTOField(
	header: 'Authorization'
)]
public ?string $authorization = null;
```

### 6.3 Middleware Source

```php
#[ODTOField(
	required: true,
	middleware: 'Login',
	middlewareProperty: 'id'
)]
public ?int $idUser = null;
```

Rules:

- `middleware` and `middlewareProperty` must be defined together.
- Middleware source and header source cannot be combined on the same field.
- Missing explicit Middleware context does not fall back to client input.
- Use Middleware context for trusted server-side values such as authenticated user IDs.

### 6.4 Validation

DTO validation supports:

- `required`
- `requiredIf`

Use:

```php
$dto->isValid();
$dto->getValidationErrors();
```

## 7. Components (`OComponent`)

Components orchestrate:

```text
request → DTO/ORequest → services/models → public properties → template
```

Keep business logic in Services where practical.

A route component can define exactly one of these `run()` signatures:

```php
public function run(): void
```

```php
public function run(ORequest $req): void
```

```php
public function run(MyDTO $dto): void
```

Requirements:

- `MyDTO` must extend `ODTO`.
- The single parameter, if present, must be non-nullable.
- Other parameter types or signatures are invalid.

## 8. Templates and Pipes

Templates can be:

- `.php`
- `.html`
- `.json`
- `.xml`

Static templates use:

```text
{{ variable }}
```

Supported pipes include:

- `date`
- `number`
- `string`
- `plain`
- `bool`

`string` applies URL encoding.

`plain` returns a JSON-safe quoted string without URL encoding and preserves Unicode.

## 9. Layouts

Layouts wrap the component output after `afterRender`.

Relevant order:

```text
component body
↓
afterRender
↓
layout
↓
afterResponse
```

If `before` or `afterRender` stops, the layout is skipped.

## 10. Services (`OService`)

Services contain reusable business/domain logic.

Use Services for:

- reusable model operations
- multi-step domain logic
- external integrations
- reusable application behavior

Do not use Services as a replacement for request/response Middleware or DTO validation.

## 11. ORM (`OModel`)

Models extend `OModel` and use PHP attributes.

Common attributes:

- `#[OPK]`
- `#[OField]`
- `#[OCreatedAt]`
- `#[OUpdatedAt]`

Use explicit property types.

## 12. CLI (`OTask`)

Application tasks extend `OTask`.

Application CLI:

```bash
php of <task>
```

Create a Middleware:

```bash
php of add --option middleware --name Login
```

The old `filter` creation option is not supported in 9.9.

## 13. Framework Migrations

The framework Composer package exposes:

```bash
php vendor/bin/ofw-migrate --help
```

Supported options:

```text
--from
--to
--dry-run
--force
--verbose
--no-interaction
--help
```

Migration state is stored in:

```text
ofw/tmp/state.json
```

Migrations are versioned and idempotent.

The Composer updater integration can execute pending migrations during framework updates.

## 14. Legacy Filter Migration

Filters are legacy pre-9.9 application concepts.

For new 9.9 code:

- do not create Filter classes
- do not use `getFilter()`
- do not use `getFilters()`
- do not use `filter` / `filterProperty`
- use native Middlewares and Middleware context

The 9.9 migration step can preserve legacy Filter business logic by generating Middleware adapters.

A migrated project may therefore temporarily contain legacy Filter classes behind generated Middleware adapters. This is migration compatibility, not the preferred 9.9 application architecture.

## 15. Strict Conventions

Prefer:

- `declare(strict_types=1);`
- explicit parameter and return types
- typed properties
- complete PHPDoc for methods
- PascalCase classes and files
- `::class` references
- `OMiddleware::PHASE_*` constants
- explicit null handling
- small focused classes

## 16. Do Not Assume

Do not invent:

- undocumented helpers
- automatic property dependency injection
- automatic relation loading
- hidden serializers
- implicit Middleware context names other than the class-name rule
- legacy Filter APIs in new code
- `run()` signatures outside the documented contracts

Use the current documentation as authoritative when a detail is not included here.
