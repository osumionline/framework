# Configuración

La configuración de Osumi Framework se gestiona mediante archivos JSON ubicados en `src/Config/`. Estos ajustes controlan el comportamiento de la aplicación, las conexiones a la base de datos, las variables específicas del entorno y más.

---

## Archivos de configuración

El framework sigue un patrón de carga jerárquico:

1. **`Config.json`**: El archivo de configuración principal con valores predeterminados.
2. **`Config_{entorno}.json`**: Archivo opcional específico del entorno (p. ej., `Config_prod.json`) que anula los valores del archivo principal.

---

## Bloques de configuración principal

### Configuración de la aplicación

Parámetros globales para el comportamiento básico de la aplicación.

```json
{
	"name": "Mi App Genial",
	"lang": "es",
	"use-session": true,
	"allow-cross-origin": true,
	"base_url": "https://ejemplo.com"
}
```

### Base de datos (`db`)

Configuración de la conexión PDO.

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

### Registro (`log`)

Configuración de los logs de la aplicación.

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

Puede definir rutas personalizadas usando marcadores de posición de directorios existentes.

```json
{
	"dir": {
		"uploads": "{{base}}public/uploads/",
		"exports": "{{ofw_export}}my_reports/"
	}
}
```

### Configuración adicional (`extra`)

Un almacén de clave-valor para cualquier dato personalizado que necesite su aplicación (claves API, secretos, etc.).

```json
{
	"extra": {
		"api_key": "12345-abcde",
		"items_per_page": 20
	}
}
```

---

## Acceso a la configuración en el código

El objeto `OConfig` suele estar disponible en las clases principales del framework (como Componentes o Tareas).

```php
// Ejemplo: Acceso a un valor "extra"
$apiKey = $this->getConfig()->getExtra('api_key');

// Ejemplo: Acceso a la ruta de un directorio
$uploadPath = $this->getConfig()->getDir('uploads');

// Ejemplo: Acceso a la información de la base de datos
$dbName = $this->getConfig()->getDB('name');

```

## Valores de sobreescritura

Cuando una clave está presente en el archivo `Config.json`, pero también está definida en el archivo específico del entorno, se aplica la última. El entorno seleccionado se define mediante la clave `env`:

```json
// Archivo Config.json
{
	"log_level": "DEBUG",
	"env": "prod"
}

// Archivo Config_prod.json
{
	"log_level": "ERROR"
}
```

En este caso, "ERROR" sería el valor de `log_level`, ya que la clave está definida tanto en el archivo global como en el específico del entorno.

---

## Rutas de la aplicación

Al cargar la aplicación, se cargan las rutas predeterminadas en `OConfig`:

| Clave           | Ruta                                               | Descripción                                                                    |
| --------------- | -------------------------------------------------- | ------------------------------------------------------------------------------ |
| `base`          | /                                                  | Ruta base de la aplicación                                                     |
| `app`           | /src/                                              | Código del usuario                                                             |
| `app_component` | /src/Component/                                    | Componentes reutilizables                                                      |
| `app_config`    | /src/Config/                                       | Archivos de configuración                                                      |
| `app_dto`       | /src/DTO/                                          | DTOs utilizados en acciones                                                    |
| `app_filter`    | /src/Filter/                                       | Filtros utilizados en acciones                                                 |
| `app_layout`    | /src/Layout/                                       | Diseños reutilizables                                                          |
| `app_mode`      | /src/Model/                                        | Archivos del modelo de base de datos                                           |
| `app_routes`    | /src/Routes/                                       | URLs definidas por el usuario                                                  |
| `app_service`   | /src/Service/                                      | Archivos de servicios reutilizables                                            |
| `app_task`      | /src/Task/                                         | Tareas definidas por el usuario para la CLI                                    |
| `app_utils`     | /src/Utils/                                        | Clases de utilidades genéricas                                                 |
| `ofw`           | /ofw/                                              | Ubicación de los archivos generados por la aplicación (logs, exportaciones...) |
| `ofw_cache`     | /ofw/cache/                                        | Ruta de los archivos de caché de la aplicación                                 |
| `ofw_export`    | /ofw/export/                                       | Ruta de los archivos exportados (como model.sql)                               |
| `ofw_tmp`       | /ofw/tmp/                                          | Ruta de los archivos temporales                                                |
| `ofw_logs`      | /ofw/logs/                                         | Ruta de los archivos de logs generados                                         |
| `ofw_base`      | /vendor/osumionline/framework/                     | Ruta base del framework                                                        |
| `ofw_vendor`    | /vendor/osumionline/framework/src/                 | Código del framework                                                           |
| `ofw_assets`    | /vendor/osumionline/framework/src/Assets/          | Recursos del framework (configuraciones regionales, plantillas)                |
| `ofw_locale`    | /vendor/osumionline/framework/src/Assets/locale/   | Archivos de configuración regional de Framerowk (en, es, eu)                   |
| `ofw_template`  | /vendor/osumionline/framework/src/Assets/template/ | Plantillas del framework para generar nuevos archivos                          |
| `ofw_task`      | /vendor/osumionline/framework/src/Task/            | Tareas de la CLI de Framework                                                  |
| `ofw_tools`     | /vendor/osumionline/framework/src/Tools/           | Herramientas internas de Framework                                             |
| `public`        | /public/                                           | DocumentRoot de la aplicación                                                  |

---

## Resumen de claves de configuración

| Clave         | Tipo     | Descripción                                     |
| ------------- | -------- | ----------------------------------------------- |
| `name`        | Cadena   | Nombre de la aplicación.                        |
| `lang`        | Cadena   | Idioma predeterminado (p. ej., "en", "es").     |
| `use-session` | Booleano | Si se habilitarán sesiones nativas de PHP.      |
| `db`          | Objeto   | Detalles de la conexión a la base de datos.     |
| `dir`         | Objeto   | Definiciones de directorios personalizadas.     |
| `extra`       | Objeto   | Pares clave-valor personalizados.               |
| `error_pages` | Objeto   | URL personalizadas para errores 403, 404 o 500. |
| `libs`        | Matriz   | Lista de bibliotecas de terceros para cargar.   |

---

## Mejores prácticas

- **Seguridad**: Nunca envíe información confidencial (contraseñas, claves API) en `Config.json`. Utilice archivos específicos del entorno que estén excluidos del control de versiones.
- **Variable de entorno**: Asegúrese de que la clave `env` esté configurada en su `Config.json` principal para activar la carga de archivos de configuración secundarios.
- **Extras tipificados**: Recuerde que `getExtra()` puede devolver varios tipos; valídelos si es necesario.
- **Formato estricto**: Los archivos de configuración deben ser archivos estrictamente compatibles con el formato JSON. Cualquier error, coma adicional o similar, generará un error en la aplicación, ya que no podrá cargarlos.
