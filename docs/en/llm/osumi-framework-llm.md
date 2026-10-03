# Osumi Framework – LLM Context (All-in-One)

**Purpose**

This document provides compact but authoritative context for AI systems that explain or generate code for **Osumi Framework 9.10**.

When framework behavior is not documented here, do not invent it.

## 0. Framework Identity

- **Name:** Osumi Framework
- **Version:** 9.10.0
- **Language:** PHP
- **Minimum PHP:** 8.5+
- **Typing:** use `declare(strict_types=1);`
- **Style:** explicit, predictable and strongly typed wherever practical

## 1. Philosophy

Osumi Framework favors explicit code, predictable lifecycle, clear separation of responsibilities, small components, reusable services, typed DTOs, explicit Middleware phases and minimal hidden behavior.

## 2. Main Request Lifecycle

Traditional response:

```text
Request
↓
Routing
↓
before
↓
Component / ORequest / DTO
↓
Rendering
↓
afterRender
↓
Layout
↓
afterResponse
↓
HTTP response
```

Streamed response:

```text
Request
↓
Routing
↓
before
↓
Component → OStreamResponse
↓
afterRender
↓
afterResponse
↓
Close DB connections
↓
HTTP headers
↓
Chunked stream
```

Streaming skips layout processing and no stream bytes are emitted before post-component Middlewares complete.

404, 405 and OPTIONS handling remain outside the normal matched-route Middleware pipeline.

## 3. Routing (`ORoute`)

Methods: `get()`, `post()`, `put()`, `delete()`, `view()`.

Grouping: `prefix()`, `layout()`, `group()`.

Middleware definitions are grouped by phase. Nested groups accumulate them in global → outer group → inner group → route order.

## 4. Middlewares (`OMiddleware`)

Phases:

```php
OMiddleware::PHASE_BEFORE
OMiddleware::PHASE_AFTER_RENDER
OMiddleware::PHASE_AFTER_RESPONSE
```

Contract:

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

Result keys: `context`, `body`, `headers`, `status_code`, `stop`, `message`.

`body` can only replace materialized bodies. Returning `body` for a streamed response is invalid.

Relevant phase data:

```php
$data['is_streaming_response']
$data['is_error']
$data['error_phase']
$data['error_status_code']
$data['error_message']
```

During `afterRender` and `afterResponse` for an `OStreamResponse`, `is_streaming_response` is `true`. Middlewares may change context, headers and status or stop the response before emission starts.

## 5. Request (`ORequest`)

Typed access to parameters, headers, files and Middleware context through `getMiddleware()` and `getMiddlewareValue()`.

## 6. DTOs (`ODTO`)

DTOs extend `ODTO` and use `#[ODTOField]`. Values may come from request input, headers or Middleware context. `middleware` and `middlewareProperty` must be declared together and never fall back to client input.

## 7. Components (`OComponent`)

Supported `run()` parameter contracts:

```php
public function run(): void
public function run(ORequest $req): void
public function run(MyDTO $dto): void
```

Streaming variants:

```php
public function run(): OStreamResponse
public function run(ORequest $req): OStreamResponse
public function run(MyDTO $dto): OStreamResponse
```

If a component has no template, `run()` must explicitly declare and return `OStreamResponse`.

An `OStreamResponse` cannot be nested inside a template or converted to a string.

## 8. `OStreamResponse`

`Osumi\OsumiFramework\Web\OStreamResponse` encapsulates:

- a readable stream;
- `array<string, string>` headers;
- HTTP status, default 200;
- chunk size, default 1 MiB;
- framework stream ownership/automatic close, default `true`.

The framework validates the resource, readability, headers, status and chunk size. Framework-owned streams are closed on completion, discard or response destruction.

## 9. Templates and Pipes

Templates: `.php`, `.html`, `.json`, `.xml`.

Pipes: `date`, `number`, `string`, `plain`, `bool`.

## 10. Layouts

Layouts wrap traditional responses after `afterRender`. They are never applied to `OStreamResponse`.

## 11. Services (`OService`)

Use for reusable business/domain logic, multi-step operations and external integrations.

## 12. ORM (`OModel`)

Models use attributes such as `#[OPK]`, `#[OField]`, `#[OCreatedAt]`, `#[OUpdatedAt]`.

## 13. CLI and Migrations

Application CLI: `php of <task>`.

Migrator: `php vendor/bin/ofw-migrate --help`.

Options: `--from`, `--to`, `--dry-run`, `--force`, `--verbose`, `--no-interaction`, `--help`.

State: `ofw/tmp/state.json`.

## 14. Legacy Filters

Filters predate 9.9 and are not part of the current runtime API. New code must use Middlewares. The 9.9 migration step can preserve legacy logic through adapters.

## 15. Strict Conventions

Prefer `declare(strict_types=1);`, explicit types, typed properties, complete PHPDoc on methods, `::class`, `OMiddleware::PHASE_*`, explicit null handling and small focused classes.

## 16. Do Not Assume

Do not invent helpers, automatic property injection, automatic relation loading, hidden serializers, legacy Filter APIs or `run()` signatures outside documented contracts.
