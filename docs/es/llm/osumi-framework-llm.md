# Osumi Framework – Contexto LLM (Todo en uno)

**Propósito**

Este documento proporciona un contexto compacto pero autoritativo para sistemas de IA que expliquen o generen código para **Osumi Framework 9.10**.

Cuando un comportamiento del framework no esté documentado aquí, no debe inventarse.

## 0. Identidad del framework

- **Nombre:** Osumi Framework
- **Versión:** 9.10.1
- **Lenguaje:** PHP
- **PHP mínimo:** 8.5+
- **Tipado:** usar `declare(strict_types=1);`
- **Estilo:** explícito, predecible y fuertemente tipado siempre que sea posible

## 1. Filosofía

Osumi Framework prioriza código explícito, ciclo de vida predecible, separación clara de responsabilidades, componentes pequeños, servicios reutilizables, DTOs tipados, fases de Middleware explícitas y mínimo comportamiento oculto.

## 2. Ciclo de vida principal

Respuesta tradicional:

```text
Petición
↓
Routing
↓
before
↓
Componente / ORequest / DTO
↓
Renderizado
↓
afterRender
↓
Layout
↓
afterResponse
↓
Respuesta HTTP
```

Respuesta streaming:

```text
Petición
↓
Routing
↓
before
↓
Componente → OStreamResponse
↓
afterRender
↓
afterResponse
↓
Cerrar conexiones BD
↓
Cabeceras HTTP
↓
Stream por bloques
```

En streaming no se aplica layout y no se envía ningún byte antes de completar los Middlewares posteriores al componente.

404, 405 y OPTIONS quedan fuera del pipeline normal de Middleware de rutas encontradas.

## 3. Routing (`ORoute`)

Métodos: `get()`, `post()`, `put()`, `delete()`, `view()`.

Agrupación: `prefix()`, `layout()`, `group()`.

Los Middlewares se agrupan por fase y los grupos anidados los acumulan en orden global → grupo exterior → grupo interior → ruta.

## 4. Middlewares (`OMiddleware`)

Fases:

```php
OMiddleware::PHASE_BEFORE
OMiddleware::PHASE_AFTER_RENDER
OMiddleware::PHASE_AFTER_RESPONSE
```

Contrato:

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

Claves de resultado: `context`, `body`, `headers`, `status_code`, `stop`, `message`.

`body` solo puede reemplazar cuerpos materializados. En una respuesta streaming devolver `body` es inválido.

Datos de fase relevantes:

```php
$data['is_streaming_response']
$data['is_error']
$data['error_phase']
$data['error_status_code']
$data['error_message']
```

Durante `afterRender` y `afterResponse` de un `OStreamResponse`, `is_streaming_response` vale `true`. Los Middlewares pueden cambiar contexto, cabeceras y estado, o detener la respuesta antes de comenzar la emisión.

## 5. Petición (`ORequest`)

Acceso tipado a parámetros, cabeceras, archivos y contexto Middleware mediante `getMiddleware()` y `getMiddlewareValue()`.

## 6. DTOs (`ODTO`)

Los DTO extienden `ODTO` y usan `#[ODTOField]`. Pueden obtener valores desde petición, cabeceras o contexto Middleware. `middleware` y `middlewareProperty` deben definirse juntos y no hacen fallback a datos del cliente.

## 7. Componentes (`OComponent`)

Parámetros admitidos por `run()`:

```php
public function run(): void
public function run(ORequest $req): void
public function run(MyDTO $dto): void
```

También pueden devolver streaming:

```php
public function run(): OStreamResponse
public function run(ORequest $req): OStreamResponse
public function run(MyDTO $dto): OStreamResponse
```

Si un componente no tiene plantilla, `run()` debe declarar explícitamente `OStreamResponse` y devolverlo.

Un `OStreamResponse` no puede anidarse dentro de una plantilla ni convertirse a string.

## 8. `OStreamResponse`

`Osumi\OsumiFramework\Web\OStreamResponse` encapsula:

- un stream legible;
- `array<string, string>` de cabeceras;
- código HTTP, por defecto 200;
- tamaño de bloque, por defecto 1 MiB;
- propiedad/cierre automático del stream, por defecto `true`.

El framework valida recurso, legibilidad, cabeceras, estado y tamaño de bloque. Los streams administrados por el framework se cierran al terminar, descartarse o destruirse la respuesta.

## 9. Plantillas y pipes

Plantillas: `.php`, `.html`, `.json`, `.xml`.

Pipes: `date`, `number`, `string`, `plain`, `bool`.

## 10. Layouts

Los layouts envuelven respuestas tradicionales después de `afterRender`. Nunca se aplican a `OStreamResponse`.

## 11. Servicios (`OService`)

Usar para lógica de negocio/dominio reutilizable, operaciones de varios pasos e integraciones externas.

## 12. ORM (`OModel`)

Modelos con atributos como `#[OPK]`, `#[OField]`, `#[OCreatedAt]`, `#[OUpdatedAt]`.

## 13. CLI y migraciones

CLI de aplicación: `php of <tarea>`.

Migrador: `php vendor/bin/ofw-migrate --help`.

Opciones: `--from`, `--to`, `--dry-run`, `--force`, `--verbose`, `--no-interaction`, `--help`.

Estado: `ofw/tmp/state.json`.

## 14. Filters legacy

Los Filters son anteriores a 9.9 y no forman parte de la API runtime actual. Código nuevo debe usar Middlewares. El migrador de 9.9 puede conservar lógica antigua mediante adaptadores.

## 15. Convenciones estrictas

Preferir `declare(strict_types=1);`, tipos explícitos, propiedades tipadas, PHPDoc completo en métodos, `::class`, `OMiddleware::PHASE_*`, gestión explícita de `null` y clases pequeñas.

## 16. No asumir

No inventar helpers, inyección automática en propiedades, carga automática de relaciones, serializadores ocultos, APIs legacy de Filter ni firmas de `run()` fuera de los contratos documentados.
