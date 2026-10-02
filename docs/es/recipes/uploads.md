# Subida de archivos

Las subidas de archivos en **Osumi Framework 9.9** siguen la misma estructura que el resto del manejo de peticiones:

- `ORequest` expone los archivos subidos.
- Los DTOs pueden validar los datos relacionados con la subida.
- Los componentes orquestan la petición.
- Los servicios son adecuados para lógica reutilizable de procesamiento de archivos.
- Los Middlewares pueden proteger las rutas de subida.

---

# 1. Acceder a un archivo subido

PHP almacena la información de los archivos subidos en `$_FILES`.

Osumi Framework la expone mediante:

```php
$file = $req->getFile(
	'photo'
);
```

El valor devuelto sigue la estructura estándar de subida de PHP.

El contexto de Middleware está disponible de forma independiente mediante `getMiddleware()` y `getMiddlewareValue()`.

---

# 2. DTO de subida

Ejemplo:

```php
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\DTO;

use Osumi\OsumiFramework\DTO\ODTO;
use Osumi\OsumiFramework\Web\ORequest;

class PhotoUploadDTO extends ODTO {
	public ?array $photo = null;

	/**
	 * Build the upload DTO.
	 *
	 * @param ORequest $req Current request.
	 */
	public function __construct(ORequest $req) {
		parent::__construct(
			$req
		);

		$this->photo = $req->getFile(
			'photo'
		);
	}
}
```

Valida la presencia del archivo, el error de subida, el tipo MIME, la extensión y el tamaño antes de almacenarlo.

---

# 3. Componente de subida

```php
class UploadPhotoComponent extends OComponent {
	public string $status = 'ok';
	public string $message = '';
	public ?string $filename = null;

	/**
	 * Store the uploaded file.
	 *
	 * @param PhotoUploadDTO $dto Upload DTO.
	 *
	 * @return void
	 */
	public function run(PhotoUploadDTO $dto): void {
		$file = $dto->photo;

		if (
			!is_array($file) ||
			!array_key_exists('error', $file) ||
			$file['error'] !== UPLOAD_ERR_OK ||
			!isset(
				$file['tmp_name'],
				$file['name']
			)
		) {
			$this->status = 'error';
			$this->message = 'Se requiere un archivo válido.';

			return;
		}

		$new_name = uniqid(
			'photo_',
			true
		)
			. '_'
			. basename(
				(string) $file['name']
			);

		$upload_dir = $this->getConfig()->getDir(
			'uploads'
		);

		$destination = $upload_dir
			. $new_name;

		if (
			!move_uploaded_file(
				(string) $file['tmp_name'],
				$destination
			)
		) {
			$this->status = 'error';
			$this->message = 'No se ha podido guardar el archivo.';

			return;
		}

		$this->filename = $new_name;
		$this->message = 'Archivo subido correctamente.';
	}
}
```

---

# 4. Proteger la ruta de subida

```php
ORoute::post(
	'/api/upload-photo',
	UploadPhotoComponent::class,
	[
		OMiddleware::PHASE_BEFORE => [
			LoginMiddleware::class
		]
	]
);
```

El componente de subida no se ejecutará si el Middleware de autenticación detiene la petición.

El contexto autenticado se puede leer mediante:

```php
$id_user = $req->getMiddlewareValue(
	'Login',
	'id'
);
```

o asignar directamente a un DTO mediante `middleware` y `middlewareProperty`.

---

# 5. Validación

Las comprobaciones habituales incluyen:

- `UPLOAD_ERR_OK`
- Tamaño máximo
- Tipo MIME
- Extensión
- Normalización del nombre
- Permisos del destino

No confíes únicamente en los metadatos MIME proporcionados por el cliente.

---

# 6. Almacenar metadatos

```php
$photo = new Photo();
$photo->filename = $new_name;
$photo->user_id = $id_user;
$photo->save();
```

Para lógica de almacenamiento reutilizable, usa un servicio.

---

# 7. Buenas prácticas

- Mantén los directorios de subida fuera del acceso público salvo que los archivos deban ser públicos.
- Genera nombres únicos.
- Valida tamaño, tipo MIME y extensión.
- Usa servicios cuando crezca la lógica de procesamiento de archivos.
- Usa Middlewares cuando las subidas requieran autenticación o autorización.
- Usa contexto de Middleware en lugar de IDs de usuario proporcionados por el cliente.

---

# 8. Resumen

Un flujo canónico de subida es:

1. Proteger la ruta con Middleware si es necesario.
2. Leer el archivo con `ORequest::getFile()`.
3. Validar la subida.
4. Guardar el archivo de forma segura.
5. Persistir metadatos si es necesario.
6. Usar contexto de Middleware de confianza para asociar la propiedad autenticada.
