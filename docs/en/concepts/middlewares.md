# Middlewares

Middlewares in **Osumi Framework** are reusable classes that participate in the HTTP request lifecycle.

They can run in three phases:

- `before`: before the route component is executed.
- `afterRender`: after the component has been rendered and before the layout is applied.
- `afterResponse`: after the final response body has been produced and immediately before it is emitted.

Typical uses include authentication, authorization, token validation, loading request context, modifying response bodies, adding headers, changing status codes, auditing and logging.

## 1. Middleware structure

Application middlewares are normally stored in `src/Middleware/`.

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

The same middleware class may be registered in one or more phases. `$phase` identifies the current phase and `$data` contains request information plus the accumulated middleware state.

## 2. Middleware phases

Osumi Framework defines:

```php
OMiddleware::PHASE_BEFORE
OMiddleware::PHASE_AFTER_RENDER
OMiddleware::PHASE_AFTER_RESPONSE
```

Execution order:

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

### `before`

Runs before the component is instantiated. Use it for authentication, authorization, validation, request blocking and context loading.

A `before` middleware can stop the pipeline before the component runs.

### `afterRender`

Runs after the component template is rendered and before the layout is applied. It may inspect or replace the rendered component body, add headers or change the status code.

If it stops the pipeline, the layout is skipped.

### `afterResponse`

Runs after the final response body has been produced. It is suitable for auditing, logging, final header changes and final response transformations.

`afterResponse` still runs when `before` or `afterRender` stopped the normal pipeline, so it can inspect the middleware error state.

If an `afterResponse` middleware stops execution, remaining middlewares in that phase are skipped and its error response is emitted directly.

## 3. Middleware result

`handle()` must return an array. An empty array means no changes:

```php
return [];
```

Supported result keys are:

### `context`

Publishes data for later middlewares, `ORequest` and DTOs:

```php
return [
    'context' => [
        'id' => 42,
        'role' => 'admin'
    ]
];
```

Context is stored using the public middleware name. `LoginMiddleware` is exposed as `Login`.

### `body`

Replaces a response body:

```php
return [
    'body' => 'Modified response'
];
```

In `afterRender` it replaces the component body. In `afterResponse` it replaces the final body.

### `headers`

Adds or replaces HTTP response headers:

```php
return [
    'headers' => [
        'X-Request-Id' => 'abc123'
    ]
];
```

### `status_code`

Changes the HTTP status:

```php
return [
    'status_code' => 201
];
```

Valid status codes are between `100` and `599`.

### `stop`

Stops the current phase:

```php
return [
    'stop' => true,
    'status_code' => 403,
    'message' => 'Forbidden'
];
```

When `stop` is `true`, later middlewares in the same phase are skipped and the request enters middleware error state. If omitted, `status_code` defaults to `500` and `message` defaults to `Middleware stopped execution.`

## 4. Example authentication middleware

```php
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Middleware;

use Osumi\OsumiFramework\Core\OMiddleware;

final class LoginMiddleware {
    /**
     * Validate the request and publish authenticated user context.
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
        if ($phase !== OMiddleware::PHASE_BEFORE) {
            return [];
        }

        $headers = $data['headers'];

        if (
            !is_array($headers) ||
            !array_key_exists('Authorization', $headers)
        ) {
            return [
                'stop' => true,
                'status_code' => 401,
                'message' => 'Unauthorized'
            ];
        }

        return [
            'context' => [
                'id' => 42,
                'role' => 'admin'
            ]
        ];
    }
}
```

## 5. Global middlewares

Global middlewares are configured in `src/Middleware/Middlewares.php`:

```php
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Middleware;

use Osumi\OsumiFramework\Core\OMiddleware;

OMiddleware::setGlobal([
    OMiddleware::PHASE_BEFORE => [
        RequestMiddleware::class
    ],
    OMiddleware::PHASE_AFTER_RENDER => [],
    OMiddleware::PHASE_AFTER_RESPONSE => [
        AuditMiddleware::class
    ]
]);
```

## 6. Route middlewares

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

## 7. Group middlewares

`prefix()`, `layout()` and `group()` accept middleware definitions.

```php
ORoute::prefix(
    '/api',
    static function (): void {
        ORoute::get(
            '/profile',
            ProfileComponent::class
        );
    },
    [
        OMiddleware::PHASE_BEFORE => [
            ApiMiddleware::class
        ]
    ]
);
```

Nested groups accumulate middlewares. Per phase, execution order is:

```text
global
↓
outer group
↓
inner group
↓
route
```

## 8. Accessing middleware context from ORequest

```php
$login = $req->getMiddleware(
    'Login'
);

$id = $req->getMiddlewareValue(
    'Login',
    'id'
);
```

Missing middleware context returns an empty array. A missing context value returns `null`.

## 9. Using middleware context from DTOs

```php
#[ODTOField(
    required: true,
    middleware: 'Login',
    middlewareProperty: 'id'
)]
public ?int $idUser = null;
```

`middleware` and `middlewareProperty` must be defined together. Middleware context is an explicit source and does not fall back to client input.

## 10. Error state in afterResponse

When `before` or `afterRender` stops the pipeline, `afterResponse` still runs. Its `$data` contains:

```php
$data['is_error']
$data['error_phase']
$data['error_status_code']
$data['error_message']
```

## 11. Best practices

- Use `before` for authentication, authorization and request context.
- Use `afterRender` only when you need the rendered component body before layout processing.
- Use `afterResponse` for final transformations, auditing and logging.
- Keep middlewares small and focused.
- Move complex business logic to services.
- Publish only context needed downstream.
- Name middleware classes `XxxMiddleware`.
- Prefer `OMiddleware::PHASE_*` constants.
- Use DTO middleware sources for trusted server-side values such as authenticated user IDs.

## 12. Full request flow

```text
Client request
↓
Routing
↓
Global before middlewares
↓
Group before middlewares
↓
Route before middlewares
↓
Component / DTO / ORequest
↓
Component rendering
↓
afterRender middlewares
↓
Layout rendering
↓
afterResponse middlewares
↓
HTTP response
```

A `stop` in `before` or `afterRender` skips the remaining normal processing but still reaches `afterResponse`.

A `stop` inside `afterResponse` terminates that final phase and emits its error response directly.
