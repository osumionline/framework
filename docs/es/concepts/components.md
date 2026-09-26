# Componentes

Los componentes en Osumi Framework son pequeños fragmentos de código reutilizables que renderizan una plantilla. Un componente se compone de:

- Una clase PHP que extiende `OComponent`.
- Un archivo de plantilla (php/html/json/xml, según el uso).

Se crea una instancia del componente, se le asignan propiedades y, a continuación, se renderiza, generalmente mediante `render()` o convirtiendo el objeto a una cadena de texto.

---

## Estructura básica del componente

### Clase del componente

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Email\LostPassword;

use Osumi\OsumiFramework\Core\OComponent;

class LostPasswordComponent extends OComponent {
  public ?string $token = null;
}
```

### Archivo de plantilla

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

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Father;

use Osumi\OsumiFramework\App\Component\Child\ChildComponent;
use Osumi\OsumiFramework\Core\OComponent;

class FatherComponent extends OComponent {
  public ?ChildComponent $child = null;

  public function run(): void {
    $this->child = new ChildComponent();
    $this->child->name = 'Nombre del hijo';
  }
}
```

### Sintaxis y acceso a las plantillas

1. **Plantillas PHP (`.php`)** pueden ejecutar código PHP nativo y acceder a las propiedades públicas como variables estándar.
2. **Plantillas estáticas/estructuradas (`.html`, `.json`, `.xml`)** usan la notación de doble llave: `{{ variable_name }}`.

---

## El método `run()` (opcional)

Un componente puede definir un método `run()` opcional. Si está presente, se ejecuta automáticamente al inicio del proceso `render()` para preparar los datos antes de procesar la plantilla.

Cuando el componente se utiliza como una acción (un componente renderizado como resultado de una ruta activada), el método `run()` admite exactamente una de las siguientes firmas:

```php
public function run(): void
```

```php
public function run(ORequest $req): void
```

```php
public function run(MyDTO $dto): void
```

El comportamiento depende de la firma utilizada:

- `run()` no recibe datos de la solicitud y puede utilizarse cuando el componente no necesita acceder a la solicitud actual.
- `run(ORequest $req)` recibe la solicitud actual como una instancia de `ORequest`.
- `run(MyDTO $dto)` recibe un DTO rellenado automáticamente con los datos de la solicitud actual. `MyDTO` debe extender `ODTO`.
- Los DTO se detectan por herencia de `ODTO`, no por su namespace. Por tanto, las clases DTO pueden estar ubicadas en cualquier parte de la aplicación.
- No se admite ninguna otra firma. El método puede recibir como máximo un parámetro, que debe ser un `ORequest` no nullable o una clase que extienda `ODTO`.

La clase `ORequest` tiene métodos para obtener los datos recibidos, como valores de formulario o parámetros pasados a través de la URL:

- **`getParamString('name')`**: devuelve el valor del campo `name` pasado a la ruta como una cadena (null si no está presente).
- **`getParamInt('name')`**: devuelve el valor del campo `name` pasado a la ruta como un entero (null si no está presente).
- **`getParamFloat('name')`**: devuelve el valor del campo `name` pasado a la ruta como un float (null si no está presente).
- **`getParamBool('name')`**: devuelve el valor del campo `name` pasado a la ruta como un booleano (null si no está presente).

Si una ruta tiene un filtro definido, la clase `ORequest` también proporciona maneras de acceder al resultado de su ejecución:

```php
public function run(ORequest $req): void {
  $login_filter = $req->getFilter('login');
  $filters = $req->getFilters();
}
```

**Ejemplos:**

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

---

## Acceso a opciones globales

Los componentes tienen métodos para acceder a opciones globales como la configuración de la aplicación, logs o datos de sesión:

- **`getConfig()`**: Devuelve el `OConfig` global.
- **`getLog()`**: Devuelve la instancia `OLog` del componente.
- **`getSession()`**: Devuelve la instancia `OSession`.

---

## Renderizado de componentes

```php
$cmp = new BooksComponent();
echo strval($cmp);
```

---

# Pipes de plantilla

Las plantillas de Osumi Framework admiten **pipes de estilo Angular**, lo que permite transformar valores directamente dentro de la plantilla.

### Sintaxis

    {{ valor | pipeName }}
    {{ valor | pipeName:param }}
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

## 1. `date`

Formatea una cadena de fecha (formato `Y-m-d H:i:s`) a un nuevo formato.

### Sintaxis

    {{ user.created_at | date }}
    {{ user.created_at | date:"d/m/Y" }}
    {{ user.created_at | date:"d-m-Y H:i" }}

### Comportamiento

- La entrada debe ser `Y-m-d H:i:s`
- La salida se formatea con `DateTime::format()` de PHP
- Si la fecha no es válida → `"null"`

### Formato predeterminado

    d/m/Y H:i:s

---

## 2. `number`

Formatea los números con `number_format()` de PHP.

### Sintaxis

    {{ price | number }}
    {{ price | number:2 }}
    {{ price | number:2:".":"," }}

### Comportamiento

- Decimales predeterminados: **2**
- Separador decimal predeterminado: `"."`
- Separador de miles predeterminado: `""`
- Si el valor es nulo → `"null"`

---

## 3. `string`

Aplica `urlencode()` a una cadena de texto.

### Sintaxis

    {{ user.name | string }}

### Comportamiento

- Nulo → `null`
- Valor → cadena codificada como URL y entre comillas

Ejemplo:

    John Doe → "John+Doe"

---

## 4. `plain`

Codifica una cadena como un valor seguro para JSON sin aplicar codificación URL.

### Sintaxis

    {{ user.name | plain }}

### Comportamiento

- Nulo → `null`
- Valor → cadena entre comillas válida para JSON
- Los caracteres Unicode se conservan
- Las barras no se escapan
- Las comillas y otros caracteres especiales para JSON se escapan correctamente

Ejemplos:

    John Doe → "John Doe"
    Dijo "hola" → "Dijo \"hola\""

Este pipe es especialmente útil en plantillas JSON cuando se quiere conservar el valor original de la cadena sin aplicar codificación URL.

---

## 5. `bool`

Convierte valores booleanos a:

    true
    false
    null

### Sintaxis

    {{ user.isAdmin | bool }}

---

# Cómo se comportan los pipes en las plantillas JSON

Los pipes proporcionan valores adecuados para plantillas estructuradas:

- `string` produce una cadena entre comillas y codificada como URL.
- `plain` produce una cadena entre comillas segura para JSON sin codificación URL.
- Los valores booleanos aparecen sin comillas.
- Los números aparecen sin comillas.
- Los valores nulos aparecen como `null`.

---

# Ejemplos

```json
{
  "id": {{ user.id | number }},
  "name": {{ user.name | plain }},
  "slug": {{ user.slug | string }},
  "created": {{ user.created_at | date:"d/m/Y" }},
  "active": {{ user.active | bool }}
}
```

---

# Resumen de los pipes

| Pipe     | Propósito                    | Notas                               |
| -------- | ---------------------------- | ----------------------------------- |
| `date`   | Formatear valores de fecha   | Acepta máscaras personalizadas      |
| `number` | Formatear valores numéricos  | Admite decimales y separadores      |
| `string` | Codificar cadenas como URL   | Añade comillas                      |
| `plain`  | Cadenas sin codificación URL | Salida segura para JSON con comillas |
| `bool`   | Normalizar salida booleana   | `true` / `false` / `null`           |


### Componentes ligados al modelo

Cuando los componentes representan vistas del modelo, puedes usar propiedades tipificadas con tus clases del modelo.

```php
namespace Osumi\OsumiFramework\App\Component\Model\User;

use Osumi\OsumiFramework\App\Model\User;
use Osumi\OsumiFramework\Core\OComponent;

class UserComponent extends OComponent {
  public ?User $user = null;
}
```

---

## Mejores prácticas

- **Mantén las plantillas simples**: Limítalas a una lógica de visualización mínima.
- **Usa `run()`**: Úsalo para preparar datos o realizar cálculos antes de renderizar.
- **Propiedades tipificadas**: Usa propiedades públicas tipificadas para mayor claridad.
- **Valores predeterminados**: Prefiere `?type = null` para evitar errores de propiedades no inicializadas.
