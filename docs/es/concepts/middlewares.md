# Middlewares

Los Middlewares de **Osumi Framework** son clases reutilizables que participan en el ciclo de vida de una petición HTTP.

Pueden ejecutarse en tres fases:

- `before`: antes de ejecutar el componente de la ruta.
- `afterRender`: después de renderizar el componente y antes de aplicar el layout en respuestas tradicionales.
- `afterResponse`: después de preparar la respuesta final y antes de emitirla.

Se usan habitualmente para autenticación, autorización, validación de tokens, carga de contexto, modificación de respuestas, cabeceras, códigos de estado, auditoría y logging.

## 1. Estructura de un Middleware

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

Los Middlewares de aplicación se almacenan normalmente en `src/Middleware/`.

## 2. Fases

```php
OMiddleware::PHASE_BEFORE
OMiddleware::PHASE_AFTER_RENDER
OMiddleware::PHASE_AFTER_RESPONSE
```

Flujo tradicional:

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

En una respuesta streaming se omite el layout y `afterResponse` termina antes de comenzar a enviar bytes.

### `before`

Se ejecuta antes de instanciar el componente. Es la fase adecuada para autenticación, autorización, validación, bloqueo de peticiones y carga de contexto.

### `afterRender`

En respuestas tradicionales se ejecuta después de renderizar el componente y antes del layout. Puede inspeccionar o sustituir el cuerpo, añadir cabeceras o cambiar el código de estado.

En respuestas streaming también se ejecuta, pero todavía no existe un cuerpo materializado que pueda sustituirse.

### `afterResponse`

Es la última fase antes de emitir la respuesta. También se ejecuta cuando `before` o `afterRender` han detenido el pipeline.

## 3. Resultado de un Middleware

`handle()` siempre debe devolver un array. Un array vacío significa que no hay cambios.

Claves admitidas:

- `context`: publica datos para fases posteriores, `ORequest` y DTOs.
- `body`: sustituye el cuerpo en `afterRender` o `afterResponse`, salvo en respuestas streaming.
- `headers`: añade o sustituye cabeceras HTTP.
- `status_code`: entero entre `100` y `599`.
- `stop`: detiene la fase actual.
- `message`: mensaje usado al detener la petición.

### `context`

```php
return [
	'context' => [
		'id' => 42,
		'role' => 'admin'
	]
];
```

`LoginMiddleware` publica bajo el nombre `Login`.

### `body`

```php
return [
	'body' => 'Modified response'
];
```

En una respuesta streaming devolver `body` produce `InvalidArgumentException`, porque el stream no se materializa como string.

### `headers`

```php
return [
	'headers' => [
		'X-Request-Id' => 'abc123'
	]
];
```

### `status_code`

```php
return [
	'status_code' => 201
];
```

### `stop`

```php
return [
	'stop' => true,
	'status_code' => 403,
	'message' => 'Forbidden'
];
```

Si faltan los datos opcionales, `status_code` usa `500` y `message` usa `Middleware stopped execution.`.

## 4. Estado acumulado de la fase

Además de los datos de la petición, `$data` incluye el estado acumulado del pipeline, entre otros:

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

## 5. Respuestas streaming

Cuando el componente devuelve `OStreamResponse`:

```php
$data['is_streaming_response'] === true
```

durante `afterRender` y `afterResponse`.

Los Middlewares pueden seguir modificando:

- `context`
- `headers`
- `status_code`
- `stop`
- `message`

No pueden devolver `body`.

El framework no envía ningún byte del stream hasta que ambas fases han terminado. Si un Middleware devuelve `stop => true`, el stream se descarta, se restauran las cabeceras normales y se genera una respuesta de error tradicional.

Los layouts se omiten para respuestas streaming.

## 6. Middlewares globales

Se configuran en `src/Middleware/Middlewares.php`:

```php
OMiddleware::setGlobal([
	OMiddleware::PHASE_BEFORE => [],
	OMiddleware::PHASE_AFTER_RENDER => [],
	OMiddleware::PHASE_AFTER_RESPONSE => []
]);
```

## 7. Middlewares de ruta y grupo

Las rutas y `prefix()`, `layout()` y `group()` aceptan definiciones de Middlewares.

Dentro de cada fase el orden es:

```text
global
↓
grupo exterior
↓
grupo interior
↓
ruta
```

## 8. Contexto desde ORequest

```php
$login = $req->getMiddleware(
	'Login'
);

$id = $req->getMiddlewareValue(
	'Login',
	'id'
);
```

Un contexto inexistente devuelve `[]` y una propiedad inexistente devuelve `null`.

## 9. Contexto desde DTOs

```php
#[ODTOField(
	required: true,
	middleware: 'Login',
	middlewareProperty: 'id'
)]
public ?int $idUser = null;
```

`middleware` y `middlewareProperty` deben definirse juntos. El origen Middleware no usa como alternativa los datos del cliente.

## 10. Estado de error en `afterResponse`

Después de un `stop` anterior, `afterResponse` puede consultar:

```php
$data['is_error']
$data['error_phase']
$data['error_status_code']
$data['error_message']
```

## 11. Buenas prácticas

- Usa `before` para autenticación, autorización y contexto.
- Usa `afterRender` para cambios previos al layout en respuestas tradicionales.
- Usa `afterResponse` para cambios finales, auditoría y logging.
- En streaming, modifica cabeceras o estado, no `body`.
- Mantén los Middlewares pequeños y mueve la lógica de negocio compleja a servicios.
- Prefiere `OMiddleware::PHASE_*`.
