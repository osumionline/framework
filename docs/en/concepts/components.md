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
  /**
   * Public properties are automatically exposed to the template.
   */
  public ?string $token = null;
}

```

### Template File

Example of a template (`LostPasswordTemplate.php`):

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

Example of a child component class file (`ChildComponent.php`):

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Child;

use Osumi\OsumiFramework\Core\OComponent;

class ChildComponent extends OComponent {
  public ?string $name = null;
}

```

### Template File

Example of a template (`ChildTemplate.php`):

```php
<div>
  Name: {{ name }}
</div>

```

Example of a father component class file using a child component (`FatherComponent.php`):

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Father;

use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\App\Component\Child\ChildComponent;

class FatherComponent extends OComponent {
  public ?ChildComponent $child = null;

  public function run(): void {
    $this->child = new ChildComponent();
    $this->child->name = 'Child Name';
  }
}

```

### Template File

Example of a template (`FatherTemplate.php`):

```php
<div>
  Child: {{ child }}
</div>

```

The resulting output would be:

```php
  Child: Name: Child Name
```

### Template Syntax and Access

Templates access the component's public properties differently depending on the file extension:

1. **PHP Templates (`.php`)**:

- Can execute native PHP code.
- Access public properties as standard variables (e.g., `$token`).

2. **Static/Structured Templates (`.html`, `.json`, `.xml`)**:

- Use the double curly brace notation to output public properties: `{{ variable_name }}`.

---

## The `run()` method (Optional)

A component can define an optional `run()` method. If present, it is executed automatically at the beginning of the `render()` process to prepare data before the template is processed.

When the component is used as an action (a component rendered as a result of an activated route), the `run()` function can receive requests data:

- If the `run()` function has a DTO, data from the request will be used to populate its fields.
- Otherwise a generic `ORequest` object will be passed to it.

`ORequest` class has methods to get data passed such as form values or parameters passed via the URL:

- **`getParamString('name')`**: returns the value of the field 'name' passed to the route as a string (null if not present).
- **`getParamInt('name')`**: returns the value of the field 'name' passed to the route as an integer (null if not present).
- **`getParamFloat('name')`**: returns the value of the field 'name' passed to the route as a float (null if not present).
- **`getParamBool('name')`**: returns the value of the field 'name' passed to the route as a boolean (null if not present).

If a route has a filter defined, the `ORequest` class also provides ways to access the result of their execution:

```php
  public function run(ORequest $req): void {
    $login_filter = $req->getFilter('login'); // Would access the returned result from the LoginFilter file
    $filters = $req->getFilters(); // Would access the returned result of every applied filter as an associative array ['login' => [...]]
  }
```

**Example:**

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

## Accessing global options

Components have methods to access to global options such as application configuration, logs or session data:

- **`getConfig()`**: Returns global `OConfig` to read paths or user defined values (secrets, email addresses...)
    - Docs: docs/en/concepts/config.md
- **`getLog()`**: Returns `OLog` instance for the component. User can log information using methods as `debug`, `info` or `error`.
    - Docs: docs/en/concepts/log.md
- **`getSession()`**: Returns `OSession` instance that can be used to access $\_SESSION params.

---

## Naming Conventions

To maintain consistency, follow these naming patterns:

| File Type           | Convention          | Example             |
| ------------------- | ------------------- | ------------------- |
| **Component Class** | `XxxComponent.php`  | `UserComponent.php` |
| **Template**        | `XxxTemplate.<ext>` | `UserTemplate.json` |

---

## Rendering Components

A typical rendering flow involves instantiating the component, assigning data, and outputting the result.

```php
$cmp = new BooksComponent();

// You can cast it to string to trigger run() and render()
echo strval($cmp);

```

# Template Pipes

Osumi Framework templates support **Angular‑style pipes**, allowing you to transform values directly inside the template.

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

Below are all built‑in pipes and their behavior, derived from the functions in `OPipeFunctions.php`.

---

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

Formats numbers using PHP’s `number_format()`.

### Syntax

    {{ price | number }}
    {{ price | number:2 }}
    {{ price | number:2:".":"," }}

### Behavior

- Default decimals: **2**
- Default decimal separator: `"."`
- Default thousand separator: `""`
- If value is null → `"null"`

Examples:

    1234.5 → 1234.50
    1234.5 → 1,234.50  (if thousand separator is ",")

---

## 3. `string`

Applies `urlencode()` to a string.

### Syntax

    {{ user.name | string }}

### Behavior

- Null → `"null"`
- Value → `"urlencoded string"`

Example:

    "John Doe" → "John+Doe"

---

## 4. `bool`

Converts booleans to:

    true
    false
    null

### Syntax

    {{ user.isAdmin | bool }}

---

# How Pipes Behave in JSON Templates

Since templates like `.json` are rendered as strings, pipes automatically ensure:

- Strings are quoted when needed
- Booleans appear without quotes
- Numbers appear unquoted
- Null values appear as `null`

This guarantees valid JSON output.

---

# Examples

```json
{
  "id": {{ user.id | number }},
  "name": {{ user.name | string }},
  "created": {{ user.created_at | date:"d/m/Y" }},
  "active": {{ user.active | bool }}
}
```

---

# Summary of Pipes

| Pipe     | Purpose                  | Notes                          |
| -------- | ------------------------ | ------------------------------ |
| `date`   | Format date values       | Accepts custom masks           |
| `number` | Format numeric values    | Supports decimals & separators |
| `string` | URL‑encode strings       | Adds quotes                    |
| `bool`   | Normalize boolean output | `true` / `false` / `null`      |

### Model-bound Components

When components represent model views, you can use typed properties with your model classes.

```php
namespace Osumi\OsumiFramework\App\Component\Model\User;

use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\App\Model\User;

class UserComponent extends OComponent {
  public ?User $user = null;
}

```

---

## Best Practices

- **Keep templates simple**: Limit them to minimal display logic.
- **Use `run()`**: Use it for preparing data or performing calculations before rendering.
- **Typed properties**: Use typed public properties for clarity.
- **Default values**: Prefer `?type = null` defaults to avoid "uninitialized property" errors in PHP 8.3+.
