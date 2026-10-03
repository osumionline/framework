# Middlewares

Middlewares in **Osumi Framework** are reusable classes that participate in the HTTP request lifecycle.

They can run in three phases:

- `before`: before the route component is executed.
- `afterRender`: after the component is rendered and before layout processing for traditional responses.
- `afterResponse`: after the final response is prepared and before it is emitted.

Typical uses include authentication, authorization, token validation, context loading, response transformation, headers, status codes, auditing and logging.

## 1. Middleware Structure

```php
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Middleware;

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

Application Middlewares are normally stored in `src/Middleware/`.

## 2. Phases

```php
OMiddleware::PHASE_BEFORE
OMiddleware::PHASE_AFTER_RENDER
OMiddleware::PHASE_AFTER_RESPONSE
```

Traditional flow:

```text
Routing
↓
before
↓
Component
↓
afterRender
↓
Layout
↓
afterResponse
↓
HTTP response
```

For streamed responses the layout is skipped and `afterResponse` completes before any stream bytes are sent.

### `before`

Runs before component instantiation. Use it for authentication, authorization, validation, request blocking and context loading.

### `afterRender`

For traditional responses it runs after component rendering and before layout processing. It can inspect or replace the body, add headers or change the status code.

For streamed responses it still runs, but there is no materialized body to replace.

### `afterResponse`

This is the final phase before emission. It also runs after a `before` or `afterRender` stop.

## 3. Middleware Result

`handle()` must always return an array. An empty array means no changes.

Supported keys:

- `context`: publishes data for later phases, `ORequest` and DTOs.
- `body`: replaces the body in `afterRender` or `afterResponse`, except for streamed responses.
- `headers`: adds or replaces HTTP headers.
- `status_code`: integer from `100` to `599`.
- `stop`: stops the current phase.
- `message`: message used when stopping.

### `body`

```php
return [
	'body' => 'Modified response'
];
```

Returning `body` during a streamed response throws `InvalidArgumentException`, because the stream is not materialized as a string.

## 4. Accumulated Phase State

In addition to request data, `$data` includes accumulated pipeline state such as:

```php
$data['context']
$data['component_body']
$data['final_body']
$data['response_headers']
$data['status_code']
$data['is_streaming_response']
$data['is_error']
$data['error_phase']
$data['error_status_code']
$data['error_message']
```

## 5. Streamed Responses

When the component returns `OStreamResponse`:

```php
$data['is_streaming_response'] === true
```

throughout `afterRender` and `afterResponse`.

Middlewares may still modify:

- `context`
- `headers`
- `status_code`
- `stop`
- `message`

They cannot return `body`.

The framework emits no stream bytes until both phases finish. If a Middleware returns `stop => true`, the stream is discarded, normal response headers are restored and a traditional typed error response is generated.

Layouts are skipped for streamed responses.

## 6. Global Middlewares

Configure them in `src/Middleware/Middlewares.php`:

```php
OMiddleware::setGlobal([
	OMiddleware::PHASE_BEFORE => [],
	OMiddleware::PHASE_AFTER_RENDER => [],
	OMiddleware::PHASE_AFTER_RESPONSE => []
]);
```

## 7. Route and Group Middlewares

Routes and `prefix()`, `layout()` and `group()` accept Middleware definitions.

Within each phase the order is:

```text
global
↓
outer group
↓
inner group
↓
route
```

## 8. Context from ORequest

```php
$login = $req->getMiddleware(
	'Login'
);

$id = $req->getMiddlewareValue(
	'Login',
	'id'
);
```

Missing context returns `[]`; a missing property returns `null`.

## 9. Context from DTOs

```php
#[ODTOField(
	required: true,
	middleware: 'Login',
	middlewareProperty: 'id'
)]
public ?int $idUser = null;
```

`middleware` and `middlewareProperty` must be declared together. Middleware source does not fall back to client input.

## 10. Error State in `afterResponse`

After an earlier stop, `afterResponse` can inspect:

```php
$data['is_error']
$data['error_phase']
$data['error_status_code']
$data['error_message']
```

## 11. Best Practices

- Use `before` for authentication, authorization and context.
- Use `afterRender` for pre-layout changes on traditional responses.
- Use `afterResponse` for final changes, auditing and logging.
- For streaming, change headers or status rather than `body`.
- Keep Middlewares focused and move complex business logic to services.
- Prefer `OMiddleware::PHASE_*`.
