# Subida de Archivos

La gestión de la subida de archivos en **Osumi Framework** sigue los mismos principios de diseño que el resto del sistema:

- Los **DTO** gestionan y validan los datos entrantes
- Los **componentes** orquestan la operación
- El archivo subido está disponible a través de **`ORequest`**
- Usted decide dónde y cómo se almacenarán los archivos

Esta receta muestra cómo aceptar un archivo subido (por ejemplo, desde un HTML `<input type="file" name="photo">`), procesarlo en un componente y almacenarlo correctamente.

---

# 1. Resumen del funcionamiento de la subida de archivos

Cuando un usuario envía un formulario con un archivo, PHP coloca la información del archivo subido dentro de `$_FILES`.

Osumi Framework fusiona los datos de la solicitud (parámetros, cabeceras, filtros, archivos) en una instancia de **`ORequest`**.

Desde el método `run()` de su componente, puede acceder a:

```php
$req->getFile("photo");
```

Esto devuelve la matriz de carga estándar de PHP:

```php
[
  'name'     => 'example.png',
  'type'     => 'image/png',
  'tmp_name' => '/tmp/phpXYZ123',
  'error'    => 0,
  'size'     => 123456
]
```

Para mantener la coherencia con el resto del framework, normalmente se encapsula este acceso dentro de un **DTO**, de modo que la validación y la estructura se mantengan limpias.

---

# 2. Creación de un DTO para la carga de archivos

Los DTO pueden aceptar cualquier parámetro de solicitud, incluidos los archivos.
Dado que los archivos subidos no provienen de encabezados ni filtros, se leen directamente desde `ORequest` dentro del constructor del DTO.

DTO de ejemplo:

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\DTO;

use Osumi\OsumiFramework\DTO\ODTO;
use Osumi\OsumiFramework\DTO\ODTOField;
use Osumi\OsumiFramework\Web\ORequest;

class PhotoUploadDTO extends ODTO {
  #[ODTOField(required: true)]
  public ?array $photo = null;

  public function __construct(ORequest $req) {
    parent::__construct($req);

    // Cargar el archivo subido
    $this->photo = $req->getFile('photo');

    // Validar la presencia del archivo
    if ($this->photo === null || $this->photo['error'] !== 0) {
      $this->validation_errors[] = "A valid file is required.";
    }
  }
}
```

### Notas:

- `required: true` garantiza que el DTO no sea válido si falta el archivo.
- `getFile('photo')` recupera la carga.
- Se puede ampliar la validación (tamaño del archivo, tipo MIME, etc.).

---

# 3. Creación del componente de carga

El componente recibe el DTO y procesa el archivo subido.

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Api\UploadPhoto;

use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\App\DTO\PhotoUploadDTO;

class UploadPhotoComponent extends OComponent {
  public string $status = 'ok';
  public string $message = '';
  public ?string $filename = null;

  public function run(PhotoUploadDTO $dto): void {
    if (!$dto->isValid()) {
      $this->status = 'error';
      $this->message = implode(", ", $dto->getValidationErrors());
      return;
    }

    // Acceder a los datos del archivo subido
    $file = $dto->photo;

    // Generar una ruta de archivo de destino
    $new_name = uniqid("photo_") . "_" . basename($file['name']);
    $upload_dir = $this->getConfig()->getDir('uploads');
    $dest = $upload_dir . $new_name;

    // Mover el archivo subido
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
      $this->status = 'error';
      $this->message = 'Error al almacenar el archivo.';
      return;
    }

    $this->filename = $new_name;
    $this->message = 'Archivo subido correctamente.';
  }
}
```

### Puntos clave:

- `run()` recibe un `PhotoUploadDTO`
- El DTO garantiza la validez
- `move_uploaded_file()` almacena el archivo de forma segura
- Debe almacenar las subidas en un directorio configurado (`uploads` o similar)
- Los componentes se mantienen limpios y legibles

---

# 4. Ejemplo de formulario HTML

Al consumir este endpoint desde una página web:

### Importante:

`enctype="multipart/form-data"` es necesario para que PHP rellene `$_FILES`.

---

# 5. Definición de la ruta

Agregue una ruta que reciba una solicitud POST y se asigne a su componente de subida:

```php
use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\App\Module\Api\UploadPhoto\UploadPhotoComponent;

ORoute::post('/api/upload-photo', UploadPhotoComponent::class);
```

Si la subida requiere autenticación, simplemente añada su filtro:

```php
ORoute::post('/api/upload-photo', UploadPhotoComponent::class, [LoginFilter::class]);
```

---

# 6. Ejemplo de plantilla de respuesta JSON

Si su componente utiliza una plantilla `.json`, el resultado podría ser:

```json
{
	"status": "{{ status }}",
	"message": "{{ message }}",
	"filename": "{{ filename }}"
}
```

---

# 7. Ampliación de la validación

Patrones de validación comunes:

### Extensiones permitidas:

```php
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['png','jpg','jpeg'])) {
  $this->validation_errors[] = "Invalid file extension.";
}
```

### Tamaño máximo de archivo:

```php
if ($file['size'] > 2 * 1024 * 1024) { // 2MB
  $this->validation_errors[] = "File is too large.";
}
```

### Tipos MIME válidos:

```php
$allowed = ['image/png','image/jpeg'];
if (!in_array($file['type'], $allowed)) {
  $this->validation_errors[] = "Invalid file type.";
}
```

Puedes almacenar estas reglas en un servicio dedicado para mantener los DTO limpios.

---

# 8. Almacenamiento de metadatos de archivos en modelos

Si quieres almacenar la información de carga en una base de datos:

```php
$photo = new Photo();
$photo->filename = $new_name;
$photo->user_id = $userId; // si se usa LoginFilter
$photo->save();
```

Esto suele hacerse dentro de un servicio en lugar de directamente en un componente.

---

# 9. Buenas prácticas

- **Usar DTO** para validar los archivos subidos
- **Usar servicios** para la lógica relacionada con los archivos si estos crecen (cambiar el tamaño de las imágenes, generar miniaturas, etc.)
- **Nunca confiar en los metadatos proporcionados por el cliente** (inspeccionar siempre el tipo MIME, la extensión y el tamaño)
- **Mantener los directorios de subida fuera del acceso público** a menos que sea necesario exponer los archivos
- **Nombrar los archivos de forma única** para evitar sobrescribir los archivos de usuario
- **Usar filtros** si solo los usuarios autenticados pueden subir archivos

---

# 10. Resumen

Para implementar subidas en Osumi Framework:

1. Crear un **DTO** para recibir y validar el archivo
2. Crear un **componente** para procesarlo y almacenarlo
3. Agregar una **ruta** que apunte al componente
4. Usar `ORequest->getFile(name)` para acceder a los archivos subidos
5. Almacenarlos usando `move_uploaded_file()`
6. Agregar filtros de autenticación si es necesario

Este enfoque Mantiene la lógica de carga consistente con el diseño del marco: limpio, modular y predecible.
