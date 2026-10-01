# Routing

Routing in Osumi Framework is managed by the `ORoute` class. It maps incoming HTTP requests (URLs) to specific Components that act as actions.

Routes are typically defined in PHP files located within the `src/Routes/` directory. You can create multiple files in this folder to organize your routes logically (e.g., one file per module).

When a user accesses a URL, `ORoute` locates the path, resolves the effective Middleware pipeline, then instantiates the component and calls `run()`, passing a user-defined `DTO`, a generic `ORequest`, or no parameter depending on the component signature.

---

## Defining Routes

To define a route, use the static methods of `ORoute` corresponding to the HTTP verbs: `get()`, `post()`, `put()`, or `delete()`.

### Basic Syntax

```php
use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\App\Module\Home\Index\IndexComponent;

ORoute::get('/', IndexComponent::class);
```

### Route Parameters

Route methods such as `get()`, `post()`, `put()` and `delete()` accept:

- **URL (string)**: The path to respond to.
- **Component (string)**: The FQCN of the component to execute.
- **Middlewares (array, optional)**: Middleware classes grouped by execution phase.
- **Layout (string|null, optional)**: A specific layout component for the route.

---

## Middlewares

Middlewares participate in the request lifecycle in three phases:

- `before`
- `afterRender`
- `afterResponse`

See `/docs/en/concepts/middlewares.md` for the complete Middleware lifecycle and result format.

Example:

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

## Grouping Routes

Osumi Framework provides three ways to group routes that share common characteristics.

Middleware definitions assigned to route groups are accumulated with nested groups and with route-specific Middlewares.

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

### 1. Prefixes

Use prefixes when multiple routes share the same URL start. Prefixes can be nested; each nested prefix is appended to the active prefix.

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

This registers `/api/health`, `/api/admin/login`, and `/api/admin/me`.

### 2. Layouts

Use `ORoute::layout()` when multiple routes share the same visual structure.

```php
ORoute::layout(
	MainLayoutComponent::class,
	static function (): void {
		ORoute::get('/home', HomeComponent::class);
		ORoute::get('/contact', ContactComponent::class);
	}
);
```

`layout()` can also receive Middleware definitions as its third argument.

### 3. Groups (Prefix + Layout)

`ORoute::group()` combines a prefix and a layout assignment. Groups can be nested with other groups or prefixes.

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

The route above is registered at `/admin/users/profile` and uses `UserLayoutComponent`.

`group()` can receive Middleware definitions as its fourth argument.

### URL Normalization

All static `ORoute` methods normalize URLs. Leading slashes are made consistent, repeated slashes are collapsed, and trailing slashes are removed except for the root URL `/`.

For example:

```php
ORoute::prefix('/api/', static function (): void {
	ORoute::prefix('//admin///', static function (): void {
		ORoute::get('//users/', UsersComponent::class);
	});
});
```

This registers `/api/admin/users`.

---

## Static Views

Use `ORoute::view()` to serve a static file or a simple template without a full action component.

```php
ORoute::view('/about-us', 'about-us.html');
```

Static view routes can also receive Middleware definitions.

---

## Parameters on Routes

URLs can define parameters using the `:name` syntax.

```php
ORoute::get('/user/:id', UserComponent::class);
ORoute::get('/location/:name', LocationComponent::class);
```

A component using `run(ORequest $req)` can access them using methods such as `getParamInt('id')` or `getParamString('name')`.

---

## Summary of `ORoute` Methods

| Method     | Description |
| ---------- | ----------- |
| `get()`    | Registers a GET route. |
| `post()`   | Registers a POST route. |
| `put()`    | Registers a PUT route. |
| `delete()` | Registers a DELETE route. |
| `view()`   | Registers a route that renders a static file directly. |
| `prefix()` | Groups routes under a cumulative, nestable URL prefix and optional Middlewares. |
| `layout()` | Groups routes under a common layout and optional Middlewares. |
| `group()`  | Groups routes with a nestable prefix, layout and optional Middlewares. |

---

## Best Practices

- **Organize by file**: Create different files in `src/Routes/` for each module or functional area.
- **Use Middlewares**: Keep components clean by moving cross-cutting request/response logic to Middlewares.
- **Use phase constants**: Prefer `OMiddleware::PHASE_*` constants.
- **Class constants**: Use `::class` notation for components, layouts and Middlewares.
