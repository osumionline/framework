# Osumi Framework – Contexto LLM (Todo en uno)

**Propósito**

Este documento proporciona un contexto compacto pero autoritativo para sistemas de IA que expliquen o generen código para **Osumi Framework 9.9**.

Cuando un comportamiento del framework no esté documentado aquí, no debe inventarse.

## 0. Identidad del framework

- **Nombre:** Osumi Framework
- **Versión:** 9.9.0
- **Lenguaje:** PHP
- **PHP mínimo:** 8.5+
- **Tipado:** usar `declare(strict_types=1);`
- **Estilo:** explícito, predecible y fuertemente tipado siempre que sea posible

## 1. Filosofía

Osumi Framework prioriza:

- código explícito
- ciclo de vida predecible
- separación clara de responsabilidades
- componentes pequeños
- servicios reutilizables
- DTOs tipados
- fases de Middleware explícitas
- mínimo comportamiento oculto

## 2. Ciclo de vida principal de una petición

Para una ruta encontrada normalmente:

```text
Petición del cliente
↓
Routing (ORoute)
↓
Middlewares before
↓
Componente / ORequest / DTO
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

Importante:

- Los DTOs se instancian únicamente cuando un componente declara una subclase de `ODTO` como parámetro de `run()`.
- `before` se ejecuta antes del componente.
- `afterRender` se ejecuta cuando ya existe el cuerpo del componente y antes del layout.
- `afterResponse` se ejecuta cuando ya existe el cuerpo final.
- `afterResponse` también se ejecuta tras un `stop` de `before` o `afterRender`.
- El tratamiento de 404, 405 y OPTIONS queda fuera de este pipeline de Middleware para rutas encontradas.

## 3. Routing (`ORoute`)

Las rutas asocian métodos HTTP y URL a componentes o vistas estáticas.

Métodos de ruta:

- `get()`
- `post()`
- `put()`
- `delete()`
- `view()`

Métodos de agrupación:

- `prefix()`
- `layout()`
- `group()`

Los prefijos son acumulativos y anidables. Las URL se normalizan.

Las definiciones de Middlewares de rutas y grupos son arrays agrupados por fase.

Ejemplo:

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

Los grupos anidados acumulan sus Middlewares.

Dentro de cada fase, el orden es:

```text
global
↓
grupo exterior
↓
grupo interior
↓
ruta
```

## 4. Middlewares (`OMiddleware`)

Los Middlewares son el mecanismo de interceptación de petición/respuesta de 9.9.

### 4.1 Fases

```php
OMiddleware::PHASE_BEFORE
OMiddleware::PHASE_AFTER_RENDER
OMiddleware::PHASE_AFTER_RESPONSE
```

### 4.2 Contrato de una clase Middleware

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

### 4.3 Claves de resultado

Un Middleware puede devolver:

- `context`: `array<string, mixed>`
- `body`: string
- `headers`: `array<string, string>`
- `status_code`: entero entre 100 y 599
- `stop`: boolean
- `message`: mensaje utilizado al detener la petición

Un array vacío significa que no hay cambios en el pipeline.

### 4.4 Contexto

El contexto se publica bajo el nombre público del Middleware.

`LoginMiddleware` se convierte en:

```text
Login
```

Ejemplo:

```php
return [
	'context' => [
		'id' => 42,
		'role' => 'admin'
	]
];
```

### 4.5 `body`

`body` tiene efecto en:

- `afterRender`: sustituye el cuerpo del componente antes del layout.
- `afterResponse`: sustituye el cuerpo final de la respuesta.

### 4.6 `stop`

Ejemplo:

```php
return [
	'stop' => true,
	'status_code' => 401,
	'message' => 'Unauthorized'
];
```

Semántica:

- `stop` en `before`:
  - se omiten los Middlewares `before` restantes
  - se omite el componente
  - se omite el layout
  - se genera un cuerpo de error tipado
  - `afterResponse` sigue ejecutándose

- `stop` en `afterRender`:
  - se omiten los Middlewares `afterRender` restantes
  - se omite el layout
  - se genera un cuerpo de error tipado
  - `afterResponse` sigue ejecutándose

- `stop` en `afterResponse`:
  - se omiten los Middlewares `afterResponse` restantes
  - su cuerpo de error tipado sustituye al cuerpo final
  - `afterResponse` no se vuelve a ejecutar de forma recursiva

Si se omiten al hacer `stop`:

- `status_code` usa `500`
- `message` usa `Middleware stopped execution.`

### 4.7 Estado de error disponible en `afterResponse`

Tras un `stop` anterior, los datos de la fase incluyen:

```php
$data['is_error']
$data['error_phase']
$data['error_status_code']
$data['error_message']
```

Esto permite que Middlewares de auditoría o logging conozcan cómo terminó la petición.

### 4.8 Middlewares globales

Se configuran en:

```text
src/Middleware/Middlewares.php
```

Ejemplo:

```php
OMiddleware::setGlobal([
	OMiddleware::PHASE_BEFORE => [],
	OMiddleware::PHASE_AFTER_RENDER => [],
	OMiddleware::PHASE_AFTER_RESPONSE => []
]);
```

## 5. Petición (`ORequest`)

`ORequest` proporciona acceso tipado a parámetros, cabeceras, archivos y contexto de Middlewares.

Contexto de Middleware:

```php
$login = $req->getMiddleware(
	'Login'
);

$id = $req->getMiddlewareValue(
	'Login',
	'id'
);
```

Comportamiento:

- contexto de Middleware inexistente → `[]`
- propiedad de contexto inexistente → `null`

## 6. DTOs (`ODTO`)

Los DTOs extienden `ODTO` y usan `#[ODTOField]`.

El framework:

1. instancia el DTO
2. carga sus campos
3. lo valida
4. lo inyecta en `run()`

Los DTOs se detectan por herencia de `ODTO`, no por namespace.

### 6.1 Parámetros de petición

Cuando no hay origen explícito, los valores se obtienen de la petición según el tipo declarado.

### 6.2 Origen de cabecera

```php
#[ODTOField(
	header: 'Authorization'
)]
public ?string $authorization = null;
```

### 6.3 Origen de Middleware

```php
#[ODTOField(
	required: true,
	middleware: 'Login',
	middlewareProperty: 'id'
)]
public ?int $idUser = null;
```

Reglas:

- `middleware` y `middlewareProperty` deben definirse juntos.
- Un mismo campo no puede combinar origen Middleware y origen cabecera.
- Si falta el contexto Middleware explícito, no se recurre a la entrada del cliente.
- Usa contexto de Middleware para valores de confianza como el ID del usuario autenticado.

### 6.4 Validación

La validación DTO soporta:

- `required`
- `requiredIf`

Uso:

```php
$dto->isValid();
$dto->getValidationErrors();
```

## 7. Componentes (`OComponent`)

Los componentes orquestan:

```text
petición → DTO/ORequest → servicios/modelos → propiedades públicas → plantilla
```

Mantén la lógica de negocio en servicios siempre que sea práctico.

Un componente de ruta puede definir exactamente una de estas firmas de `run()`:

```php
public function run(): void
```

```php
public function run(ORequest $req): void
```

```php
public function run(MyDTO $dto): void
```

Requisitos:

- `MyDTO` debe extender `ODTO`.
- El único parámetro, si existe, no puede ser nullable.
- Otros tipos de parámetros o firmas son inválidos.

## 8. Plantillas y pipes

Las plantillas pueden ser:

- `.php`
- `.html`
- `.json`
- `.xml`

Las plantillas estáticas usan:

```text
{{ variable }}
```

Pipes soportados:

- `date`
- `number`
- `string`
- `plain`
- `bool`

`string` aplica codificación URL.

`plain` devuelve una cadena entre comillas segura para JSON, sin codificación URL y conservando Unicode.

## 9. Layouts

Los layouts envuelven la salida del componente después de `afterRender`.

Orden relevante:

```text
cuerpo del componente
↓
afterRender
↓
layout
↓
afterResponse
```

Si `before` o `afterRender` hacen `stop`, el layout se omite.

## 10. Servicios (`OService`)

Los servicios contienen lógica de negocio/dominio reutilizable.

Úsalos para:

- operaciones reutilizables con modelos
- lógica de dominio de varios pasos
- integraciones externas
- comportamiento reutilizable de aplicación

No uses servicios como sustituto de Middlewares de petición/respuesta ni de validación DTO.

## 11. ORM (`OModel`)

Los modelos extienden `OModel` y utilizan atributos PHP.

Atributos habituales:

- `#[OPK]`
- `#[OField]`
- `#[OCreatedAt]`
- `#[OUpdatedAt]`

Usa tipos explícitos en las propiedades.

## 12. CLI (`OTask`)

Las tareas de aplicación extienden `OTask`.

CLI de aplicación:

```bash
php of <tarea>
```

Crear un Middleware:

```bash
php of add --option middleware --name Login
```

La antigua opción de creación `filter` no está soportada en 9.9.

## 13. Migraciones del framework

El paquete Composer expone:

```bash
php vendor/bin/ofw-migrate --help
```

Opciones soportadas:

```text
--from
--to
--dry-run
--force
--verbose
--no-interaction
--help
```

El estado de migración se almacena en:

```text
ofw/tmp/state.json
```

Las migraciones están versionadas y son idempotentes.

La integración con el updater de Composer puede ejecutar automáticamente las migraciones pendientes durante las actualizaciones del framework.

## 14. Migración de Filters antiguos

Los Filters son un concepto legacy anterior a 9.9.

Para código nuevo en 9.9:

- no crear clases Filter
- no usar `getFilter()`
- no usar `getFilters()`
- no usar `filter` / `filterProperty`
- usar Middlewares nativos y contexto de Middleware

El paso de migración de 9.9 puede conservar la lógica de negocio de los Filters antiguos generando adaptadores Middleware.

Por tanto, un proyecto migrado puede contener temporalmente clases Filter antiguas detrás de adaptadores Middleware generados. Esto es compatibilidad de migración, no la arquitectura recomendada para nuevas aplicaciones 9.9.

## 15. Convenciones estrictas

Preferir:

- `declare(strict_types=1);`
- tipos explícitos de parámetros y retorno
- propiedades tipadas
- PHPDoc completo en métodos
- clases y archivos PascalCase
- referencias `::class`
- constantes `OMiddleware::PHASE_*`
- gestión explícita de `null`
- clases pequeñas y enfocadas

## 16. No asumir

No inventar:

- helpers no documentados
- inyección automática en propiedades
- carga automática de relaciones
- serializadores ocultos
- nombres de contexto Middleware implícitos distintos de la regla del nombre de clase
- APIs legacy de Filter en código nuevo
- firmas de `run()` fuera de los contratos documentados

Cuando un detalle no esté aquí, usa la documentación actual como fuente autoritativa.
