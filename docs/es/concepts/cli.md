# CLI (Interfaz de Línea de Comandos)

Osumi Framework proporciona una potente interfaz de línea de comandos para automatizar tareas de desarrollo, administrar la base de datos y ejecutar scripts personalizados. El punto de entrada para todos los comandos CLI es el archivo `of`, ubicado en la raíz del proyecto.

---

## Uso

Los comandos se ejecutan usando PHP desde la terminal:

```bash
php of <opcion> [parámetros]

```

Si el archivo se ejecuta sin parámetros, se muestra la lista de opciones disponibles (tanto las del Framework como las creadas por el usuario).

### Comandos Principales

El framework incluye varias tareas integradas:

- **`add`**: Crea nuevas acciones, servicios, tareas, modelos, componentes o filtros.
- **`generateModel`**: Crea el esquema de la base de datos SQL a partir de las clases del modelo.
- **`generateModelFrom` / `generateModelFromDB`**: Ingeniería inversa para crear modelos.
- **`backupAll` / `backupDB`**: Crea copias de seguridad de archivos y/o bases de datos.
- **`extractor`**: Exporta toda la aplicación en un único archivo autoextraíble.
- **`reset`**: Borra todos los datos que no pertenecen al framework para una nueva instalación.
- **`version`**: Muestra la versión actual del framework.

---

## Tareas personalizadas

Puede ampliar la CLI creando sus propias tareas. Cualquier clase ubicada en `src/Task/` que extienda `OTask` aparecerá automáticamente como una opción disponible en el comando `of`.

### Creación de una tarea

Una tarea requiere dos elementos principales:

1. **`__toString()`**: Devuelve una breve descripción de la tarea (mostrada en el menú de ayuda).
2. **`run(array $options)`**: La lógica que se ejecutará.

Ejemplo: `AddUserTask.php`

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Task;

use Osumi\OsumiFramework\Core\OTask;
use Osumi\OsumiFramework\App\Model\User;

class AddUserTask extends OTask {
  public function __toString() {
    return "addUser: Tarea para crear nuevos usuarios";
  }

  public function run(array $options=[]): void {
    $name = $options['name'] ?? $options[0] ?? null;

    if (is_null($name)) {
      echo "Error: Se requiere el nombre.\n";
      return;
    }

    $u = new User();
    $u->name = $name; $u->save();

    echo "Usuario " . $nombre . " creado correctamente.\n";
  }
}

```

---

## Manejo de argumentos

El método `run` recibe un array `$options` que admite dos estilos de entrada:

### 1. Argumentos posicionales

Se pasan directamente después del nombre del comando.

```bash
php of addUser "John Doe"
# $options = [0 => "John Doe"]

```

### 2. Parámetros con nombre

Se utiliza la sintaxis `--key value`. Esto genera un array asociativo.

```bash
php of addUser --name "John Doe"
# $options = ["name" => "John Doe"]

```

> **Nota sobre `$options$`**: El parámetro `$options` siempre es un array; no se pueden usar DTOs en las tareas.

---

## Características de las tareas

Las clases que extienden `OTask` tienen acceso a varias utilidades integradas:

- **`$this->getConfig()`**: Acceder a la configuración de la aplicación.
- **`$this->getColors()`**: Usar la utilidad `OColors` para mostrar texto en color en la consola.
- **Acceso ORM**: Se puede usar cualquier clase de modelo para realizar operaciones de base de datos como en un componente.
- **Ejecución programática**: Las tareas se pueden instanciar y ejecutar desde otras partes del código, no solo desde la terminal.

---

## Mejores prácticas

- **Mensajes de ayuda**: Use el método `run` para comprobar si los argumentos necesarios están presentes y mostrar un ejemplo de uso si faltan.
- **Código de color**: Use `$this->getColors()->getColoredString()` para resaltar los errores en rojo o los mensajes de éxito en verde para una mejor experiencia de usuario.
- **Espacio de nombres**: Asegúrese de que sus tareas personalizadas se encuentren en el espacio de nombres `Osumi\OsumiFramework\App\Task`.
