# Componentes

Los componentes en Osumi Framework son pequeños fragmentos de código reutilizables que renderizan una plantilla. Un componente se compone de:

- Una clase PHP que extiende `OComponent`.
- Un archivo de plantilla (php/html/json/xml, según el uso).

Se crea una instancia del componente, se le asignan propiedades y, a continuación, se renderiza, generalmente mediante `render()` o convirtiendo el objeto a una cadena de texto.

---

## Estructura básica del componente

### Clase del componente

Ejemplo de un archivo de clase de componente (`LostPasswordComponent.php`):

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Email\LostPassword;

use Osumi\OsumiFramework\Core\OComponent;

class LostPasswordComponent extends OComponent {
  /**
  * Las propiedades públicas se exponen automáticamente a la plantilla.
  */
  public ?string $token = null;
}

```

### Archivo de plantilla

Ejemplo de plantilla (`LostPasswordTemplate.php`):

```php
<div>
  Token: {{ token }}
</div>

```

---

## Funciones avanzadas

### Encabezados automáticos de tipo de contenido

Cuando un componente se utiliza como acción principal para una URL, el framework envía automáticamente el encabezado `Content-Type` adecuado según la extensión del archivo de la plantilla:

- `.json`: Envía `Content-type: application/json`.
- `.xml`: Envía `Content-type: application/xml`.
- `.html` / `.php`: Envía `Content-type: text/html`.

### Anidación de componentes

Los componentes se pueden encadenar o anidar. Un componente más grande puede incluir y renderizar componentes más pequeños dentro de su lógica o plantilla para facilitar su reutilización.

Ejemplo de un archivo de clase de componente secundario (`ChildComponent.php`):

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Child;

use Osumi\OsumiFramework\Core\OComponent;

class ChildComponent extends OComponent {
  public ?string $name = null;
}

```

### Archivo de plantilla

Ejemplo de la plantilla (`ChildTemplate.php`):

```php
<div>
  Nombre: {{ name }}
</div>

```

Ejemplo de un archivo de clase de componente padre que utiliza un componente hijo (`FatherComponent.php`):

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Father;

use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\App\Component\Child\ChildComponent;

class FatherComponent extends OComponent {
  public ?ChildComponent $child = null;

  public function run(): void {
    $this->child = new ChildComponent();
    $this->child->name = 'Nombre del hijo';
  }
}

```

### Archivo de plantilla

Ejemplo de plantilla (`FatherTemplate.php`):

```php
<div>
  Hijo: {{ hijo }}
</div>

```

El resultado sería:

```php
Hijo: Nombre: Nombre del hijo
```

### Sintaxis y acceso a las plantillas

Las plantillas acceden a las propiedades públicas del componente de forma diferente según la extensión del archivo:

1. **Plantillas PHP (`.php`)**:

- Pueden ejecutar código PHP nativo.
- Acceden a las propiedades públicas como variables estándar (p. ej., `$token`).

2. **Plantillas estáticas/estructuradas (`.html`, `.json`, `.xml`)**:

- Use la notación de doble llave para mostrar las propiedades públicas: `{{ variable_name }}`.

---

## El método `run()` (opcional)

Un componente puede definir un método `run()` opcional. Si está presente, se ejecuta automáticamente al inicio del proceso `render()` para preparar los datos antes de procesar la plantilla.

Cuando el componente se utiliza como una acción (un componente renderizado como resultado de una ruta activada), la función `run()` puede recibir datos de las solicitudes:

- Si la función `run()` tiene un DTO, se utilizarán los datos de la solicitud para rellenar sus campos.
- De lo contrario, se le pasará un objeto `ORequest` genérico.

La clase `ORequest` tiene métodos para obtener los datos pasados, como valores de formulario o parámetros pasados ​​a través de la URL:

- **`getParamString('name')`**: devuelve el valor del campo `name' pasado a la ruta como una cadena (null si no está presente).
- **`getParamInt('name')`**: devuelve el valor del campo 'name' pasado a la ruta como un entero (nulo si no está presente).
- **`getParamFloat('name')`**: devuelve el valor del campo 'name' pasado a la ruta como un float (nulo si no está presente).
- **`getParamBool('name')`**: devuelve el valor del campo 'name' pasado a la ruta como un booleano (nulo si no está presente).

Si una ruta tiene un filtro definido, la clase `ORequest` también proporciona maneras de acceder al resultado de su ejecución:

```php
public function run(ORequest $req): void {
  $login_filter = $req->getFilter('login'); // Accederá al resultado devuelto desde el archivo LoginFilter
  $filters = $req->getFilters(); // Accederá al resultado devuelto por cada filtro aplicado como una matriz asociativa ['login' => [...]]
}
```

**Ejemplo:**

```php
class BooksComponent extends OComponent {
  public array $books = [];

  public function run(): void {
    $this->books = ['Book A', 'Book B'];
  }
}

```

```php
class GetBookComponent extends OComponent {
  public ?Book $book = null;

  public function run(ORequest $req): void {
    $id_book = $req->getParamInt('id');
    $this->book = Book::findOne(['id' => $id_book]);
  }
}

```

## Acceso a opciones globales

Los componentes tienen métodos para Acceso a opciones globales como la configuración de la aplicación, registros o datos de sesión:

- **`getConfig()`**: Devuelve `OConfig` global para leer rutas o valores definidos por el usuario (secretos, direcciones de correo electrónico, etc.).
- Documentación: docs/es/concepts/config.md
- **`getLog()`**: Devuelve la instancia `OLog` del componente. El usuario puede registrar información mediante métodos como `debug`, `info` o `error`.
- Documentación: docs/es/concepts/log.md
- **`getSession()`**: Devuelve la instancia `OSession` que permite acceder a los parámetros de $\_SESSION.

---

## Convenciones de nomenclatura

Para mantener la coherencia, siga estos patrones de nomenclatura:

| Tipo de archivo         | Convención          | Ejemplo             |
| ----------------------- | ------------------- | ------------------- |
| **Clase de componente** | `XxxComponent.php`  | `UserComponent.php` |
| **Plantilla**           | `XxxTemplate.<ext>` | `UserTemplate.json` |

---

## Componentes de renderizado

Un flujo de renderizado típico implica instanciar el componente, asignar datos y mostrar el resultado.

```php
$cmp = new BooksComponent();

// Puedes convertirlo a una cadena para activar run() y render()
echo strval($cmp);

```

# Canalizaciones de plantilla

Las plantillas de Osumi Framework admiten **pipes de estilo Angular**, lo que permite transformar valores directamente dentro de la plantilla.

### Sintaxis

{{ valor | nombreDeLaPipa}}
{{ valor | nombreDeLaPipa:param}}
{{ valor | pipeName:param1:param2 }}

### Propósito

Los pipes permiten formatear:

- Fechas
- Números
- Cadenas
- Booleanos

Los pipes son procesados por la clase interna **OPipeFunctions**.

---

# Pipes disponibles

A continuación se muestran todos los pipes integradas y su comportamiento, derivadas de las funciones de `OPipeFunctions.php`.

---

## 1. `date`

Formatea una cadena de fecha (formato `Y-m-d H:i:s`) a un nuevo formato.

### Sintaxis

{{ user.created_at | date }}
{{ user.created_at | date:"d/m/Y" }}
{{ user.created_at | fecha:"d-m-A H:i" }}

### Comportamiento

- La entrada debe ser `A-m-d H:i:s`
- La salida se formatea con `DateTime::format()` de PHP
- Si la fecha no es válida → `"null"`

### Formato predeterminado

d/m/A H:i:s

---

## 2. `number`

Formatea los números con `number_format()` de PHP.

### Sintaxis

{{ precio | número }}
{{ precio | número:2 }}
{{ precio | number:2:".":"," }}

### Comportamiento

- Decimales predeterminados: **2**
- Separador decimal predeterminado: `"."`
- Separador de miles predeterminado: `""`
- Si el valor es nulo → `"null"`

Ejemplos:

1234.5 → 1234.50
1234.5 → 1,234.50 (si el separador de miles es ",")

---

## 3. `string`

Aplica `urlencode()` a una cadena de texto.

### Sintaxis

{{ user.name | string }}

### Comportamiento

- Nulo → `"null"`
- Valor → `"urlencoded string"`

Ejemplo:

"John Doe" → "John+Doe"

---

## 4. `bool`

Convierte valores booleanos a:

true
false
null

### Sintaxis

{{ user.isAdmin | bool }}

---

# Cómo se comportan los pipes en las plantillas JSON

Dado que las plantillas como `.json` se representan como cadenas, los pipes garantizan automáticamente:

- Las cadenas se entrecomillan cuando es necesario
- Los valores booleanos aparecen sin comillas
- Los números aparecen sin comillas
- Los valores nulos aparecen como `null`

Esto garantiza una salida JSON válida.

---

# Ejemplos

```json
{
  "id": {{ user.id | number }},
  "name": {{ user.name | string }},
  "created": {{ user.created_at | date:"d/m/Y" }},
  "active": {{ user.active | bool }}
}
```

---

# Resumen de los pipes

| Pipe     | Propósito                   | Notas                          |
| -------- | --------------------------- | ------------------------------ |
| `date`   | Formatear valores de fecha  | Acepta máscaras personalizadas |
| `number` | Formatear valores numéricos | Admite decimales y separadores |
| `string` | Codificar cadenas en URL    | Añade comillas                 |
| `bool`   | Normalizar salida booleana  | `true` / `false` / `null`      |

### Componentes ligados al modelo

Cuando los componentes representan vistas del modelo, puedes usar propiedades tipificadas con tus clases del modelo.

```php
namespace Osumi\OsumiFramework\App\Component\Model\User;

use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\App\Model\User;

class UserComponent extends OComponent {
  public ?User $user = null;
}

```

---

## Mejores prácticas

- **Mantenga las plantillas simples**: Limítelas a una lógica de visualización mínima.
- **Use `run()`**: Úselo para preparar datos o realizar cálculos antes de renderizar.
- **Propiedades tipificadas**: Use propiedades públicas tipificadas para mayor claridad.
- **Valores predeterminados**: Prefiera los valores predeterminados `?type = null` para evitar errores de "propiedad no inicializada" en PHP 8.3+.
