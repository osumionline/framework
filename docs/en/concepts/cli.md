# CLI (Command Line Interface)

Osumi Framework provides command-line tools for application development, maintenance and framework migrations.

The application CLI entry point is the `of` file in the project root.

---

## Application CLI

```bash
php of <option> [arguments]
```

Without arguments, the CLI displays available framework and application tasks.

### Core Commands

The framework includes tasks such as:

- `add`: create actions, services, tasks, model components, components or Middlewares.
- `generateModel`
- `generateModelFrom`
- `generateModelFromDB`
- `backupAll`
- `backupDB`
- `extractor`
- `reset`
- `version`

Example Middleware generation:

```bash
php of add --option middleware --name Login
```

This creates:

```text
src/Middleware/LoginMiddleware.php
```

The old `filter` add option has not been supported since 9.9.

---

## Framework Migration CLI

Framework migrations are also available through the Composer-installed binary:

```bash
php vendor/bin/ofw-migrate --help
```

This command is documented in detail in the 9.8.5 → 9.9.0 migration guide.

---

## Custom Tasks

Application tasks live in `src/Task/` and extend `OTask`.

Example:

```php
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Task;

use Osumi\OsumiFramework\Core\OTask;

class AddUserTask extends OTask {
	public function __toString(): string {
		return 'addUser: Task to create new users';
	}

	/**
	 * Run the task.
	 *
	 * @param array<string, string|false> $options Task options.
	 *
	 * @return void
	 */
	public function run(array $options = []): void {
		$name = $options['name'] ?? null;

		if (!is_string($name)) {
			echo "Error: Name is required.\n";
			return;
		}

		echo "User {$name} created successfully.\n";
	}
}
```

---

## Named Parameters

```bash
php of addUser --name "John Doe"
```

CLI task options are always provided as an array.

---

## Task Features

Classes extending `OTask` have access to:

- `getConfig()`
- `getColors()`
- application models and services where applicable

---

## Best Practices

- Validate task arguments.
- Keep task methods strongly typed.
- Document task methods with PHPDoc.
- Use `getColors()` for readable CLI output.
- Keep application tasks under `Osumi\OsumiFramework\App\Task`.
