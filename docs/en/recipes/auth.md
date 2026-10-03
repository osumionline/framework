# Authentication — Recipes & Best Practices

Authentication in **Osumi Framework 9.10** is typically built with:

- A login endpoint that validates credentials and issues a token.
- A `before` Middleware that validates the token on protected routes.
- Middleware context for trusted authenticated values.
- DTOs for validated input and trusted Middleware-sourced fields.
- Services for reusable authentication and authorization logic.

---

# 1. Protecting Routes with Middleware

Example:

```php
ORoute::get(
	'/profile',
	ProfileComponent::class,
	[
		OMiddleware::PHASE_BEFORE => [
			LoginMiddleware::class
		]
	]
);
```

When the route is matched:

1. `before` Middlewares run.
2. `LoginMiddleware` validates the request.
3. If it returns `stop => true`, later `before` Middlewares and the component are skipped.
4. `afterResponse` still runs.
5. The framework emits the typed error response.

Authentication failures should normally use HTTP 401.

---

# 2. Login Middleware

A login Middleware should publish trusted context when validation succeeds.

```php
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Middleware;

use Osumi\OsumiFramework\Core\OMiddleware;

final class LoginMiddleware {
	/**
	 * Validate authentication and publish user context.
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

		// Validate Authorization token here.

		$is_valid = true;
		$id_user = 42;
		$role = 'admin';

		if (!$is_valid) {
			return [
				'stop' => true,
				'status_code' => 401,
				'message' => 'Unauthorized'
			];
		}

		return [
			'context' => [
				'id' => $id_user,
				'role' => $role
			]
		];
	}
}
```

`LoginMiddleware` is exposed publicly as `Login`.

---

# 3. Creating the Login Endpoint

A login endpoint typically:

1. Receives credentials through a DTO.
2. Validates them through a service.
3. Creates a token.
4. Returns the token to the client.
5. The client sends that token through `Authorization`.

Example DTO:

```php
class LoginDTO extends ODTO {
	#[ODTOField(required: true)]
	public ?string $email = null;

	#[ODTOField(required: true)]
	public ?string $password = null;
}
```

Authentication service:

```php
class AuthService extends OService {
	/**
	 * Validate credentials and return a token.
	 *
	 * @param string $email User email.
	 * @param string $password Plain-text password.
	 *
	 * @return array{token: string}|null Token data or null on failure.
	 */
	public function login(
		string $email,
		string $password
	): ?array {
		$user = User::findOne([
			'email' => $email
		]);

		if (
			$user === null ||
			!password_verify(
				$password,
				$user->password
			)
		) {
			return null;
		}

		$token = new OToken(
			$this->getConfig()->getExtra('secret')
		);

		$token->addParam(
			'id',
			$user->id
		);

		return [
			'token' => $token->getToken()
		];
	}
}
```

---

# 4. Reading Middleware Context in Components

Get all context:

```php
$login = $req->getMiddleware(
	'Login'
);
```

Get one value:

```php
$id_user = $req->getMiddlewareValue(
	'Login',
	'id'
);
```

Prefer `getMiddlewareValue()` when only one value is needed.

---

# 5. Using Middleware Context in DTOs

DTOs can use Middleware context as an explicit source:

```php
#[ODTOField(
	required: true,
	middleware: 'Login',
	middlewareProperty: 'id'
)]
public ?int $idUser = null;
```

This prevents the client from spoofing the authenticated user ID.

If the Middleware context value is missing, the DTO value remains `null`; it does not fall back to client input.

---

# 6. Protected Endpoint Using a DTO

Route:

```php
ORoute::get(
	'/my-cinemas',
	GetCinemasComponent::class,
	[
		OMiddleware::PHASE_BEFORE => [
			LoginMiddleware::class
		]
	]
);
```

DTO:

```php
class GetCinemasDTO extends ODTO {
	#[ODTOField(
		required: true,
		middleware: 'Login',
		middlewareProperty: 'id'
	)]
	public ?int $idUser = null;
}
```

Component:

```php
class GetCinemasComponent extends OComponent {
	private ?CinemaService $cinema_service = null;

	/**
	 * Prepare the component.
	 */
	public function __construct() {
		parent::__construct();

		$this->cinema_service = inject(
			CinemaService::class
		);
	}

	/**
	 * Load cinemas for the authenticated user.
	 *
	 * @param GetCinemasDTO $dto Authenticated request DTO.
	 *
	 * @return void
	 */
	public function run(GetCinemasDTO $dto): void {
		if (
			!$dto->isValid() ||
			$dto->idUser === null
		) {
			return;
		}

		$this->list = $this->cinema_service->getCinemas(
			$dto->idUser
		);
	}
}
```

---

# 7. Permissions

A Middleware may publish role or permission context:

```php
return [
	'context' => [
		'id' => 42,
		'role' => 'admin'
	]
];
```

A component can inspect it:

```php
$role = $req->getMiddlewareValue(
	'Login',
	'role'
);
```

For authorization rules shared by many routes, prefer a dedicated authorization Middleware instead of repeating checks in components.

---

# 8. Logout

For stateless token authentication, logout usually means removing the token client-side.

If server-side revocation is required, store revoked token identifiers in a service/cache and make the authentication Middleware reject them.

---

# 9. Best Practices

- Use `before` Middlewares for authentication.
- Return HTTP 401 for authentication failures.
- Publish only the trusted context required downstream.
- Never trust client-provided authenticated user IDs.
- Use DTO Middleware sources for authenticated values.
- Keep business logic in services.
- Use separate authorization Middleware when permissions are shared across routes.
- Keep token secrets in configuration.

---

# 10. Summary

A typical authentication flow contains:

1. Login endpoint.
2. Token issuing service.
3. `LoginMiddleware` on protected routes.
4. Trusted Middleware context.
5. DTOs and/or `ORequest` consuming that context.
6. Services containing business logic.

This is the canonical replacement, introduced in 9.9, for the legacy Filter-based authentication flow.
