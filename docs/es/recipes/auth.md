# Autenticación — Recetas y buenas prácticas

La autenticación en **Osumi Framework 9.9** se construye normalmente mediante:

- Un endpoint de login que valida credenciales y emite un token.
- Un Middleware `before` que valida el token en las rutas protegidas.
- Contexto de Middleware para valores autenticados de confianza.
- DTOs para entrada validada y campos procedentes de Middleware.
- Servicios para lógica reutilizable de autenticación y autorización.

---

# 1. Proteger rutas con Middleware

Ejemplo:

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

Cuando la ruta coincide:

1. Se ejecutan los Middlewares `before`.
2. `LoginMiddleware` valida la petición.
3. Si devuelve `stop => true`, se omiten los Middlewares `before` restantes y el componente.
4. `afterResponse` sigue ejecutándose.
5. El framework emite la respuesta de error tipada.

Los fallos de autenticación deberían utilizar normalmente HTTP 401.

---

# 2. Login Middleware

Un Middleware de login debe publicar contexto de confianza cuando la validación tiene éxito.

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

		// Validar aquí el token Authorization.

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

`LoginMiddleware` se expone públicamente como `Login`.

---

# 3. Crear el endpoint de login

Un endpoint de login normalmente:

1. Recibe las credenciales mediante un DTO.
2. Las valida mediante un servicio.
3. Crea un token.
4. Devuelve el token al cliente.
5. El cliente envía el token mediante `Authorization`.

DTO de ejemplo:

```php
class LoginDTO extends ODTO {
	#[ODTOField(required: true)]
	public ?string $email = null;

	#[ODTOField(required: true)]
	public ?string $password = null;
}
```

Servicio de autenticación:

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

# 4. Leer contexto de Middleware desde componentes

Obtener todo el contexto:

```php
$login = $req->getMiddleware(
	'Login'
);
```

Obtener un único valor:

```php
$id_user = $req->getMiddlewareValue(
	'Login',
	'id'
);
```

Es preferible usar `getMiddlewareValue()` cuando solo necesitas un valor.

---

# 5. Usar contexto de Middleware en DTOs

Los DTOs pueden usar contexto de Middleware como origen explícito:

```php
#[ODTOField(
	required: true,
	middleware: 'Login',
	middlewareProperty: 'id'
)]
public ?int $idUser = null;
```

Esto evita que el cliente pueda suplantar el ID del usuario autenticado.

Si el valor de contexto no existe, el valor del DTO permanece en `null`; no se recurre a los datos enviados por el cliente.

---

# 6. Endpoint protegido usando un DTO

Ruta:

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

Componente:

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

# 7. Permisos

Un Middleware puede publicar información de rol o permisos:

```php
return [
	'context' => [
		'id' => 42,
		'role' => 'admin'
	]
];
```

Un componente puede consultarla:

```php
$role = $req->getMiddlewareValue(
	'Login',
	'role'
);
```

Para reglas de autorización compartidas por muchas rutas, es preferible usar un Middleware específico de autorización en lugar de repetir las comprobaciones en los componentes.

---

# 8. Cerrar sesión

En una autenticación stateless basada en tokens, cerrar sesión normalmente consiste en eliminar el token en el cliente.

Si se necesita revocación en servidor, almacena identificadores de tokens revocados en un servicio/caché y haz que el Middleware de autenticación los rechace.

---

# 9. Buenas prácticas

- Usa Middlewares `before` para autenticación.
- Devuelve HTTP 401 cuando falle la autenticación.
- Publica únicamente el contexto de confianza necesario.
- Nunca confíes en IDs de usuario autenticado enviados por el cliente.
- Usa fuentes de Middleware en DTOs para valores autenticados.
- Mantén la lógica de negocio en servicios.
- Usa un Middleware de autorización separado cuando los permisos se compartan entre rutas.
- Mantén los secretos de token en configuración.

---

# 10. Resumen

Un flujo de autenticación habitual contiene:

1. Endpoint de login.
2. Servicio que emite tokens.
3. `LoginMiddleware` en rutas protegidas.
4. Contexto de Middleware de confianza.
5. DTOs y/o `ORequest` que consumen ese contexto.
6. Servicios que contienen la lógica de negocio.

Este es el reemplazo canónico en 9.9 del antiguo flujo de autenticación basado en Filters.
