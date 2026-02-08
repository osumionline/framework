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

**Example:**

```php
class BooksComponent extends OComponent {
  public array $books = [];

  public function run(): void {
    $this->books = ['Book A', 'Book B'];
  }
}

```

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
