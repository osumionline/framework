# Quickstart Guide

This guide walks through creating a fresh **Osumi Framework 9.9** project, installing the token plugin, generating an action and a Middleware with the CLI, creating a model, defining a protected route and returning JSON.

By the end you will have:

- A new Osumi Framework application
- The OToken plugin installed
- A working `LoginMiddleware`
- A `User` model
- An authenticated `/api/get-users` endpoint returning JSON

All examples assume PHP 8.5+ and `declare(strict_types=1);`.

---

# 1. Create a New Project

```bash
composer create-project osumionline/new myapp
```

---

# 2. Install the OToken Plugin

```bash
composer require osumionline/plugin-token
```

The application can then use:

```php
use Osumi\OsumiFramework\Plugins\OToken;
```

---

# 3. Remove Example Data

```bash
php of reset
```

This keeps the standard application structure while removing example functionality.

---

# 4. Create a New Action

```bash
php of add --option action --name api/getUsers --url /api/get-users --type json
```

This creates files under:

```text
src/Api/GetUsers/
```

including:

```text
src/Api/GetUsers/GetUsersComponent.php
src/Api/GetUsers/GetUsersTemplate.json
```

and, unless disabled, the corresponding route entry.

The generated namespace is:

```php
namespace Osumi\OsumiFramework\App\Api\GetUsers;
```

---

# 5. Create a Login Middleware

Generate the Middleware:

```bash
php of add --option middleware --name Login
```

This creates:

```text
src/Middleware/LoginMiddleware.php
```

Replace its contents with your authentication logic.

Example:

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

This Middleware:

- Runs in the `before` phase.
- Reads the `Authorization` header.
- Validates the token.
- Stops the request with HTTP 401 when authentication fails.
- Publishes the authenticated user ID as `Login.id`.

---

# 6. Create the `User` Model

Create:

```text
src/Model/User.php
```

Example:

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
		comment: "Unique ID for a user"
	)]
	public ?int $id = null;

	#[OField(
		comment: "User name",
		max: 100,
		nullable: false
	)]
	public ?string $name = null;

	#[OField(
		comment: "User email",
		max: 100,
		nullable: false
	)]
	public ?string $email = null;

	#[OCreatedAt(
		comment: "Record creation date"
	)]
	public ?string $created_at = null;

	#[OUpdatedAt(
		comment: "Record update date"
	)]
	public ?string $updated_at = null;
}
```

---

# 7. Protect the API Route

Create or edit:

```text
src/Routes/Api.php
```

Example:

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

The component is only executed when the Middleware completes without `stop`.

---

# 8. Create a Model Component

```bash
php of add --option modelComponent --name User
```

This generates:

```text
src/Component/Model/User/UserComponent.php
src/Component/Model/User/UserTemplate.php
src/Component/Model/UserList/UserListComponent.php
src/Component/Model/UserList/UserListTemplate.php
```

---

# 9. Modify the Generated Action

Open:

```text
src/Api/GetUsers/GetUsersComponent.php
```

Example:

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

The value published by `LoginMiddleware` is available through:

```php
$req->getMiddlewareValue(
	'Login',
	'id'
);
```

---

# 10. Modify the JSON Template

Open:

```text
src/Api/GetUsers/GetUsersTemplate.json
```

Example:

```json
{
	"status": {{ status | plain }},
	"users": [
		{{ list }}
	]
}
```

Use the `plain` pipe when the original string should be preserved without URL encoding.

---

# 11. Test the Endpoint

```bash
curl -X GET http://localhost:8000/api/get-users \
	-H "Authorization: YOUR_TOKEN_HERE"
```

With a valid token, the protected component runs.

With a missing or invalid token, `LoginMiddleware` stops the request and the framework returns a typed JSON error response with HTTP 401.

---

# 12. Summary

This quickstart covered:

- Creating a project
- Installing OToken
- Resetting example data
- Generating an API action
- Creating a native Middleware
- Publishing authenticated context
- Protecting a route with `before`
- Reading Middleware context through `ORequest`
- Creating a model and Model Component
- Returning JSON

You now have the basic 9.9 authentication flow using Middlewares instead of legacy Filters.
