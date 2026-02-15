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
	"base_url": "https://example.com",
	"css_list": [],
	"js_list": [],
	"head_elements": []
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

### Assets and head elements

The `css_list` and `js_list` entries accept arrays of strings. Each string is interpreted as a file name that must live under the application's `public` directory.

- For `css_list`: each value `"name"` maps to the file `/public/css/name.css` and will be rendered as:

    `<link rel="stylesheet" type="text/css" href="css/name.css">`

    Example: `"css_list": ["main", "lib"]` includes:
    - `/public/css/main.css` → `<link rel="stylesheet" type="text/css" href="css/main.css">`
    - `/public/css/lib.css` → `<link rel="stylesheet" type="text/css" href="css/lib.css">`

- For `js_list`: each value `"name"` maps to the file `/public/js/name.js` and will be rendered as:

    `<script src="js/name.js"></script>`

    Example: `"js_list": ["app"]` includes:
    - `/public/js/app.js` → `<script src="js/app.js"></script>`

The `head_elements` option accepts an array of objects to inject arbitrary elements into the document `<head>` using a simple structure.

Example:

```json
{
	"head_elements": [
		{
			"item": "meta",
			"attributes": { "name": "theme-color", "content": "#000" }
		},
		{
			"item": "link",
			"attributes": {
				"rel": "icon",
				"href": "/fav.svg",
				"type": "image/svg+xml"
			}
		},
		{
			"item": "script",
			"attributes": {
				"src": "https://cdn.example.com/lib.js",
				"async": true
			}
		}
	]
}
```

Each `head_elements` entry must be an object with `item` (tag name) and `attributes` (an object of key/value pairs). Scripts will be rendered with an explicit closing tag (`<script></script>`); other tags are self-closed.

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

## Overriding values

When a key is present on the `Config.json` file, but there is also defined on the environment specific file, the latest is applied. Selected environment is defined by using the `env` key:

```json
  // Config.json file
  {
	"log_level": "DEBUG",
	"env": "prod"
  }

  // Config_prod.json file
  {
	"log_level": "ERROR"
  }
```

In this case "ERROR" would be the value for `log_level` because the key is defined as a key on the global file and the environment specific one.

---

## Application paths

When the application is loaded, a set of default paths are loaded on `OConfig`:

| Key             | Path                                               | Description                                                |
| --------------- | -------------------------------------------------- | ---------------------------------------------------------- |
| `base`          | /                                                  | Applications base path                                     |
| `app`           | /src/                                              | User code                                                  |
| `app_component` | /src/Component/                                    | Reusable components                                        |
| `app_config`    | /src/Config/                                       | Configuration files                                        |
| `app_dto`       | /src/DTO/                                          | DTOs used on actions                                       |
| `app_filter`    | /src/Filter/                                       | Filters used on actions                                    |
| `app_layout`    | /src/Layout/                                       | Reusable layouts                                           |
| `app_mode`      | /src/Model/                                        | Database model files                                       |
| `app_routes`    | /src/Routes/                                       | User defined URLs                                          |
| `app_service`   | /src/Service/                                      | Reusable service files                                     |
| `app_task`      | /src/Task/                                         | User defined tasks for the CLI                             |
| `app_utils`     | /src/Utils/                                        | Generic utils classes                                      |
| `ofw`           | /ofw/                                              | Location of application generated files (logs, exports...) |
| `ofw_cache`     | /ofw/cache/                                        | Path of application cache files                            |
| `ofw_export`    | /ofw/export/                                       | Path of exported files (as model.sql)                      |
| `ofw_tmp`       | /ofw/tmp/                                          | Path of tmp files                                          |
| `ofw_logs`      | /ofw/logs/                                         | Path of generated log files                                |
| `ofw_base`      | /vendor/osumionline/framework/                     | Base path of framework                                     |
| `ofw_vendor`    | /vendor/osumionline/framework/src/                 | Framework code                                             |
| `ofw_assets`    | /vendor/osumionline/framework/src/Assets/          | Framework assets (locales, templates)                      |
| `ofw_locale`    | /vendor/osumionline/framework/src/Assets/locale/   | Framerowk locale files (en, es, eu)                        |
| `ofw_template`  | /vendor/osumionline/framework/src/Assets/template/ | Framework templates for generating new files               |
| `ofw_task`      | /vendor/osumionline/framework/src/Task/            | Framework CLI tasks                                        |
| `ofw_tools`     | /vendor/osumionline/framework/src/Tools/           | Framework internal tools                                   |
| `public`        | /public/                                           | DocumentRoot of the application                            |

---

## Summary of Configuration Keys

| Key             | Type    | Description                                                |
| --------------- | ------- | ---------------------------------------------------------- |
| `name`          | String  | Application name.                                          |
| `lang`          | String  | Default language (e.g., "en", "es").                       |
| `use-session`   | Boolean | Whether to enable native PHP sessions.                     |
| `db`            | Object  | Database connection details.                               |
| `dir`           | Object  | Custom directory definitions.                              |
| `extra`         | Object  | Custom key-value pairs.                                    |
| `error_pages`   | Object  | Custom URLs for 403, 404, or 500 errors.                   |
| `css_list`      | Array   | List of CSS files to include.                              |
| `js_list`       | Array   | List of JavaScript files to include.                       |
| `head_elements` | Array   | List of HTML head elements to inject (meta, link, script). |
| `libs`          | Array   | List of third-party libraries to load.                     |

---

## Best Practices

- **Security**: Never commit sensitive information (passwords, API keys) in `Config.json`. Use environment-specific files that are excluded from version control.
- **Environment Variable**: Ensure the `env` key is set in your main `Config.json` to trigger the loading of secondary configuration files.
- **Typed Extras**: Remember that `getExtra()` can return various types; validate them if necessary.
- **Strict formating**: Configuration files must be strict compliant files with the JSON formatting. Any error, extra comma or similars would result in a application error as it won't be able to load them.
