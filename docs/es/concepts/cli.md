# CLI (Interfaz de Línea de Comandos)

Osumi Framework proporciona herramientas de línea de comandos para el desarrollo y mantenimiento de aplicaciones y para las migraciones del framework.

El punto de entrada de la CLI de aplicación es el archivo `of` situado en la raíz del proyecto.

---

## CLI de aplicación

```bash
php of <opcion> [argumentos]
```

Sin argumentos, la CLI muestra las tareas disponibles del framework y de la aplicación.

### Comandos principales

El framework incluye tareas como:

- `add`: crea acciones, servicios, tareas, componentes de modelo, componentes o Middlewares.
- `generateModel`
- `generateModelFrom`
- `generateModelFromDB`
- `backupAll`
- `backupDB`
- `extractor`
- `reset`
- `version`

Ejemplo de generación de un Middleware:

```bash
php of add --option middleware --name Login
```

Esto crea:

```text
src/Middleware/LoginMiddleware.php
```

La antigua opción `filter` de `add` no está soportada desde 9.9.

---

## CLI de migraciones del framework

Las migraciones del framework también están disponibles mediante el binario instalado por Composer:

```bash
php vendor/bin/ofw-migrate --help
```

Este comando se documenta en detalle en la guía de migración 9.8.5 → 9.9.0.

---

## Tareas personalizadas

Las tareas de aplicación se ubican en `src/Task/` y extienden `OTask`.

Ejemplo:

```php
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Task;

use Osumi\OsumiFramework\Core\OTask;

class AddUserTask extends OTask {
	public function __toString(): string {
		return 'addUser: Tarea para crear nuevos usuarios';
	}

	/**
	 * Ejecuta la tarea.
	 *
	 * @param array<string, string|false> $options Opciones de la tarea.
	 *
	 * @return void
	 */
	public function run(array $options = []): void {
		$name = $options['name'] ?? null;

		if (!is_string($name)) {
			echo "Error: Se requiere el nombre.\n";
			return;
		}

		echo "Usuario {$name} creado correctamente.\n";
	}
}
```

---

## Parámetros con nombre

```bash
php of addUser --name "John Doe"
```

Las opciones de una tarea CLI se proporcionan siempre como un array.

---

## Características de las tareas

Las clases que extienden `OTask` tienen acceso a:

- `getConfig()`
- `getColors()`
- modelos y servicios de la aplicación cuando corresponda

---

## Mejores prácticas

- Valida los argumentos de las tareas.
- Mantén los métodos de las tareas fuertemente tipificados.
- Documenta los métodos de las tareas con PHPDoc.
- Usa `getColors()` para mejorar la legibilidad de la salida CLI.
- Mantén las tareas de aplicación bajo `Osumi\OsumiFramework\App\Task`.
