# Components

Components in Osumi Framework are small, reusable pieces of code that render a template. A component is composed of:

- A PHP class extending `OComponent`.
- A template file (php/html/json/xml depending on usage).

A component instance is created, properties are assigned, and then the component is rendered, usually via `render()` or by casting the object to a string.

---

## Basic component structure

### Component Class

Example of a component class file (`LostPasswordComponent.php`):

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Email\LostPassword;

use Osumi\OsumiFramework\Core\OComponent;

class LostPasswordComponent extends OComponent {
  public ?string $token = null;
}
```

### Template File

```php
<div>
  Token: {{ token }}
</div>
```

---

## Advanced Features

### Automatic Content-Type Headers

When a component is used as a main action for a URL, the framework automatically sends the appropriate `Content-Type` header based on the template's file extension:

- `.json`: Sends `Content-type: application/json`.
- `.xml`: Sends `Content-type: application/xml`.
- `.html` / `.php`: Sends `Content-type: text/html`.

### Component Nesting

Components can be chained or nested. A larger component can include and render smaller components within its logic or template to promote reusability.

```php
<?php declare(strict_types=1);

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

Templates access the component's public properties differently depending on the file extension:

1. **PHP Templates (`.php`)** can execute native PHP code and access public properties as standard variables.
2. **Static/Structured Templates (`.html`, `.json`, `.xml`)** use double curly braces: `{{ variable_name }}`.

---

## The `run()` method (Optional)

A component can define an optional `run()` method. If present, it is executed automatically at the beginning of the `render()` process to prepare data before the template is processed.

When the component is used as an action (a component rendered as a result of an activated route), the `run()` method supports exactly one of the following signatures:

```php
public function run(): void
```

```php
public function run(ORequest $req): void
```

```php
public function run(MyDTO $dto): void
```

The behavior depends on the selected signature:

- `run()` receives no request data and can be used when the component does not need access to the current request.
- `run(ORequest $req)` receives the current request as an `ORequest` instance.
- `run(MyDTO $dto)` receives a DTO automatically populated from the current request. `MyDTO` must extend `ODTO`.
- DTOs are detected by inheritance from `ODTO`, not by their namespace. DTO classes can therefore be located anywhere in the application.
- No other signatures are supported. The method can receive at most one parameter, and that parameter must be a non-nullable `ORequest` or a class extending `ODTO`.

The `ORequest` class has methods to get data passed such as form values or parameters passed via the URL:

- **`getParamString('name')`**: returns the value of the field 'name' passed to the route as a string (null if not present).
- **`getParamInt('name')`**: returns the value of the field 'name' passed to the route as an integer (null if not present).
- **`getParamFloat('name')`**: returns the value of the field 'name' passed to the route as a float (null if not present).
- **`getParamBool('name')`**: returns the value of the field 'name' passed to the route as a boolean (null if not present).

If a route has a filter defined, the `ORequest` class also provides ways to access the result of their execution:

```php
public function run(ORequest $req): void {
  $login_filter = $req->getFilter('login');
  $filters = $req->getFilters();
}
```

**Examples:**

```php
class BooksComponent extends OComponent {
  public array $books = [];

  public function run(): void {
    $this->books = ['Book A', 'Book B'];
  }
}
```

```php
class GetBookComponent extends OComponent {
  public ?Book $book = null;

  public function run(ORequest $req): void {
    $id_book = $req->getParamInt('id');
    $this->book = Book::findOne(['id' => $id_book]);
  }
}
```

---

## Accessing global options

Components have methods to access global options such as application configuration, logs or session data:

- **`getConfig()`**: Returns global `OConfig`.
- **`getLog()`**: Returns the component `OLog` instance.
- **`getSession()`**: Returns the `OSession` instance.

---

## Rendering Components

```php
$cmp = new BooksComponent();
echo strval($cmp);
```

---

# Template Pipes

Osumi Framework templates support **Angular-style pipes**, allowing you to transform values directly inside the template.

### Syntax

    {{ value | pipeName }}
    {{ value | pipeName:param }}
    {{ value | pipeName:param1:param2 }}

### Purpose

Pipes allow formatting of:

- Dates
- Numbers
- Strings
- Booleans

Pipes are processed by the internal **OPipeFunctions** class.

---

# Available Pipes

## 1. `date`

Formats a date string (`Y-m-d H:i:s` format) into a new format.

### Syntax

    {{ user.created_at | date }}
    {{ user.created_at | date:"d/m/Y" }}
    {{ user.created_at | date:"d-m-Y H:i" }}

### Behavior

- Input must be `Y-m-d H:i:s`
- Output is formatted using PHP `DateTime::format()`
- If the date is invalid → `"null"`

### Default format

    d/m/Y H:i:s

---

## 2. `number`

Formats numbers using PHP's `number_format()`.

### Syntax

    {{ price | number }}
    {{ price | number:2 }}
    {{ price | number:2:".":"," }}

### Behavior

- Default decimals: **2**
- Default decimal separator: `"."`
- Default thousand separator: `""`
- If value is null → `"null"`

---

## 3. `string`

Applies `urlencode()` to a string.

### Syntax

    {{ user.name | string }}

### Behavior

- Null → `null`
- Value → URL-encoded string in quotes

Example:

    John Doe → "John+Doe"

---

## 4. `plain`

Encodes a string as a JSON-safe value without applying URL encoding.

### Syntax

    {{ user.name | plain }}

### Behavior

- Null → `null`
- Value → JSON-safe quoted string
- Unicode characters are preserved
- Slashes are not escaped
- Quotes and other JSON-sensitive characters are properly escaped

Examples:

    John Doe → "John Doe"
    He said "hello" → "He said \"hello\""

This pipe is especially useful in JSON templates when the original string value must be preserved without URL encoding.

---

## 5. `bool`

Converts booleans to:

    true
    false
    null

### Syntax

    {{ user.isAdmin | bool }}

---

# How Pipes Behave in JSON Templates

Pipes provide values suitable for structured templates:

- `string` produces a quoted URL-encoded string.
- `plain` produces a quoted JSON-safe string without URL encoding.
- Booleans appear without quotes.
- Numbers appear unquoted.
- Null values appear as `null`.

---

# Examples

```json
{
  "id": {{ user.id | number }},
  "name": {{ user.name | plain }},
  "slug": {{ user.slug | string }},
  "created": {{ user.created_at | date:"d/m/Y" }},
  "active": {{ user.active | bool }}
}
```

---

# Summary of Pipes

| Pipe     | Purpose                  | Notes                            |
| -------- | ------------------------ | -------------------------------- |
| `date`   | Format date values       | Accepts custom masks             |
| `number` | Format numeric values    | Supports decimals & separators   |
| `string` | URL-encode strings       | Adds quotes                      |
| `plain`  | JSON-safe plain strings  | Adds quotes without URL encoding |
| `bool`   | Normalize boolean output | `true` / `false` / `null`        |


### Model-bound Components

When components represent model views, you can use typed properties with your model classes.

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

- **Keep templates simple**: Limit them to minimal display logic.
- **Use `run()`**: Use it for preparing data or performing calculations before rendering.
- **Typed properties**: Use typed public properties for clarity.
- **Default values**: Prefer `?type = null` defaults to avoid uninitialized property errors.
