# Osumi Framework CLI Commands

Osumi Framework provides application CLI tasks and a dedicated framework migration binary.

## Application CLI

Application tasks are executed from the project root through:

```bash
php of <task> [options]
```

### `add`

Creates framework application elements.

```bash
php of add --option <type> --name <name>
```

Supported types include:

- `action`
- `service`
- `task`
- `modelComponent`
- `component`
- `middleware`

Example:

```bash
php of add --option middleware --name Login
```

The old `filter` option has not been supported since 9.9.

### `backupAll`

```bash
php of backupAll
```

Creates a complete application backup by using the corresponding framework backup/export tasks.

### `backupDB`

```bash
php of backupDB
```

Creates a database backup using the configured database connection and system tooling.

### `extractor`

```bash
php of extractor
```

Exports the application using the framework extractor.

### `generateModel`

```bash
php of generateModel
```

Generates the SQL schema from application model classes.

### `generateModelFrom`

```bash
php of generateModelFrom <file>
```

Generates models from a model definition file.

### `generateModelFromDB`

```bash
php of generateModelFromDB
```

Generates model classes from the configured database.

### `reset`

```bash
php of reset
```

Starts the protected reset workflow used to recreate a clean application structure. The generated structure includes `src/Middleware/` and `src/Middleware/Middlewares.php`.

### `version`

```bash
php of version
```

Displays framework version information.

---

## Framework Migration CLI

The Composer package exposes:

```bash
php vendor/bin/ofw-migrate --help
```

Supported migration options include:

```text
--from
--to
--dry-run
--force
--verbose
--no-interaction
--help
```

Framework migrations are versioned, idempotent and keep their state under `ofw/tmp/state.json`.

The 9.8.5 → 9.9.0 migration guide documents the migration workflow and the automatic Composer integration in detail.

---

## Notes

- Run application CLI commands from the project root.
- Run migration commands from the project root so the migration runner operates on the intended application.
- Database-related tasks require valid database configuration.
