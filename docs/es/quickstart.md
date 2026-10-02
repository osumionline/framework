# Guía de inicio rápido

Esta guía muestra cómo crear un proyecto nuevo de **Osumi Framework 9.9**, instalar el plugin de token, generar una acción y un Middleware mediante la CLI, crear un modelo, definir una ruta protegida y devolver JSON.

Al finalizar tendrás:

- Una nueva aplicación de Osumi Framework
- El plugin OToken instalado
- Un `LoginMiddleware` funcional
- Un modelo `User`
- Un endpoint `/api/get-users` autenticado que devuelve JSON

Todos los ejemplos asumen PHP 8.5+ y `declare(strict_types=1);`.

---

# 1. Crear un nuevo proyecto

```bash
composer create-project osumionline/new myapp
```

---

# 2. Instalar el plugin OToken

```bash
composer require osumionline/plugin-token
```

La aplicación podrá utilizar:

```php
use Osumi\OsumiFramework\Plugins\OToken;
```

---

# 3. Eliminar los datos de ejemplo

```bash
php of reset
```

Esto conserva la estructura estándar de la aplicación y elimina la funcionalidad de ejemplo.

---

# 4. Crear una nueva acción

```bash
php of add --option action --name api/getUsers --url /api/get-users --type json
```

Esto crea archivos bajo:

```text
src/Api/GetUsers/
```

incluyendo:

```text
src/Api/GetUsers/GetUsersComponent.php
src/Api/GetUsers/GetUsersTemplate.json
```

y, salvo que se deshabilite, la entrada de ruta correspondiente.

El namespace generado es:

```php
namespace Osumi\OsumiFramework\App\Api\GetUsers;
```

---

# 5. Crear un Login Middleware

Genera el Middleware:

```bash
php of add --option middleware --name Login
```

Esto crea:

```text
src/Middleware/LoginMiddleware.php
```

Sustituye su contenido por la lógica de autenticación de tu aplicación.

Ejemplo:

```php
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Middleware;

use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Plugins\OToken;

final class LoginMiddleware {
	/**
	 * Validate the Authorization token and publish the authenticated user ID.
	 *
	 * @param string $phase Current middleware phase.
	 * @param array<string, mixed> $data Current middleware pipeline data.
	 *
	 * @return array<string, mixed> Middleware result.
	 */
	public static function handle(
		string $phase,
		array $data
	): array {
		if ($phase !== OMiddleware::PHASE_BEFORE) {
			return [];
		}

		global $core;

		if (
			!($core instanceof OCore) ||
			$core->config === null
		) {
			return [
				'stop' => true,
				'status_code' => 500,
				'message' => 'Framework configuration is not available.'
			];
		}

		$headers = $data['headers'] ?? [];

		if (!is_array($headers)) {
			return [
				'stop' => true,
				'status_code' => 401,
				'message' => 'Unauthorized'
			];
		}

		$normalized_headers = array_change_key_case(
			$headers,
			CASE_LOWER
		);

		$authorization = $normalized_headers['authorization']
			?? null;

		if (
			!is_string($authorization) ||
			$authorization === ''
		) {
			return [
				'stop' => true,
				'status_code' => 401,
				'message' => 'Unauthorized'
			];
		}

		$secret = $core->config->getExtra(
			'secret'
		);

		if (
			!is_string($secret) ||
			$secret === ''
		) {
			return [
				'stop' => true,
				'status_code' => 500,
				'message' => 'Token secret is not configured.'
			];
		}

		$token = new OToken(
			$secret
		);

		if (!$token->checkToken($authorization)) {
			return [
				'stop' => true,
				'status_code' => 401,
				'message' => 'Unauthorized'
			];
		}

		return [
			'context' => [
				'id' => (int) $token->getParam('id')
			]
		];
	}
}
```

Este Middleware:

- Se ejecuta en la fase `before`.
- Lee la cabecera `Authorization`.
- Valida el token.
- Detiene la petición con HTTP 401 cuando falla la autenticación.
- Publica el ID del usuario autenticado como `Login.id`.

---

# 6. Crear el modelo `User`

Crea:

```text
src/Model/User.php
```

Ejemplo:

```php
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Model;

use Osumi\OsumiFramework\ORM\OCreatedAt;
use Osumi\OsumiFramework\ORM\OField;
use Osumi\OsumiFramework\ORM\OModel;
use Osumi\OsumiFramework\ORM\OPK;
use Osumi\OsumiFramework\ORM\OUpdatedAt;

class User extends OModel {
	#[OPK(
		comment: "ID único de un usuario"
	)]
	public ?int $id = null;

	#[OField(
		comment: "Nombre del usuario",
		max: 100,
		nullable: false
	)]
	public ?string $name = null;

	#[OField(
		comment: "Email del usuario",
		max: 100,
		nullable: false
	)]
	public ?string $email = null;

	#[OCreatedAt(
		comment: "Fecha de creación del registro"
	)]
	public ?string $created_at = null;

	#[OUpdatedAt(
		comment: "Fecha de actualización del registro"
	)]
	public ?string $updated_at = null;
}
```

---

# 7. Proteger la ruta de la API

Crea o edita:

```text
src/Routes/Api.php
```

Ejemplo:

```php
<?php

declare(strict_types=1);

use Osumi\OsumiFramework\App\Api\GetUsers\GetUsersComponent;
use Osumi\OsumiFramework\App\Middleware\LoginMiddleware;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Routing\ORoute;

ORoute::get(
	'/api/get-users',
	GetUsersComponent::class,
	[
		OMiddleware::PHASE_BEFORE => [
			LoginMiddleware::class
		]
	]
);
```

El componente solo se ejecuta cuando el Middleware finaliza sin `stop`.

---

# 8. Crear un componente de modelo

```bash
php of add --option modelComponent --name User
```

Esto genera:

```text
src/Component/Model/User/UserComponent.php
src/Component/Model/User/UserTemplate.php
src/Component/Model/UserList/UserListComponent.php
src/Component/Model/UserList/UserListTemplate.php
```

---

# 9. Modificar la acción generada

Abre:

```text
src/Api/GetUsers/GetUsersComponent.php
```

Ejemplo:

```php
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Api\GetUsers;

use Osumi\OsumiFramework\App\Component\Model\UserList\UserListComponent;
use Osumi\OsumiFramework\App\Model\User;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Web\ORequest;

class GetUsersComponent extends OComponent {
	public string $status = 'ok';
	public ?UserListComponent $list = null;

	/**
	 * Load users for an authenticated request.
	 *
	 * @param ORequest $req Current request.
	 *
	 * @return void
	 */
	public function run(ORequest $req): void {
		$id_user = $req->getMiddlewareValue(
			'Login',
			'id'
		);

		$this->list = new UserListComponent();

		if (!is_int($id_user)) {
			$this->status = 'error';
			$this->list->list = [];

			return;
		}

		$this->list->list = User::where([]);
	}
}
```

El valor publicado por `LoginMiddleware` está disponible mediante:

```php
$req->getMiddlewareValue(
	'Login',
	'id'
);
```

---

# 10. Modificar la plantilla JSON

Abre:

```text
src/Api/GetUsers/GetUsersTemplate.json
```

Ejemplo:

```json
{
	"status": {{ status | plain }},
	"users": [
		{{ list }}
	]
}
```

Utiliza el pipe `plain` cuando quieras conservar la cadena original sin codificación URL.

---

# 11. Probar el endpoint

```bash
curl -X GET http://localhost:8000/api/get-users \
	-H "Authorization: TU_TOKEN_AQUI"
```

Con un token válido, se ejecuta el componente protegido.

Si el token falta o no es válido, `LoginMiddleware` detiene la petición y el framework devuelve una respuesta de error JSON tipada con HTTP 401.

---

# 12. Resumen

Esta guía ha cubierto:

- Crear un proyecto.
- Instalar OToken.
- Eliminar los datos de ejemplo.
- Generar una acción API.
- Crear un Middleware nativo.
- Publicar contexto de autenticación.
- Proteger una ruta mediante `before`.
- Leer contexto de Middleware desde `ORequest`.
- Crear un modelo y un componente de modelo.
- Devolver JSON.

Esta es la base del flujo de autenticación de 9.9 mediante Middlewares en lugar de los antiguos Filters.
