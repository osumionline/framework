# Routing

Routing in Osumi Framework is managed by the `ORoute` class. It maps incoming HTTP requests (URLs) to specific Components that act as actions.

Routes are typically defined in PHP files located within the `src/Routes/` directory. You can create multiple files in this folder to organize your routes logically (e.g., one file per module).

When a user accesses a URL, `ORoute` locates the path, runs filters, then instantiates the component and calls `run()`, passing a user defined `DTO` or a generic `ORequest`.

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

- **URL (string)**: The path to respond to.
- **Component (string)**: The FQCN (Fully Qualified Class Name) of the component to execute.
- **Filters (array, optional)**: A list of filter classes to execute before the component.
- **Layout (string, optional)**: A specific layout component for this route.

---

## Filters

Filters are classes executed before the main component. They are commonly used for authentication (checking tokens), logging, or request validation.

Docs: /docs/concepts/filters.md

```php
use Osumi\OsumiFramework\App\Filter\LoginFilter;
use Osumi\OsumiFramework\App\Module\User\Profile\ProfileComponent;

ORoute::post('/profile', ProfileComponent::class, [LoginFilter::class]);

```

---

## Grouping Routes

Osumi Framework provides three ways to group routes that share common characteristics:

### 1. Prefixes

Used when multiple routes share the same URL start (e.g., an API).

```php
ORoute::prefix('/api', function() {
  ORoute::post('/login', LoginComponent::class);
  ORoute::post('/register', RegisterComponent::class);
});

```

### 2. Layouts

Used when multiple routes share the same visual structure (header, footer, etc.).

```php
ORoute::layout(MainLayoutComponent::class, function() {
  ORoute::get('/home', HomeComponent::class);
  ORoute::get('/contact', ContactComponent::class);
});

```

### 3. Groups (Prefix + Layout)

Combines both prefixing and layout assignment in a single block.

```php
ORoute::group('/admin', AdminLayoutComponent::class, function() {
  ORoute::get('/dashboard', DashboardComponent::class);
  ORoute::get('/settings', SettingsComponent::class);
});

```

---

## Static Views

If you need to serve a static file or a simple template without the logic of a full action component, use `ORoute::view()`.

```php
ORoute::view('/about-us', 'about-us.html');

```

## Parameters on routes

URLs can be defined to have parameters on them using the `:name` syntax.

```php
ORoute::get('/user/:id', UserComponent::class);
ORoute::get('/location/:name', LocationComponent::class);
```

The method `run(ORequest $req)` of the component can then access that parameter using methods such as `getParamInt('id')` or `getParamString('name')`.

---

## Summary of `ORoute` Methods

| Method     | Description                                            |
| ---------- | ------------------------------------------------------ |
| `get()`    | Registers a GET route.                                 |
| `post()`   | Registers a POST route.                                |
| `put()`    | Registers a PUT route.                                 |
| `delete()` | Registers a DELETE route.                              |
| `view()`   | Registers a route that renders a static file directly. |
| `prefix()` | Groups routes under a common URL prefix.               |
| `layout()` | Groups routes under a common layout component.         |
| `group()`  | Groups routes with both a prefix and a layout.         |

---

## Best Practices

- **Organize by file**: Create different files in `src/Routes/` for each module or functional area of your app.
- **Use Filters**: Keep your components clean by offloading authentication and validation logic to filters.
- **Class Constants**: Always use `::class` notation for components and filters to benefit from IDE autocompletion and static analysis.
