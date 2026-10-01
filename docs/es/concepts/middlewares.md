# Middlewares

Los Middlewares de **Osumi Framework** son clases reutilizables que participan en el ciclo de vida de una petición HTTP.

Pueden ejecutarse en tres fases:

- `before`: antes de ejecutar el componente de la ruta.
- `afterRender`: después de renderizar el componente y antes de aplicar el layout.
- `afterResponse`: después de generar el cuerpo final de la respuesta y justo antes de enviarlo.

Se usan habitualmente para autenticación, autorización, validación de tokens, carga de contexto, modificación de respuestas, cabeceras, códigos de estado, auditoría y logging.

## 1. Estructura de un Middleware

Los Middlewares de aplicación se almacenan normalmente en `src/Middleware/`.

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

Una misma clase puede registrarse en una o varias fases. `$phase` identifica la fase actual y `$data` contiene la información de la petición y el estado acumulado del pipeline.

## 2. Fases

Osumi Framework define:

```php
OMiddleware::PHASE_BEFORE
OMiddleware::PHASE_AFTER_RENDER
OMiddleware::PHASE_AFTER_RESPONSE
```

Orden de ejecución:

```text
Routing
↓
before
↓
Componente
↓
afterRender
↓
Layout
↓
afterResponse
↓
Respuesta HTTP
```

### `before`

Se ejecuta antes de instanciar el componente. Es la fase adecuada para autenticación, autorización, validación, bloqueo de peticiones y carga de contexto.

Puede detener el pipeline antes de ejecutar el componente.

### `afterRender`

Se ejecuta después de renderizar el template del componente y antes de aplicar el layout. Puede inspeccionar o sustituir el cuerpo del componente, añadir cabeceras o cambiar el código de estado.

Si detiene el pipeline, el layout no se renderiza.

### `afterResponse`

Se ejecuta después de generar el cuerpo final. Es adecuada para auditoría, logging, cambios finales de cabeceras y transformaciones finales de la respuesta.

También se ejecuta cuando `before` o `afterRender` han detenido el pipeline, de modo que puede inspeccionar el estado de error.

Si un Middleware de `afterResponse` detiene la ejecución, se omiten los Middlewares restantes de esa fase y su respuesta de error se envía directamente.

## 3. Resultado de un Middleware

`handle()` siempre debe devolver un array. Un array vacío significa que no hay cambios:

```php
return [];
```

Claves admitidas:

### `context`

Publica datos para Middlewares posteriores, `ORequest` y DTOs:

```php
return [
    'context' => [
        'id' => 42,
        'role' => 'admin'
    ]
];
```

El contexto se almacena con el nombre público del Middleware. `LoginMiddleware` se expone como `Login`.

### `body`

Sustituye un cuerpo de respuesta:

```php
return [
    'body' => 'Modified response'
];
```

En `afterRender` sustituye el cuerpo del componente. En `afterResponse` sustituye el cuerpo final.

### `headers`

Añade o sustituye cabeceras HTTP:

```php
return [
    'headers' => [
        'X-Request-Id' => 'abc123'
    ]
];
```

### `status_code`

Cambia el estado HTTP:

```php
return [
    'status_code' => 201
];
```

Los valores válidos están entre `100` y `599`.

### `stop`

Detiene la fase actual:

```php
return [
    'stop' => true,
    'status_code' => 403,
    'message' => 'Forbidden'
];
```

Cuando `stop` es `true`, no se ejecutan los Middlewares posteriores de esa fase y la petición entra en estado de error de Middleware. Si se omiten, `status_code` usa `500` y `message` usa `Middleware stopped execution.`

## 4. Ejemplo de autenticación

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

## 5. Middlewares globales

Se configuran en `src/Middleware/Middlewares.php`:

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

## 6. Middlewares de ruta

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

## 7. Middlewares de grupo

`prefix()`, `layout()` y `group()` aceptan definiciones de Middlewares.

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

Los grupos anidados acumulan sus Middlewares. En cada fase el orden es:

```text
global
↓
grupo exterior
↓
grupo interior
↓
ruta
```

## 8. Acceso al contexto desde ORequest

```php
$login = $req->getMiddleware(
    'Login'
);

$id = $req->getMiddlewareValue(
    'Login',
    'id'
);
```

Un contexto inexistente devuelve un array vacío. Una propiedad inexistente devuelve `null`.

## 9. Contexto de Middleware desde DTOs

```php
#[ODTOField(
    required: true,
    middleware: 'Login',
    middlewareProperty: 'id'
)]
public ?int $idUser = null;
```

`middleware` y `middlewareProperty` deben definirse juntos. El contexto de Middleware es un origen explícito y no usa como alternativa los datos enviados por el cliente.

## 10. Estado de error en afterResponse

Cuando `before` o `afterRender` detienen el pipeline, `afterResponse` continúa ejecutándose. Su `$data` contiene:

```php
$data['is_error']
$data['error_phase']
$data['error_status_code']
$data['error_message']
```

## 11. Buenas prácticas

- Usa `before` para autenticación, autorización y contexto.
- Usa `afterRender` cuando necesites el cuerpo del componente antes del layout.
- Usa `afterResponse` para transformaciones finales, auditoría y logging.
- Mantén los Middlewares pequeños y con una responsabilidad clara.
- Mueve la lógica de negocio compleja a servicios.
- Publica solo el contexto necesario.
- Usa nombres `XxxMiddleware`.
- Prefiere las constantes `OMiddleware::PHASE_*`.
- Usa el contexto de Middleware en DTOs para valores internos de confianza, como el ID del usuario autenticado.

## 12. Flujo completo

```text
Petición del cliente
↓
Routing
↓
Middlewares before globales
↓
Middlewares before de grupos
↓
Middlewares before de ruta
↓
Componente / DTO / ORequest
↓
Renderizado del componente
↓
Middlewares afterRender
↓
Renderizado del layout
↓
Middlewares afterResponse
↓
Respuesta HTTP
```

Un `stop` en `before` o `afterRender` omite el procesamiento normal restante pero continúa hasta `afterResponse`.

Un `stop` dentro de `afterResponse` termina esa fase final y envía directamente su respuesta de error.
