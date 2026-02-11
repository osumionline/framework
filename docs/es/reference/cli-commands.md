# Comandos CLI de Osumi Framework

Osumi Framework incluye un conjunto de tareas CLI que permiten realizar diversas operaciones relacionadas con el desarrollo y mantenimiento de aplicaciones. A continuación, se describe la lista de comandos disponibles:

## Comandos disponibles

### `add`

**Descripción:** Permite crear nuevas acciones, servicios, tareas, componentes del modelo, componentes o filtros.

**Uso:**

```bash
php of add [tipo] [nombre]
```

- **tipo:** Tipo del elemento a crear (`action`, `service`, `task`, `modelComponent`, `component`, `filter`).
- **nombre:** Nombre del elemento a crear.

**Ejemplo:**

```bash
php of add --option action --name MyAction
```

---

### `backupAll`

**Descripción:** Genera una copia de seguridad completa de la aplicación, incluyendo la base de datos y el código.

**Uso:**

```bash
php of backupAll
```

**Notas:** Este comando invoca internamente las tareas `backupDB` y `extractor`.

---

### `backupDB`

**Descripción:** Crea una copia de seguridad de la base de datos con la herramienta `mysqldump`.

**Uso:**

```bash
php of backupDB [opciones]
```

- **opciones:**
- `silent`: Si se incluye, el comando no mostrará mensajes en la consola.

**Ejemplo:**

```bash
php of backupDB silent
```

---

### `extractor`

**Descripción:** Exporta toda la aplicación a un único archivo PHP autoextraíble.

**Uso:**

```bash
php of extractor
```

**Notas:** Exporta toda la aplicación a un único archivo PHP autoextraíble.

---

### `generateModel`

**Descripción:** Genera un archivo SQL para crear todas las tablas de la base de datos basadas en los modelos definidos por el usuario.

**Uso:**

```bash
php of generateModel
```

**Notas:** El archivo SQL se genera en el directorio de exportación.

---

### `generateModelFrom`

**Descripción:** Genera todos los modelos a partir de un archivo JSON proporcionado.

**Uso:**

```bash
php of generateModelFrom [archivo]
```

- **archivo:** Ruta al archivo JSON que contiene las definiciones del modelo.

**Ejemplo:**

```bash
php of generateModelFrom models.json
```

---

### `generateModelFromDB`

**Descripción:** Genera todos los modelos a partir de una conexión a una base de datos existente.

**Uso:**

```bash
php of generateModelFromDB
```

**Notas:** Se conecta a la base de datos configurada y genera los modelos correspondientes.

---

### `reset`

**Descripción:** Limpia todos los datos que no pertenecen al framework, útil para nuevas instalaciones.

**Uso:**

```bash
php of reset
```

**Notas:** Elimina carpetas y archivos generados por el usuario y restaura la configuración y estructura predeterminadas.

---

### `version`

**Descripción:** Muestra información sobre la versión actual del framework.

**Uso:**

```bash
php of la versión
```

**Notas:** Incluye enlaces al repositorio oficial y a la cuenta X (anteriormente Twitter) del proyecto.

---

## Notas adicionales

- Todos los comandos deben ejecutarse desde la raíz del proyecto.
- Asegúrese de que las configuraciones necesarias estén definidas en el archivo `Config.json` antes de ejecutar comandos relacionados con la base de datos o las exportaciones.
