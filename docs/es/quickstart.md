# Guía de inicio rápido

Esta guía le guiará en la creación de un nuevo proyecto de **Osumi Framework**, la instalación del plugin de token, la generación de acciones y filtros con la CLI, la creación de un modelo, la definición de rutas y la modificación del componente y la plantilla para crear un endpoint de API autenticado y funcional.

Al finalizar esta guía, tendrá:

- Una nueva aplicación de Osumi Framework
- El plugin de OToken instalado
- Un LoginFilter funcional
- Un modelo `User`
- Un endpoint `/api/get-users` autenticado que devuelve JSON

---

# 1. Crear un nuevo proyecto

Ejecute el siguiente comando para crear un nuevo proyecto de Osumi Framework:

```bash
composer create-project osumionline/new myapp
```

Esto generará una estructura de carpetas completa con ejemplos de componentes, rutas, modelos, etc.

---

# 2. Instalar el plugin de OToken

El plugin de OToken le permite generar y validar tokens tipo JWT.

Instálalo mediante Composer:

```bash
composer require osumionline/plugin-token
```

Tras la instalación, tu aplicación podrá usar:

```php
use Osumi\OsumiFramework\Plugins\OToken;
```

---

# 3. Eliminar datos de ejemplo

Cada proyecto nuevo incluye módulos, componentes, rutas y modelos de ejemplo.
Puedes borrarlos todos con:

```bash
php of reset
```

Esto conserva la estructura del framework, pero elimina toda la funcionalidad de ejemplo.

---

# 4. Crear una nueva acción (componente)

Usa la CLI para generar un nuevo componente de acción que servirá como punto final de la API.

```bash
php of add --option action --name api/getUsers --url /api/get-users --type json
```

Esto genera:

- `/src/App/Module/Api/GetUsers/GetUsersComponent.php`
- `/src/App/Module/Api/GetUsers/GetUsersTemplate.json`
- Una definición de ruta dentro de la carpeta de rutas (a menos que deshabilite la creación automática de rutas)

---

# 5. Crear un filtro de inicio de sesión

Ahora genere un filtro usando la CLI:

```bash
php of add --option filter --name login
```

Esto crea:

- `/src/App/Filter/LoginFilter.php`

Ahora debe reemplazar el archivo generado con su implementación real de LoginFilter:

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Filter;

use Osumi\OsumiFramework\Plugins\OToken;

class LoginFilter {
  /**
   * Filtro de seguridad para usuarios
   */
  public static function handle(array $params, array $headers): array {
    global $core;
    $ret = ['status'=>'error', 'id'=>null];

    $tk = new OToken($core->config->getExtra('secret'));
    if ($tk->checkToken($headers['Authorization'])) {
      $ret['status'] = 'ok';
      $ret['id'] = intval($tk->getParam('id'));
    }

    return $ret;
  }
}
```

Este filtro:

- Lee la cabecera `Authorization`
- Valida el token usando el secreto configurado
- Devuelve `"status" => "ok"` solo cuando el token es válido
- Inyecta `id` para que los componentes sepan qué usuario está autenticado

---

# 6. Crear el modelo `Usuario`

Los modelos se crean manualmente.
Dentro de `src/App/Model/User.php`, cree algo como:

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Model;

use Osumi\OsumiFramework\ORM\OModel;
use Osumi\OsumiFramework\ORM\OPK;
use Osumi\OsumiFramework\ORM\OField;
use Osumi\OsumiFramework\ORM\OCreatedAt;
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
    comment: "Correo electrónico del usuario",
    max: 100,
    nullable: false
  )]
  public ?string $email = null;

  #[OCreatedAt(
    comment: "Fecha de creación del registro"
  )]
  public ?string $created_at = null;

  #[OUpdatedAt(
    comment: "Fecha de la última actualización del registro"
  )]
  public ?string $updated_at = null;
}
```

Puede ajustar los campos según sus necesidades.

---

# 7. Crear la ruta de la API

Cree el archivo (si no se crea automáticamente):

    /src/Routes/Api.php

Agregue un prefijo para una agrupación limpia de los futuros endpoints y aplique LoginFilter para proteger la ruta de la API:

```php
<?php declare(strict_types=1);

use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\App\Filter\LoginFilter;
use Osumi\OsumiFramework\App\Module\Api\GetUsers\GetUsersComponent;

ORoute::prefix('/api', function() {
  ORoute::get('/get-users', GetUsersComponent::class, [LoginFilter::class]);
});
```

Ahora, cualquier llamada a `/api/get-users` debe incluir un token `Authorization` válido.

# 8. Crear un Componente de Modelo

Los componentes de modelo representan un modelo de usuario en formato JSON. Genere un Componente de Modelo para la clase Modelo de Usuario:

```bash
php of add --option modelComponent --name User
```

Al crear un Componente de Modelo, se generan dos componentes:

    /src/App/Component/Model/User/UserComponent.php
    /src/App/Component/Model/User/UserTemplate.php
    /src/App/Component/Model/UserList/UserListComponent.php
    /src/App/Component/Model/UserList/UserListTemplate.php

Con estos componentes, puede consultar la base de datos para un solo usuario o un conjunto de usuarios y mostrar sus datos fácilmente.

Edite el archivo `UserTemplate.php` para agregar o eliminar lo que necesite, por ejemplo, elimine las fechas de creación/actualización:

```php
<?php if (is_null($user)): ?>
null
<?php else: ?>
{
	"id": {{ user.id }},
	"name": {{ user.name | string }},
  "email": {{ email.name | string }}
}
<?php endif ?>

```

---

# 9. Modificar el componente generado

Abra:

    /src/App/Module/Api/GetUsers/GetUsersComponent.php

Modifiquelo para que:

1. Lea la salida de LoginFilter
2. Use el ID del usuario autenticado
3. Consulte la tabla de usuarios
4. Los pase a la plantilla JSON

Ejemplo:

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Api\GetUsers;

use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Web\ORequest;
use Osumi\OsumiFramework\App\Model\User;
use Osumi\OsumiFramework\App\Component\Model\UserList\UserListComponent;

class GetUsersComponent extends OComponent {
  public string $status = 'ok';
  public ?UserListComponent $list = null;

  public function run(ORequest $req): void {
    $filter = $req->getFilter('Login');
    $this->list = new UserListComponent();

    if (is_null($filter) || !array_key_exists('id', $filter)) {
      $this->status = 'error';
      $this->list->list = [];
      return;
    }

    // Ejemplo: obtener todos los usuarios (o filtrar por ID de usuario autenticado si es necesario)
    $this->list->list = User::where([]);
  }
}
```

---

# 9. Modificar la plantilla JSON

Abra:

    /src/App/Module/Api/GetUsers/GetUsersTemplate.json

Reemplace el contenido por:

```json
{
  "status": "{{ status }}",
  "users": [
    {{ list }}
  ]
}
```

La plantilla:

- Genera `"status"`
- Recorre los datos del usuario
- Utiliza subcomponentes para mostrar sus datos
- Crea un array JSON

---

# 10. Probar el endpoint

Para llamar a la API:

1. Genere un token válido (usando su propio endpoint de inicio de sesión o creando manualmente un OToken)
2. Envie una solicitud:

```bash
curl -X GET http://localhost:8000/api/get-users \
  -H "Authorization: TU_TOKEN_AQUÍ"
```

Si el token es válido, recibirás:

```json
{
	"status": "ok",
	"users": [
		{ "id": 1, "name": "Alice", "email": "alice@mail.com" },
		{ "id": 2, "name": "Bob", "email": "bob@mail.com" }
	]
}
```

Si el token no es válido o no está presente:

```json
{
	"status": "error",
	"users": []
}
```

---

# 11. Resumen

Esta guía de inicio rápido abordó:

- Creación de un nuevo proyecto Osumi
- Instalación de OToken
- Eliminación de datos de demostración
- Generación de una nueva acción de API
- Creación de un LoginFilter
- Escritura de un modelo de usuario
- Adición de una ruta autenticada
- Creación de un modelo Componente
- Modificación del componente y la plantilla

Ahora cuenta con una base sólida para crear API con compatibilidad con autenticación en Osumi Framework.
