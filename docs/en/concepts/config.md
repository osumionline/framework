# Configuration

The Osumi Framework configuration is managed through JSON files located in `src/Config/`. These settings control application behavior, database connections, environment-specific variables, and more.

---

## Configuration Files

1. `Config.json`: main configuration.
2. `Config_{environment}.json`: optional environment-specific overrides.

---

## Core Configuration Blocks

### Application Settings

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

```json
{
	"dir": {
		"uploads": "{{base}}public/uploads/",
		"exports": "{{ofw_export}}my_reports/"
	}
}
```

### Extra Settings (`extra`)

```json
{
	"extra": {
		"api_key": "12345-abcde",
		"items_per_page": 20
	}
}
```

---

## Assets and Head Elements

`css_list` and `js_list` accept arrays of names resolved below `public`.

`head_elements` accepts entries containing an `item` and an `attributes` object.

---

## Accessing Configuration in Code

```php
$apiKey = $this->getConfig()->getExtra('api_key');
$uploadPath = $this->getConfig()->getDir('uploads');
$dbName = $this->getConfig()->getDB('name');
```

---

## Environment Overrides

When a value exists in both `Config.json` and the selected environment file, the environment-specific value overrides the main one.

---

## Application Paths

`OConfig` defines a set of default application paths:

| Key | Path | Description |
| --- | --- | --- |
| `base` | `/` | Application base path |
| `app` | `/src/` | Application code |
| `app_component` | `/src/Component/` | Reusable components |
| `app_config` | `/src/Config/` | Configuration files |
| `app_dto` | `/src/DTO/` | DTO classes |
| `app_layout` | `/src/Layout/` | Layout components |
| `app_middleware` | `/src/Middleware/` | Middleware classes and global Middleware configuration |
| `app_model` | `/src/Model/` | Database model files |
| `app_routes` | `/src/Routes/` | Application routes |
| `app_service` | `/src/Service/` | Reusable services |
| `app_task` | `/src/Task/` | Application CLI tasks |
| `app_utils` | `/src/Utils/` | Utility classes |
| `ofw` | `/ofw/` | Framework-generated application files |
| `ofw_cache` | `/ofw/cache/` | Cache files |
| `ofw_export` | `/ofw/export/` | Exported files |
| `ofw_tmp` | `/ofw/tmp/` | Temporary files |
| `ofw_logs` | `/ofw/logs/` | Log files |
| `ofw_base` | `/vendor/osumionline/framework/` | Framework base path |
| `ofw_vendor` | `/vendor/osumionline/framework/src/` | Framework code |
| `ofw_assets` | `/vendor/osumionline/framework/src/Assets/` | Framework assets |
| `ofw_locale` | `/vendor/osumionline/framework/src/Assets/locale/` | Framework locale files |
| `ofw_template` | `/vendor/osumionline/framework/src/Assets/template/` | Framework templates |
| `ofw_task` | `/vendor/osumionline/framework/src/Task/` | Framework CLI tasks |
| `ofw_tools` | `/vendor/osumionline/framework/src/Tools/` | Framework tools |
| `public` | `/public/` | Application document root |

---

## Best Practices

- Never commit secrets.
- Use environment-specific configuration where appropriate.
- Validate values returned by generic configuration getters when their type matters.
- Keep JSON files syntactically valid.
