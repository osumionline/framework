# Componentes

Los componentes en Osumi Framework son pequeñas piezas de código reutilizables. Normalmente renderizan una plantilla, aunque desde **Osumi Framework 9.10** también pueden producir directamente una respuesta HTTP por streaming mediante `OStreamResponse`.

Un componente tradicional se compone de:

- Una clase PHP que extiende `OComponent`.
- Un archivo de plantilla (`php`, `html`, `json` o `xml`, según el uso).

Un componente exclusivamente streaming puede omitir la plantilla cuando su método `run()` declara explícitamente `OStreamResponse` como tipo de retorno.

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

Cuando un componente con plantilla se utiliza como acción principal para una URL, el framework prepara automáticamente el `Content-Type` según la extensión:

- `.json`: `application/json`.
- `.xml`: `text/xml`.
- `.html` / `.php`: `text/html`.

Las respuestas `OStreamResponse` definen sus propias cabeceras HTTP.

### Anidación de componentes

Los componentes tradicionales pueden anidarse para favorecer la reutilización.

```php
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Father;

use Osumi\OsumiFramework\App\Component\Child\ChildComponent;
use Osumi\OsumiFramework\Core\OComponent;

class FatherComponent extends OComponent {
	public ?ChildComponent $child = null;

	/**
	 * Prepare the nested component.
	 *
	 * @return void
	 */
	public function run(): void {
		$this->child = new ChildComponent();
		$this->child->name = 'Nombre del hijo';
	}
}
```

Un `OStreamResponse` no puede renderizarse como componente anidado ni convertirse a string.

### Sintaxis y acceso a las plantillas

1. **Plantillas PHP (`.php`)** pueden ejecutar PHP y acceder a propiedades públicas como variables.
2. **Plantillas estáticas/estructuradas (`.html`, `.json`, `.xml`)** usan `{{ variable_name }}`.

---

## El método `run()`

Un componente puede definir un método `run()` opcional.

Cuando el componente se utiliza como acción de una ruta, se admiten estos parámetros:

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
- No se admite más de un parámetro.
- El parámetro, cuando existe, debe ser no nullable.

El método puede preparar propiedades para una plantilla o devolver un `OStreamResponse`.

### Contexto de Middleware

`ORequest` expone el contexto publicado por los Middlewares ejecutados.

```php
public function run(ORequest $req): void {
	$login = $req->getMiddleware(
		'Login'
	);
}
```

```php
public function run(ORequest $req): void {
	$id = $req->getMiddlewareValue(
		'Login',
		'id'
	);
}
```

`LoginMiddleware` se expone mediante el nombre público `Login`.

Un contexto inexistente devuelve un array vacío y una propiedad inexistente devuelve `null`.

Consulta `/docs/es/concepts/middlewares.md`.

---

## Respuestas streaming con `OStreamResponse`

Una respuesta streaming permite enviar archivos grandes o contenido generado progresivamente sin materializar el cuerpo completo en memoria.

```php
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Download;

use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Web\OStreamResponse;

class DownloadComponent extends OComponent {
	/**
	 * Stream a file to the client.
	 *
	 * @return OStreamResponse Streamed HTTP response.
	 */
	public function run(): OStreamResponse {
		$file = '/path/to/file.zip';
		$stream = fopen(
			$file,
			'rb'
		);

		if ($stream === false) {
			throw new \RuntimeException(
				'Could not open file.'
			);
		}

		$size = filesize(
			$file
		);

		if ($size === false) {
			fclose(
				$stream
			);

			throw new \RuntimeException(
				'Could not determine file size.'
			);
		}

		return new OStreamResponse(
			$stream,
			[
				'Content-Type' => 'application/zip',
				'Content-Length' => strval($size),
				'Content-Disposition' => 'attachment; filename="file.zip"'
			]
		);
	}
}
```

La plantilla puede omitirse porque `run()` declara explícitamente `OStreamResponse`.

También se admiten los parámetros habituales de ruta:

```php
public function run(ORequest $req): OStreamResponse
```

```php
public function run(MyDTO $dto): OStreamResponse
```

El constructor de `OStreamResponse` recibe:

1. un stream legible;
2. cabeceras HTTP;
3. código HTTP, `200` por defecto;
4. tamaño de bloque, 1 MiB por defecto;
5. si el framework debe cerrar el stream, `true` por defecto.

### Ciclo de vida streaming

```text
Middlewares before
↓
Componente
↓
OStreamResponse
↓
Middlewares afterRender
↓
Middlewares afterResponse
↓
Cierre de conexiones de base de datos
↓
Cabeceras HTTP
↓
Emisión del stream por bloques
```

Reglas:

- No se aplica layout.
- No se envía ningún byte antes de terminar `afterRender` y `afterResponse`.
- Los Middlewares pueden cambiar cabeceras y estado HTTP.
- Los Middlewares no pueden sustituir `body` mientras la respuesta sea streaming.
- `$data['is_streaming_response']` vale `true` durante esas fases.
- Si un Middleware hace `stop` antes de la emisión, el stream se descarta y se genera la respuesta de error normal.
- Los streams propiedad del framework se cierran al finalizar, al descartarse o si la respuesta se destruye inesperadamente.
- Si falla la lectura una vez iniciada la emisión, la respuesta HTTP ya no puede sustituirse de forma segura por otro cuerpo.

---

## Acceso a opciones globales

Los componentes pueden acceder a servicios del framework como:

- `getConfig()`: `OConfig` global.
- `getLog()`: `OLog` del componente.
- `getSession()`: `OSession`.

---

## Renderizado de componentes

Los componentes basados en plantilla pueden convertirse a string:

```php
$cmp = new BooksComponent();
echo strval($cmp);
```

Un componente que devuelve `OStreamResponse` debe ser gestionado por el pipeline HTTP del framework y no puede convertirse a string.

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

Formatea una cadena de fecha. Formato predeterminado: `d/m/Y H:i:s`.

## `number`

Usa `number_format()`.

## `string`

Aplica `urlencode()` y devuelve una cadena entre comillas.

## `plain`

Codifica una cadena como un valor entre comillas seguro para JSON sin aplicar codificación URL. Conserva Unicode y escapa correctamente los caracteres sensibles para JSON.

## `bool`

Produce `true`, `false` o `null`.

---

## Mejores prácticas

- Mantén las plantillas simples.
- Usa `run()` para preparar datos.
- Usa propiedades públicas tipificadas.
- Prefiere valores nullable como `?type = null` cuando corresponda.
- Usa `OStreamResponse` para cuerpos grandes o progresivos que no deben cargarse completos en memoria.
- Define explícitamente `OStreamResponse` como retorno si el componente no tiene plantilla.
