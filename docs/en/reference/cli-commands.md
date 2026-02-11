# Osumi Framework CLI Commands

Osumi Framework includes a set of CLI tasks that allow you to perform various operations related to the development and maintenance of applications. Below is a description of the available commands:

## Available Commands

### `add`

**Description:** Allows you to create new actions, services, tasks, model components, components, or filters.

**Usage:**

```bash
php of add [option] [name]
```

- **option:** Type of element to create (`action`, `service`, `task`, `modelComponent`, `component`, `filter`).
- **name:** Name of the element to create.

**Example:**

```bash
php of add --option action --name MyAction
```

---

### `backupAll`

**Description:** Generates a complete backup file of the application, including the database and code.

**Usage:**

```bash
php of backupAll
```

**Notes:** This command internally calls the `backupDB` and `extractor` tasks.

---

### `backupDB`

**Description:** Creates a database backup using the `mysqldump` tool.

**Usage:**

```bash
php of backupDB [options]
```

- **options:**
    - `silent`: If included, the command will not display messages in the console.

**Example:**

```bash
php of backupDB silent
```

---

### `extractor`

**Description:** Exports the entire application to a single self-extracting PHP file.

**Usage:**

```bash
php of extractor
```

**Notes:** Exports the entire application to a single self-extracting PHP file.

---

### `generateModel`

**Description:** Generates an SQL file to create all database tables based on the user-defined models.

**Usage:**

```bash
php of generateModel
```

**Notes:** The SQL file is generated in the export directory.

---

### `generateModelFrom`

**Description:** Generates all models from a provided JSON file.

**Usage:**

```bash
php of generateModelFrom [file]
```

- **file:** Path to the JSON file containing the model definitions.

**Example:**

```bash
php of generateModelFrom models.json
```

---

### `generateModelFromDB`

**Description:** Generates all models from an existing database connection.

**Usage:**

```bash
php of generateModelFromDB
```

**Notes:** Connects to the configured database and generates the corresponding models.

---

### `reset`

**Description:** Cleans all non-framework data, useful for new installations.

**Usage:**

```bash
php of reset
```

**Notes:** Deletes user-generated folders and files and restores the default configuration and structure.

---

### `version`

**Description:** Displays information about the current version of the framework.

**Usage:**

```bash
php of version
```

**Notes:** Includes links to the official repository and the project's X (formerly Twitter) account.

---

## Additional Notes

- All commands must be executed from the root of the project.
- Ensure that the necessary configurations are defined in the `Config.json` file before running commands related to the database or exports.
