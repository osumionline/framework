# Componentes

Los componentes en Osumi Framework son pequeñas piezas de código reutilizables que renderizan una plantilla. Un componente se compone de:

- Una clase PHP que extiende `OComponent`.
- Un archivo de plantilla (`php`, `html`, `json` o `xml`, según el uso).

Se crea una instancia del componente, se le asignan propiedades y, a continuación, se renderiza.

---

## Estructura básica de un componente

### Clase del componente

```php
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Email\LostPassword;

use Osumi\OsumiFramework\Core\OComponent;

class LostPasswordComponent extends OComponent {
	public ?string $token = null;
}
```

### Archivo de plantilla

```html
<div>
	Token: {{ token }}
</div>
```

---

## Funciones avanzadas

### Encabezados automáticos de tipo de contenido

Cuando un componente se utiliza como acción principal para una URL, el framework envía automáticamente el `Content-Type` adecuado según la extensión de la plantilla:

- `.json`: `application/json`.
- `.xml`: `application/xml`.
- `.html` / `.php`: `text/html`.

### Anidación de componentes

Los componentes pueden anidarse para favorecer la reutilización.

```php
<?php

declare(strict_types=1);

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

1. **Plantillas PHP (`.php`)** pueden ejecutar PHP y acceder a propiedades públicas como variables.
2. **Plantillas estáticas/estructuradas (`.html`, `.json`, `.xml`)** usan `{{ variable_name }}`.

---

## El método `run()`

Un componente puede definir un método `run()` opcional.

Cuando el componente se utiliza como acción de una ruta, se admiten exactamente estas firmas:

```php
public function run(): void
```

```php
public function run(ORequest $req): void
```

```php
public function run(MyDTO $dto): void
```

Comportamiento:

- `run()` no recibe datos de la petición.
- `run(ORequest $req)` recibe la petición actual.
- `run(MyDTO $dto)` recibe un DTO rellenado a partir de la petición actual. `MyDTO` debe extender `ODTO`.
- Los DTO se reconocen por herencia de `ODTO`, no por su namespace.
- No se admite ninguna otra firma.
- El único parámetro, cuando existe, debe ser no nullable.

`ORequest` proporciona accesores tipificados como:

- `getParamString('name')`
- `getParamInt('name')`
- `getParamFloat('name')`
- `getParamBool('name')`

### Contexto de Middleware

`ORequest` también expone el contexto publicado por los Middlewares ejecutados.

Obtener todo el contexto publicado por un Middleware:

```php
public function run(ORequest $req): void {
	$login = $req->getMiddleware(
		'Login'
	);
}
```

Obtener un único valor:

```php
public function run(ORequest $req): void {
	$id = $req->getMiddlewareValue(
		'Login',
		'id'
	);
}
```

`LoginMiddleware` se expone mediante el nombre público `Login`.

Un contexto de Middleware inexistente devuelve un array vacío, mientras que una propiedad de contexto inexistente devuelve `null`.

Consulta `/docs/es/concepts/middlewares.md`.

### Ejemplos

```php
class BooksComponent extends OComponent {
	public array $books = [];

	public function run(): void {
		$this->books = [
			'Libro A',
			'Libro B'
		];
	}
}
```

```php
class GetBookComponent extends OComponent {
	public ?Book $book = null;

	public function run(ORequest $req): void {
		$id_book = $req->getParamInt(
			'id'
		);

		$this->book = Book::findOne([
			'id' => $id_book
		]);
	}
}
```

---

## Acceso a opciones globales

Los componentes pueden acceder a servicios del framework como:

- `getConfig()`: `OConfig` global.
- `getLog()`: `OLog` del componente.
- `getSession()`: `OSession`.

---

## Renderizado de componentes

```php
$cmp = new BooksComponent();
echo strval($cmp);
```

---

# Pipes de plantilla

Las plantillas de Osumi Framework admiten pipes de estilo Angular.

### Sintaxis

```text
{{ value | pipeName }}
{{ value | pipeName:param }}
{{ value | pipeName:param1:param2 }}
```

Los pipes son procesados por `OPipeFunctions`.

## `date`

Formatea una cadena de fecha.

```text
{{ user.created_at | date }}
{{ user.created_at | date:"d/m/Y" }}
```

Formato predeterminado:

```text
d/m/Y H:i:s
```

## `number`

Usa `number_format()` de PHP.

```text
{{ price | number }}
{{ price | number:2 }}
{{ price | number:2:".":"," }}
```

## `string`

Aplica `urlencode()` y devuelve una cadena entre comillas.

```text
{{ user.name | string }}
```

Ejemplo:

```text
John Doe → "John+Doe"
```

## `plain`

Codifica una cadena como un valor entre comillas seguro para JSON sin aplicar codificación URL.

```text
{{ user.name | plain }}
```

Comportamiento:

- `null` → `null`
- Se conserva Unicode.
- Las barras no se escapan.
- Los caracteres sensibles para JSON se escapan correctamente.

Ejemplos:

```text
John Doe → "John Doe"
Dijo "hola" → "Dijo \"hola\""
```

Este pipe es especialmente útil en plantillas JSON.

## `bool`

Produce:

```text
true
false
null
```

### Ejemplo JSON

```json
{
	"id": {{ user.id | number }},
	"name": {{ user.name | plain }},
	"slug": {{ user.slug | string }},
	"created": {{ user.created_at | date:"d/m/Y" }},
	"active": {{ user.active | bool }}
}
```

### Resumen

| Pipe     | Propósito | Notas |
| -------- | --------- | ----- |
| `date`   | Formatear fechas | Admite máscaras personalizadas |
| `number` | Formatear números | Admite decimales y separadores |
| `string` | Codificar cadenas como URL | Añade comillas |
| `plain`  | Cadenas seguras para JSON | Añade comillas sin codificación URL |
| `bool`   | Normalizar booleanos | `true` / `false` / `null` |

---

### Componentes ligados a modelos

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

- Mantén las plantillas simples.
- Usa `run()` para preparar datos.
- Usa propiedades públicas tipificadas.
- Prefiere valores nullable como `?type = null` cuando corresponda.
