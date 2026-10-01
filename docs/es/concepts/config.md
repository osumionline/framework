# Configuración

La configuración de Osumi Framework se gestiona mediante archivos JSON ubicados en `src/Config/`. Estos ajustes controlan el comportamiento de la aplicación, las conexiones a la base de datos, las variables específicas del entorno y otros valores.

---

## Archivos de configuración

1. `Config.json`: configuración principal.
2. `Config_{environment}.json`: sobrescrituras opcionales específicas del entorno.

---

## Bloques principales de configuración

### Configuración de la aplicación

```json
{
	"name": "Mi aplicación",
	"lang": "es",
	"use-session": true,
	"allow-cross-origin": true,
	"base_url": "https://example.com",
	"css_list": [],
	"js_list": [],
	"head_elements": []
}
```

### Base de datos (`db`)

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

### Directorios personalizados (`dir`)

```json
{
	"dir": {
		"uploads": "{{base}}public/uploads/",
		"exports": "{{ofw_export}}my_reports/"
	}
}
```

### Configuración adicional (`extra`)

```json
{
	"extra": {
		"api_key": "12345-abcde",
		"items_per_page": 20
	}
}
```

---

## Recursos y elementos `head`

`css_list` y `js_list` aceptan arrays de nombres resueltos bajo `public`.

`head_elements` acepta entradas que contienen un `item` y un objeto `attributes`.

---

## Acceso a la configuración desde código

```php
$apiKey = $this->getConfig()->getExtra('api_key');
$uploadPath = $this->getConfig()->getDir('uploads');
$dbName = $this->getConfig()->getDB('name');
```

---

## Sobrescrituras por entorno

Cuando un valor existe tanto en `Config.json` como en el archivo del entorno seleccionado, el valor específico del entorno sobrescribe al principal.

---

## Rutas de la aplicación

`OConfig` define un conjunto de rutas predeterminadas:

| Clave | Ruta | Descripción |
| ----- | ---- | ----------- |
| `base` | `/` | Ruta base de la aplicación |
| `app` | `/src/` | Código de la aplicación |
| `app_component` | `/src/Component/` | Componentes reutilizables |
| `app_config` | `/src/Config/` | Archivos de configuración |
| `app_dto` | `/src/DTO/` | Clases DTO |
| `app_layout` | `/src/Layout/` | Componentes de layout |
| `app_middleware` | `/src/Middleware/` | Clases Middleware y configuración global de Middlewares |
| `app_model` | `/src/Model/` | Archivos de modelos de base de datos |
| `app_routes` | `/src/Routes/` | Rutas de la aplicación |
| `app_service` | `/src/Service/` | Servicios reutilizables |
| `app_task` | `/src/Task/` | Tareas CLI de la aplicación |
| `app_utils` | `/src/Utils/` | Clases de utilidad |
| `ofw` | `/ofw/` | Archivos generados por el framework |
| `ofw_cache` | `/ofw/cache/` | Archivos de caché |
| `ofw_export` | `/ofw/export/` | Archivos exportados |
| `ofw_tmp` | `/ofw/tmp/` | Archivos temporales |
| `ofw_logs` | `/ofw/logs/` | Archivos de log |
| `ofw_base` | `/vendor/osumionline/framework/` | Ruta base del framework |
| `ofw_vendor` | `/vendor/osumionline/framework/src/` | Código del framework |
| `ofw_assets` | `/vendor/osumionline/framework/src/Assets/` | Recursos del framework |
| `ofw_locale` | `/vendor/osumionline/framework/src/Assets/locale/` | Archivos de idioma del framework |
| `ofw_template` | `/vendor/osumionline/framework/src/Assets/template/` | Plantillas del framework |
| `ofw_task` | `/vendor/osumionline/framework/src/Task/` | Tareas CLI del framework |
| `ofw_tools` | `/vendor/osumionline/framework/src/Tools/` | Herramientas del framework |
| `public` | `/public/` | Document root de la aplicación |

---

## Mejores prácticas

- No subas secretos al repositorio.
- Usa configuración específica por entorno cuando corresponda.
- Valida los valores devueltos por getters genéricos cuando su tipo sea importante.
- Mantén los archivos JSON sintácticamente válidos.
