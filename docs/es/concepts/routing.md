# Enrutamiento

El enrutamiento en Osumi Framework se gestiona mediante la clase `ORoute`. Esta clase asigna las solicitudes HTTP entrantes (URL) a componentes específicos que actúan como acciones.

Las rutas se definen normalmente en archivos PHP ubicados en el directorio `src/Routes/`. Se pueden crear varios archivos en esta carpeta para organizar las rutas de forma lógica, por ejemplo, un archivo por módulo.

Cuando un usuario accede a una URL, `ORoute` localiza la ruta, resuelve el pipeline efectivo de Middlewares, instancia el componente y llama a `run()`, pasando un `DTO` definido por el usuario, un `ORequest` genérico o ningún parámetro, según la firma del componente.

---

## Definición de rutas

Para definir una ruta, utiliza los métodos estáticos de `ORoute` correspondientes a los verbos HTTP: `get()`, `post()`, `put()` o `delete()`.

### Sintaxis básica

```php
use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\App\Module\Home\Index\IndexComponent;

ORoute::get('/', IndexComponent::class);
```

### Parámetros de ruta

Los métodos de ruta como `get()`, `post()`, `put()` y `delete()` aceptan:

- **URL (string)**: La ruta a la que se responde.
- **Componente (string)**: El FQCN del componente que se ejecutará.
- **Middlewares (array, opcional)**: Clases Middleware agrupadas por fase de ejecución.
- **Layout (string|null, opcional)**: Un componente de layout específico para la ruta.

---

## Middlewares

Los Middlewares participan en el ciclo de vida de la petición en tres fases:

- `before`
- `afterRender`
- `afterResponse`

Consulta `/docs/es/concepts/middlewares.md` para ver el ciclo de vida completo y el formato de resultado de los Middlewares.

Ejemplo:

```php
use Osumi\OsumiFramework\App\Middleware\AuditMiddleware;
use Osumi\OsumiFramework\App\Middleware\LoginMiddleware;
use Osumi\OsumiFramework\App\Module\User\Profile\ProfileComponent;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Routing\ORoute;

ORoute::post(
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

---

## Agrupación de rutas

Osumi Framework ofrece tres formas de agrupar rutas que comparten características comunes.

Las definiciones de Middlewares asignadas a grupos de rutas se acumulan con los grupos anidados y con los Middlewares específicos de cada ruta.

Dentro de cada fase, el orden de ejecución es:

```text
global
↓
grupo exterior
↓
grupo interior
↓
ruta
```

### 1. Prefijos

Se utilizan cuando varias rutas comparten el mismo inicio de URL. Los prefijos pueden anidarse; cada prefijo anidado se añade al prefijo activo.

```php
use Osumi\OsumiFramework\App\Middleware\AdminAuthMiddleware;
use Osumi\OsumiFramework\Core\OMiddleware;

ORoute::prefix(
	'/api',
	static function (): void {
		ORoute::get('/health', HealthComponent::class);

		ORoute::prefix(
			'/admin',
			static function (): void {
				ORoute::post('/login', LoginComponent::class);
				ORoute::get('/me', MeComponent::class);
			},
			[
				OMiddleware::PHASE_BEFORE => [
					AdminAuthMiddleware::class
				]
			]
		);
	}
);
```

Esto registra `/api/health`, `/api/admin/login` y `/api/admin/me`.

### 2. Layouts

Usa `ORoute::layout()` cuando varias rutas comparten la misma estructura visual.

```php
ORoute::layout(
	MainLayoutComponent::class,
	static function (): void {
		ORoute::get('/home', HomeComponent::class);
		ORoute::get('/contact', ContactComponent::class);
	}
);
```

`layout()` también puede recibir definiciones de Middlewares como tercer argumento.

### 3. Grupos (prefijo + layout)

`ORoute::group()` combina un prefijo y un layout. Los grupos pueden anidarse con otros grupos o prefijos.

```php
ORoute::group(
	'/admin',
	AdminLayoutComponent::class,
	static function (): void {
		ORoute::group(
			'/users',
			UserLayoutComponent::class,
			static function (): void {
				ORoute::get('/profile', ProfileComponent::class);
			}
		);
	}
);
```

La ruta anterior se registra como `/admin/users/profile` y utiliza `UserLayoutComponent`.

`group()` también puede recibir definiciones de Middlewares como cuarto argumento.

### Normalización de URL

Todos los métodos estáticos de `ORoute` normalizan las URL. Las barras iniciales se unifican, las barras repetidas se reducen a una sola y las barras finales se eliminan, excepto en la URL raíz `/`.

Por ejemplo:

```php
ORoute::prefix('/api/', static function (): void {
	ORoute::prefix('//admin///', static function (): void {
		ORoute::get('//users/', UsersComponent::class);
	});
});
```

Esto registra `/api/admin/users`.

---

## Vistas estáticas

Usa `ORoute::view()` para servir un archivo estático o una plantilla sencilla sin un componente de acción completo.

```php
ORoute::view('/about-us', 'about-us.html');
```

Las rutas de vista estática también pueden recibir definiciones de Middlewares.

---

## Parámetros en las rutas

Las URL pueden definir parámetros mediante la sintaxis `:name`.

```php
ORoute::get('/user/:id', UserComponent::class);
ORoute::get('/location/:name', LocationComponent::class);
```

Un componente que use `run(ORequest $req)` puede acceder a ellos mediante métodos como `getParamInt('id')` o `getParamString('name')`.

---

## Resumen de los métodos de `ORoute`

| Método     | Descripción |
| ---------- | ----------- |
| `get()`    | Registra una ruta GET. |
| `post()`   | Registra una ruta POST. |
| `put()`    | Registra una ruta PUT. |
| `delete()` | Registra una ruta DELETE. |
| `view()`   | Registra una ruta que renderiza directamente un archivo estático. |
| `prefix()` | Agrupa rutas bajo un prefijo acumulativo y anidable y Middlewares opcionales. |
| `layout()` | Agrupa rutas bajo un layout común y Middlewares opcionales. |
| `group()`  | Agrupa rutas con un prefijo anidable, layout y Middlewares opcionales. |

---

## Mejores prácticas

- **Organiza por archivo**: Crea archivos diferentes en `src/Routes/` para cada módulo o área funcional.
- **Usa Middlewares**: Mantén los componentes limpios trasladando la lógica transversal de petición/respuesta a Middlewares.
- **Usa constantes de fase**: Prefiere las constantes `OMiddleware::PHASE_*`.
- **Constantes de clase**: Usa `::class` para componentes, layouts y Middlewares.
