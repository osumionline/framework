# Layouts

In **Osumi Framework**, a layout is a special component that wraps the output of the main route component.

Layouts are typically used to share the same HTML structure across multiple routes.

A layout is applied after the route component and the `afterRender` Middleware phase. It receives the current component output as its `body`.

---

## 1. Render Flow

For a normal matched route, the relevant flow is:

```text
Routing
↓
before Middlewares
↓
Component
↓
afterRender Middlewares
↓
Layout
↓
afterResponse Middlewares
↓
HTTP response
```

This means:

- The action component produces page content.
- `afterRender` Middlewares may inspect or replace that content before the layout.
- The layout wraps the resulting content.
- `afterResponse` Middlewares run after the final body has been produced.

If a `before` Middleware stops execution, the component and layout are skipped.

If an `afterRender` Middleware stops execution, the layout is skipped.

In both cases, `afterResponse` still runs before the response is emitted.

---

## 2. Default Layout

New projects include a default layout.

### Default Layout Component

`DefaultLayoutComponent` exposes:

- `title`
- `body`

### Default Layout Template

The default template uses:

- `{{title}}`
- `{{body}}`

---

## 3. Defining Layouts in Routing

### Layout Group

```php
use Osumi\OsumiFramework\App\Layout\MainLayoutComponent;
use Osumi\OsumiFramework\Routing\ORoute;

ORoute::layout(
	MainLayoutComponent::class,
	static function (): void {
		ORoute::get('/home', HomeComponent::class);
		ORoute::get('/contact', ContactComponent::class);
	}
);
```

`ORoute::layout()` accepts an optional third argument containing Middleware definitions.

### Layout + Prefix Group

```php
ORoute::group(
	'/admin',
	AdminLayoutComponent::class,
	static function (): void {
		ORoute::get('/dashboard', DashboardComponent::class);
		ORoute::get('/settings', SettingsComponent::class);
	}
);
```

`ORoute::group()` accepts an optional fourth argument containing Middleware definitions.

See `/docs/en/concepts/middlewares.md`.

---

## 4. CSS / JS Injection

Layouts are the point where Osumi Framework injects configured frontend resources into documents containing `</head>`.

---

## 5. Best Practices

- Keep layouts structural.
- Do not place business logic in layouts.
- Use dedicated layouts for different application areas where useful.
- Prefer `ORoute::layout()` and `ORoute::group()` for consistent routing configuration.
- Use `afterRender` Middlewares when output must be changed before layout wrapping.

---

## 6. Summary

- Layouts wrap route component output.
- `afterRender` executes before the layout.
- `afterResponse` executes after the final body is produced.
- A stopped `before` or `afterRender` phase skips layout rendering.
- Route groups can combine layouts and Middlewares.
