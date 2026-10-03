# Layouts

In **Osumi Framework**, a layout is a special component that wraps the output of the main route component.

Layouts are typically used to share the same HTML structure across multiple routes.

A layout is applied after the route component and the `afterRender` Middleware phase. It receives the current component output as its `body`.

`OStreamResponse` responses are an exception: they do not have a materialized body that can be wrapped and **do not use a layout**.

---

## 1. Render Flow

For a traditional response:

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

For a streamed response:

```text
Routing
↓
before Middlewares
↓
Component → OStreamResponse
↓
afterRender Middlewares
↓
afterResponse Middlewares
↓
Stream emission
```

This means:

- `afterRender` runs before layout processing for traditional responses.
- The layout wraps the resulting content.
- `afterResponse` runs after the traditional final body is produced.
- For streaming, the layout is skipped and `afterResponse` completes before the first byte is sent.

If `before` stops execution, the component and layout are skipped.

If `afterRender` stops execution, the layout is skipped.

---

## 2. Default Layout

New projects include a default layout. `DefaultLayoutComponent` normally exposes `title` and `body`.

---

## 3. Defining Layouts in Routing

```php
ORoute::layout(
	MainLayoutComponent::class,
	static function (): void {
		ORoute::get('/home', HomeComponent::class);
		ORoute::get('/contact', ContactComponent::class);
	}
);
```

`ORoute::layout()` accepts an optional third Middleware argument.

```php
ORoute::group(
	'/admin',
	AdminLayoutComponent::class,
	static function (): void {
		ORoute::get('/dashboard', DashboardComponent::class);
	}
);
```

`ORoute::group()` accepts an optional fourth Middleware argument.

Even when a route belongs to a layout group, the layout is skipped if its component returns `OStreamResponse`.

See `/docs/en/concepts/middlewares.md`.

---

## 4. CSS / JS Injection

Layouts are where Osumi Framework injects configured frontend resources into documents containing `</head>`.

This injection does not apply to streamed responses.

---

## 5. Best Practices

- Keep layouts focused on structure and presentation.
- Do not place business logic in layouts.
- Use dedicated layouts for different application areas where useful.
- Do not rely on a layout for headers required by a streamed download; define them in `OStreamResponse` or Middlewares.

---

## 6. Summary

- Layouts wrap traditional component responses.
- `afterRender` runs before the layout.
- `afterResponse` runs after the traditional final body.
- A `stop` in `before` or `afterRender` skips layout rendering.
- `OStreamResponse` always skips the layout.
