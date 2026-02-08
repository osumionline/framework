# Configuration

The Osumi Framework configuration is managed through JSON files located in `src/Config/`. These settings control the application's behavior, database connections, environment-specific variables, and more.

---

## Configuration Files

The framework follows a hierarchical loading pattern:

1.  **`Config.json`**: The main configuration file with default values.
2.  **`Config_{environment}.json`**: Optional environment-specific file (e.g., `Config_prod.json`) that overrides values from the main file.

---

## Core Configuration Blocks

### Application Settings

Global parameters for the application's basic behavior.

```json
{
	"name": "My Awesome App",
	"lang": "en",
	"use-session": true,
	"allow-cross-origin": true,
	"base_url": "[https://example.com](https://example.com)"
}
```

### Database (`db`)

Configuration for the PDO connection.

```json
{
	"db": {
		"driver": "mysql",
		"host": "localhost",
		"user": "root",
		"pass": "secret",
		"name": "my_database",
		"charset": "utf8mb4",
		"collate": "utf8mb4_unicode_ci"
	}
}
```

### Logging (`log`)

Settings for application logs.

```json
{
	"log_level": "DEBUG",
	"log": {
		"name": "app_log",
		"max_file_size": 50,
		"max_num_files": 3
	}
}
```

### Custom Directories (`dir`)

You can define custom paths using placeholders from existing directories.

```json
{
	"dir": {
		"uploads": "{{base}}public/uploads/",
		"exports": "{{ofw_export}}my_reports/"
	}
}
```

### Extra Settings (`extra`)

A key-value store for any custom data your application needs (API keys, secrets, etc.).

```json
{
	"extra": {
		"api_key": "12345-abcde",
		"items_per_page": 20
	}
}
```

---

## Accessing Configuration in Code

The `OConfig` object is typically available within the framework's core classes (like Components or Tasks).

```php
// Example: Accessing an "extra" value
$apiKey = $this->getConfig()->getExtra('api_key');

// Example: Accessing a directory path
$uploadPath = $this->getConfig()->getDir('uploads');

// Example: Accessing DB info
$dbName = $this->getConfig()->getDB('name');

```

---

## Summary of Configuration Keys

| Key           | Type    | Description                              |
| ------------- | ------- | ---------------------------------------- |
| `name`        | String  | Application name.                        |
| `lang`        | String  | Default language (e.g., "en", "es").     |
| `use-session` | Boolean | Whether to enable native PHP sessions.   |
| `db`          | Object  | Database connection details.             |
| `dir`         | Object  | Custom directory definitions.            |
| `extra`       | Object  | Custom key-value pairs.                  |
| `error_pages` | Object  | Custom URLs for 403, 404, or 500 errors. |
| `libs`        | Array   | List of third-party libraries to load.   |

---

## Best Practices

- **Security**: Never commit sensitive information (passwords, API keys) in `Config.json`. Use environment-specific files that are excluded from version control.
- **Environment Variable**: Ensure the `environment` key is set in your main `Config.json` to trigger the loading of secondary configuration files.
- **Typed Extras**: Remember that `getExtra()` can return various types; validate them if necessary.
