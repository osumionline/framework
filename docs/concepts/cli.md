# CLI (Command Line Interface)

Osumi Framework provides a powerful command-line interface to automate development tasks, manage the database, and run custom scripts. The entry point for all CLI commands is the `of` file located in the root of your project.

---

## Usage

Commands are executed using PHP from the terminal:

```bash
php of <option> [arguments]

```

If the file is executed without any arguments, the list of available options are shown (both Framework options and user created ones).

### Core Commands

The framework includes several built-in tasks:

- **`add`**: Create new actions, services, tasks, models, components, or filters.
- **`generateModel`**: Create the SQL database schema from your model classes.
- **`generateModelFrom` / `generateModelFromDB`**: Reverse engineering to create models.
- **`backupAll` / `backupDB`**: Create security copies of files and/or database.
- **`extractor`**: Export the entire application into a single self-extracting file.
- **`reset`**: Clear all non-framework data for a fresh installation.
- **`version`**: Display the current framework version.

---

## Custom Tasks

You can extend the CLI by creating your own tasks. Any class placed in `src/Task/` that extends `OTask` will automatically appear as an available option in the `of` command.

### Creating a Task

A task requires two main elements:

1. **`__toString()`**: Returns a brief description of the task (displayed in the help menu).
2. **`run(array $options)`**: The logic to be executed.

### Example: `AddUserTask.php`

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Task;

use Osumi\OsumiFramework\Core\OTask;
use Osumi\OsumiFramework\App\Model\User;

class AddUserTask extends OTask {
  public function __toString() {
    return "addUser: Task to create new users";
  }

  public function run(array $options=[]): void {
    $name = $options['name'] ?? $options[0] ?? null;

    if (is_null($name)) {
      echo "Error: Name is required.\n";
      return;
    }

    $u = new User();
    $u->name = $name;
    $u->save();

    echo "User " . $name . " created successfully.\n";
  }
}

```

---

## Handling Arguments

The `run` method receives an `$options` array that supports two styles of input:

### 1. Positional Arguments

Passed directly after the command name.

```bash
php of addUser "John Doe"
# $options = [0 => "John Doe"]

```

### 2. Named Parameters

Using the `--key value` syntax. This results in an associative array.

```bash
php of addUser --name "John Doe"
# $options = ["name" => "John Doe"]

```

> **Note on `$options$`**: The `$options` parameter is always an array, DTOs can't be used on Tasks.

---

## Task Features

Classes extending `OTask` have access to several built-in utilities:

- **`$this->getConfig()`**: Access application configuration.
- **`$this->getColors()`**: Use the `OColors` utility to output colored text to the console.
- **ORM Access**: You can use any Model class to perform database operations just as you would in a Component.
- **Programmatic Execution**: Tasks can be instantiated and run from other parts of the code, not just the terminal.

---

## Best Practices

- **Help Messages**: Use the `run` method to check if required arguments are present and display a usage example if they are missing.
- **Color Coding**: Use `$this->getColors()->getColoredString()` to highlight errors in red or success messages in green for better UX.
- **Namespace**: Ensure your custom tasks are under the `Osumi\OsumiFramework\App\Task` namespace.
