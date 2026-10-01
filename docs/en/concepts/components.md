# Components

Components in Osumi Framework are small, reusable pieces of code that render a template. A component is composed of:

- A PHP class extending `OComponent`.
- A template file (`php`, `html`, `json` or `xml`, depending on usage).

A component instance is created, properties are assigned, and then the component is rendered.

---

## Basic Component Structure

### Component Class

```php
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Email\LostPassword;

use Osumi\OsumiFramework\Core\OComponent;

class LostPasswordComponent extends OComponent {
	public ?string $token = null;
}
```

### Template File

```html
<div>
	Token: {{ token }}
</div>
```

---

## Advanced Features

### Automatic Content-Type Headers

When a component is used as the main action for a URL, the framework automatically sends the appropriate `Content-Type` header based on the template extension:

- `.json`: `application/json`.
- `.xml`: `application/xml`.
- `.html` / `.php`: `text/html`.

### Component Nesting

Components can be nested to promote reusability.

```php
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Father;

use Osumi\OsumiFramework\App\Component\Child\ChildComponent;
use Osumi\OsumiFramework\Core\OComponent;

class FatherComponent extends OComponent {
	public ?ChildComponent $child = null;

	public function run(): void {
		$this->child = new ChildComponent();
		$this->child->name = 'Child Name';
	}
}
```

### Template Syntax and Access

1. **PHP templates (`.php`)** can execute PHP and access public properties as variables.
2. **Static/structured templates (`.html`, `.json`, `.xml`)** use `{{ variable_name }}`.

---

## The `run()` Method

A component can define an optional `run()` method.

When the component is used as a route action, exactly these signatures are supported:

```php
public function run(): void
```

```php
public function run(ORequest $req): void
```

```php
public function run(MyDTO $dto): void
```

Behavior:

- `run()` receives no request data.
- `run(ORequest $req)` receives the current request.
- `run(MyDTO $dto)` receives a DTO populated from the current request. `MyDTO` must extend `ODTO`.
- DTOs are recognized through inheritance from `ODTO`, not through their namespace.
- No other signatures are supported.
- The single parameter, when present, must be non-nullable.

`ORequest` provides typed request accessors such as:

- `getParamString('name')`
- `getParamInt('name')`
- `getParamFloat('name')`
- `getParamBool('name')`

### Middleware Context

`ORequest` also exposes context published by executed Middlewares.

Get all context published by one Middleware:

```php
public function run(ORequest $req): void {
	$login = $req->getMiddleware(
		'Login'
	);
}
```

Get one value:

```php
public function run(ORequest $req): void {
	$id = $req->getMiddlewareValue(
		'Login',
		'id'
	);
}
```

`LoginMiddleware` is exposed through the public name `Login`.

A missing Middleware context returns an empty array, while a missing context value returns `null`.

See `/docs/en/concepts/middlewares.md`.

### Examples

```php
class BooksComponent extends OComponent {
	public array $books = [];

	public function run(): void {
		$this->books = [
			'Book A',
			'Book B'
		];
	}
}
```

```php
class GetBookComponent extends OComponent {
	public ?Book $book = null;

	public function run(ORequest $req): void {
		$id_book = $req->getParamInt(
			'id'
		);

		$this->book = Book::findOne([
			'id' => $id_book
		]);
	}
}
```

---

## Accessing Global Options

Components can access framework services such as:

- `getConfig()`: global `OConfig`.
- `getLog()`: component `OLog`.
- `getSession()`: `OSession`.

---

## Rendering Components

```php
$cmp = new BooksComponent();
echo strval($cmp);
```

---

# Template Pipes

Osumi Framework templates support Angular-style pipes.

### Syntax

```text
{{ value | pipeName }}
{{ value | pipeName:param }}
{{ value | pipeName:param1:param2 }}
```

Pipes are processed by `OPipeFunctions`.

## `date`

Formats a date string.

```text
{{ user.created_at | date }}
{{ user.created_at | date:"d/m/Y" }}
```

Default format:

```text
d/m/Y H:i:s
```

## `number`

Uses PHP `number_format()`.

```text
{{ price | number }}
{{ price | number:2 }}
{{ price | number:2:".":"," }}
```

## `string`

Applies `urlencode()` and returns a quoted string.

```text
{{ user.name | string }}
```

Example:

```text
John Doe → "John+Doe"
```

## `plain`

Encodes a string as a JSON-safe quoted value without URL encoding.

```text
{{ user.name | plain }}
```

Behavior:

- `null` → `null`
- Unicode is preserved.
- Slashes are not escaped.
- JSON-sensitive characters are escaped correctly.

Examples:

```text
John Doe → "John Doe"
He said "hello" → "He said \"hello\""
```

This pipe is especially useful in JSON templates.

## `bool`

Produces:

```text
true
false
null
```

### JSON Example

```json
{
	"id": {{ user.id | number }},
	"name": {{ user.name | plain }},
	"slug": {{ user.slug | string }},
	"created": {{ user.created_at | date:"d/m/Y" }},
	"active": {{ user.active | bool }}
}
```

### Summary

| Pipe     | Purpose | Notes |
| -------- | ------- | ----- |
| `date`   | Format date values | Accepts custom masks |
| `number` | Format numeric values | Supports decimals and separators |
| `string` | URL-encode strings | Adds quotes |
| `plain`  | JSON-safe plain strings | Adds quotes without URL encoding |
| `bool`   | Normalize boolean output | `true` / `false` / `null` |

---

### Model-bound Components

```php
namespace Osumi\OsumiFramework\App\Component\Model\User;

use Osumi\OsumiFramework\App\Model\User;
use Osumi\OsumiFramework\Core\OComponent;

class UserComponent extends OComponent {
	public ?User $user = null;
}
```

---

## Best Practices

- Keep templates simple.
- Use `run()` for preparing data.
- Use typed public properties.
- Prefer nullable defaults such as `?type = null` where appropriate.
