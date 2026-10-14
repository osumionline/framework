# Comandos CLI de Osumi Framework

Osumi Framework proporciona tareas CLI de aplicación y un binario dedicado a las migraciones del framework.

## CLI de aplicación

Las tareas de aplicación se ejecutan desde la raíz del proyecto mediante:

```bash
php of <tarea> [opciones]
```

### `add`

Crea elementos de aplicación del framework.

```bash
php of add --option <tipo> --name <nombre>
```

Los tipos soportados incluyen:

- `action`
- `service`
- `task`
- `modelComponent`
- `component`
- `middleware`

Ejemplo:

```bash
php of add --option middleware --name Login
```

La antigua opción `filter` no está soportada desde 9.9.

### `backupAll`

```bash
php of backupAll
```

Crea una copia completa de la aplicación utilizando las tareas correspondientes de copia/exportación del framework.

### `backupDB`

```bash
php of backupDB
```

Crea una copia de la base de datos utilizando la configuración de base de datos y las herramientas del sistema.

### `extractor`

```bash
php of extractor
```

Exporta la aplicación mediante el extractor del framework.

### `generateModel`

```bash
php of generateModel
```

Genera el esquema SQL a partir de las clases de modelo de la aplicación.

### `generateModelFrom`

```bash
php of generateModelFrom <archivo>
```

Genera modelos a partir de un archivo de definición de modelos.

### `generateModelFromDB`

```bash
php of generateModelFromDB
```

Genera clases de modelo a partir de la base de datos configurada.

### `reset`

```bash
php of reset
```

Inicia el flujo protegido de reset utilizado para recrear una estructura limpia de aplicación. La estructura generada incluye `src/Middleware/` y `src/Middleware/Middlewares.php`.

### `version`

```bash
php of version
```

Muestra información sobre la versión del framework.

---

## CLI de migraciones del framework

El paquete Composer expone:

```bash
php vendor/bin/ofw-migrate --help
```

Las opciones de migración soportadas incluyen:

```text
--from
--to
--dry-run
--force
--verbose
--no-interaction
--help
```

Desde 9.12, el estado autoritativo de las migraciones se mantiene en `.ofw-migrations.json` en la raíz del proyecto. Este archivo debe versionarse junto con el código de la aplicación.

Por compatibilidad, el runner todavía puede leer el antiguo `ofw/tmp/state.json` cuando `.ofw-migrations.json` aún no existe.

La guía de migración 9.8.5 → 9.9.0 documenta en detalle el flujo de migración y la integración automática con Composer.

---

## Notas

- Ejecuta los comandos CLI de aplicación desde la raíz del proyecto.
- Ejecuta las migraciones desde la raíz del proyecto para que el runner opere sobre la aplicación correcta.
- Las tareas relacionadas con base de datos requieren una configuración válida.
