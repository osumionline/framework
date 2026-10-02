# Tareas comunes

Este documento muestra la forma canónica de resolver tareas habituales en **Osumi Framework 9.9**.

Todos los ejemplos asumen:

- PHP 8.5+
- `declare(strict_types=1);`
- Tipado estricto siempre que sea posible

---

# 1. Crear un endpoint JSON sencillo

```php
ORoute::get(
	'/api/ping',
	PingComponent::class
);
```

```php
class PingComponent extends OComponent {
	public string $status = 'ok';
}
```

```json
{
	"status": {{ status | plain }}
}
```

---

# 2. Recibir entrada mediante un DTO

```php
class CreateUserDTO extends ODTO {
	#[ODTOField(required: true)]
	public ?string $name = null;

	#[ODTOField(required: true)]
	public ?string $email = null;
}
```

```php
class CreateUserComponent extends OComponent {
	public string $status = 'ok';

	/**
	 * Create a user from validated input.
	 *
	 * @param CreateUserDTO $dto Request DTO.
	 *
	 * @return void
	 */
	public function run(CreateUserDTO $dto): void {
		if (!$dto->isValid()) {
			$this->status = 'error';
			return;
		}

		$user = new User();
		$user->name = $dto->name;
		$user->email = $dto->email;
		$user->save();
	}
}
```

---

# 3. Proteger un endpoint con autenticación

```php
ORoute::get(
	'/api/profile',
	ProfileComponent::class,
	[
		OMiddleware::PHASE_BEFORE => [
			LoginMiddleware::class
		]
	]
);
```

Acceder a un valor publicado:

```php
$id_user = $req->getMiddlewareValue(
	'Login',
	'id'
);
```

O asignarlo directamente a un campo DTO:

```php
#[ODTOField(
	required: true,
	middleware: 'Login',
	middlewareProperty: 'id'
)]
public ?int $idUser = null;
```

---

# 4. Leer un parámetro de URL

```php
ORoute::get(
	'/user/:id',
	UserComponent::class
);
```

```php
$id = $req->getParamInt(
	'id'
);
```

---

# 5. Usar un servicio dentro de un componente

```php
class UserService extends OService {
	/**
	 * Return all users.
	 *
	 * @return array<int, User> Users.
	 */
	public function getAll(): array {
		return User::where([]);
	}
}
```

---

# 6. Guardar o actualizar un modelo

```php
$user = new User();
$user->name = 'Alice';
$user->email = 'alice@mail.com';
$user->save();

$user = User::findOne([
	'id' => 1
]);

if ($user !== null) {
	$user->name = 'Nombre actualizado';
	$user->save();
}
```

---

# 7. Devolver una lista de modelos

```php
public ?UserListComponent $list = null;
```

```php
/**
 * Load users.
 *
 * @return void
 */
public function run(): void {
	$this->list = new UserListComponent();
	$this->list->list = User::where([]);
}
```

---

# 8. Gestionar subidas de archivos

Usa `ORequest::getFile()` para obtener un archivo subido y valídalo antes de moverlo.

Consulta `recipes/uploads.md` para ejemplos completos.

---

# 9. Usar un layout personalizado

```php
ORoute::layout(
	MainLayoutComponent::class,
	static function (): void {
		ORoute::get(
			'/home',
			HomeComponent::class
		);
	}
);
```

Los Middlewares también pueden aplicarse a grupos de rutas creados con `layout()`, `prefix()` y `group()`.

---

# 10. Gestionar errores de validación

```php
if (!$dto->isValid()) {
	$this->status = 'error';
	$this->errors = $dto->getValidationErrors();

	return;
}
```

---

# Resumen

Patrones canónicos de 9.9:

- DTOs para entrada tipada y validada.
- Middlewares para lógica transversal de petición/respuesta.
- Contexto de Middleware para valores de confianza del servidor.
- Servicios para lógica de negocio.
- Componentes ligeros para orquestación.
- Componentes de modelo para representación.
- Layouts configurados desde routing.
