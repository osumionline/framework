# Layouts

In **Osumi Framework**, a **layout** is a special kind of component that wraps the output of the main action component.

Layouts are typically used to share the same HTML structure across multiple routes (for example: `<head>`, metadata, header/footer, script/style injection, etc.).

A layout is applied **after** the route component is executed and rendered, and it receives the rendered output as its `body`.

---

## 1. How Layouts Work (Render Flow)

When a route is matched, Osumi Framework executes this sequence:

1. The route is resolved (Routing).
2. Filters are executed (if any).
3. The route component is instantiated and rendered.
4. If a layout is defined for the route, the layout is instantiated and receives:
    - `title`: default title from config
    - `body`: the rendered output from the route component
5. The layout template is rendered, producing the final response.

This means:

- Your **action component** focuses on producing the **page content**.
- Your **layout** provides the shared structure and wraps that content.

---

## 2. Default Layout

When you create a new Osumi Framework project, a default layout is generated.

### 2.1 Default Layout Component

`DefaultLayoutComponent` is a very simple component that only defines the public properties used by its template:

- `title`: page title
- `body`: HTML content from the action component

### 2.2 Default Layout Template

The default layout template contains a standard HTML skeleton and uses two placeholders:

- `{{title}}` → inserted in `<title>`
- `{{body}}` → inserted in the `<body>`

This makes the default layout a generic wrapper for most server-rendered pages.

---

## 3. Defining a Layout in Routing (IMPORTANT)

You can assign a layout in routing in two ways:

### 3.1 Layout Group

Use `ORoute::layout()` to apply a layout to multiple routes:

```php
use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\App\Layout\MainLayoutComponent;

ORoute::layout(MainLayoutComponent::class, function() {
  ORoute::get('/home', HomeComponent::class);
  ORoute::get('/contact', ContactComponent::class);
});
```

### 3.2 Layout + Prefix Group

Use `ORoute::group()` to combine a URL prefix and a layout:

```php
use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\App\Layout\AdminLayoutComponent;

ORoute::group('/admin', AdminLayoutComponent::class, function() {
  ORoute::get('/dashboard', DashboardComponent::class);
  ORoute::get('/settings', SettingsComponent::class);
});
```

> This is the recommended way to apply a custom layout consistently to an area of your app.

---

## 4. CSS / JS Injection

Layouts are also the place where Osumi Framework injects CSS and JS resources.

When the layout output contains a `</head>` tag, the framework automatically inserts:

- Inline CSS (`<style>...</style>`) from configured files
- Inline JS (`<script>...</script>`) from configured files
- External CSS (`<link ...>`) from config `ext_css_list`
- External JS (`<script src=...>`) from config `ext_js_list`

This makes the layout the natural point where global frontend assets are assembled.

---

## 5. Best Practices

- Keep layouts purely structural (HTML skeleton + shared UI).
- Do not put business logic in layouts.
- Use a dedicated layout per section if needed (e.g. `MainLayout`, `AdminLayout`).
- Prefer `ORoute::layout()` / `ORoute::group()` for consistency.

---

## 6. Summary

- Layouts wrap the rendered output of route components.
- Layouts receive `title` and `body`.
- A default layout is generated in new projects.
- You can define a **custom layout in routing** using `ORoute::layout()` or `ORoute::group()`.
- Layouts are where global CSS/JS injection happens.
