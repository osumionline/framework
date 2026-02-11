# Tareas Comunes

Este documento describe **tareas comunes del mundo real** en Osumi Framework y la **forma recomendada (canónica)** de resolverlas.

Si existen varios enfoques posibles, solo se muestra la solución idiomática de Osumi Framework.

Todos los ejemplos asumen:

- PHP 8.3+
- `declare(strict_types=1);`
- Espacios de nombres adecuados

---

# 1. Crear un punto final JSON simple

## Objetivo

Devolver JSON desde `/api/ping`.

### Ruta

```php
use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\App\Module\Api\Ping\PingComponent;

ORoute::get('/api/ping', PingComponent::class);
```

### Componente

```php
class PingComponent extends OComponent {
  public string $status = 'ok';
}
```

### Plantilla (`PingTemplate.json`)

```json
{
	"status": "{{ status }}"
}
```

---

# 2. Recibir entrada mediante un DTO

## Objetivo

Crear un usuario utilizando la entrada validada.

### DTO

```php
class CreateUserDTO extends ODTO {
  #[ODTOField(required: true)]
  public ?string $name = null;

  #[ODTOField(required: true)]
  public ?string $email = null;
}
```

### Componente

```php
class CreateUserComponent extends OComponent {
  public string $status = 'ok';

  public function run(CreateUserDTO $dto): void {
    if (!$dto->isValid()) {
      $this->status = 'error';
      return;
    }

    $u = new User();
    $u->name = $dto->name;
    $u->email = $dto->email;
    $u->save();
  }
}
```

---

# 3. Proteger un endpoint con autenticación

## Objetivo

Solo los usuarios autenticados pueden acceder a `/api/profile`.

### Ruta

```php
ORoute::get('/api/profile', ProfileComponent::class, [LoginFilter::class]);
```

### Acceder a los datos del filtro

```php
public function run(ORequest $req): void {
  $login = $req->getFilter('Login');
  $user_id = $login['id'];
}
```

---

# 4. Leer un parámetro de URL

## Objetivo

Acceder a `/user/:id`.

### Ruta

```php
ORoute::get('/user/:id', UserComponent::class);
```

### Componente

```php
public function run(ORequest $req): void {
  $id = $req->getParamInt('id');
  $this->user = User::findOne(['id' => $id]);
}
```

---

# 5. Usar un servicio dentro de un componente

## Objetivo

Extraer la lógica de negocio del componente.

### Servicio

```php
class UserService extends OService {
  public function getAll(): array {
    return User::where([]);
  }
}
```

### Componente

```php
class UsersComponent extends OComponent {
  private ?UserService $us = null;
  public array $users = [];

  public function __construct() {
    parent::__construct();
    $this->us = inject(UserService::class);
  }

  public function run(): void {
    $this->users = $this->us->getAll();
  }
}
```

---

# 6. Guardar o actualizar un modelo

## Objetivo

Insertar o actualizar automáticamente usando `save()`.

```php
$user = new User();
$user->name = 'Alice';
$user->email = 'alice@mail.com';
$user->save(); // INSERTAR

$user = User::findOne(['id' => 1]);
$user->name = 'Updated Name';
$user->save(); // ACTUALIZAR
```

---

# 7. Devolver una lista de modelos (JSON)

## Objetivo

Devolver usuarios usando un componente de modelo.

### Dentro del componente

```php
public ?UserListComponent $list = null;

public function run(): void {
  $this->list = new UserListComponent();
  $this->list->list = User::where([]);
}
```

### Plantilla

```json
{
  "users": [
    {{ list }}
  ]
}
```

---

# 8. Gestionar la subida de archivos

## Objetivo

Subir un archivo de forma segura.

### DTO

```php
class UploadDTO extends ODTO {
  #[ODTOField(required: true)]
  public ?array $file = null;

  public function __construct(ORequest $req) {
    parent::__construct($req);
    $this->file = $req->getFile('file');
  }
}
```

### Componente

```php
public function run(UploadDTO $dto): void {
  if (!$dto->isValid()) return;

  $file = $dto->file;
  $dest = $this->getConfig()->getDir('uploads') . basename($file['name']);
  move_uploaded_file($file['tmp_name'], $dest);
}
```

---

# 9. Usar un diseño personalizado

## Objetivo

Aplicar un diseño a un grupo de rutas.

```php
ORoute::layout(MainLayoutComponent::class, function() {
  ORoute::get('/home', HomeComponent::class);
});
```

O combinar prefijo + diseño:

```php
ORoute::group('/admin', AdminLayoutComponent::class, function() {
  ORoute::get('/dashboard', DashboardComponent::class);
});
```

Los diseños envuelven la salida del componente renderizado y reciben:

- `title`
- `body`

---

# 10. Gestionar correctamente los errores de validación

## Validación de DTO

```php
if (!$dto->isValid()) {
  $this->status = 'error';
  $this->errors = $dto->getValidationErrors();
  return;
}
```

## Modelo no encontrado

```php
$user = User::findOne(['id' => $id]);
if (is_null($user)) {
  $this->status = 'error';
  return;
}
```

---

# Resumen

Estas recetas muestran la **forma canónica** de realizar tareas comunes en Osumi Framework:

- Usar DTO para la validación de entrada
- Usar filtros para la autenticación
- Usar servicios para la lógica de negocio
- Mantener componentes delgados
- Usar componentes de modelo para la representación JSON
- Aplicar diseños mediante enrutamiento

Siga estos patrones para lograr aplicaciones consistentes y predecibles.
