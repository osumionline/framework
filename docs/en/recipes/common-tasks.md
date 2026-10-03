# Common Tasks

This document shows the canonical way to solve common tasks in **Osumi Framework 9.10**.

All examples assume:

- PHP 8.5+
- `declare(strict_types=1);`
- Strict typing wherever practical

---

# 1. Create a Simple JSON Endpoint

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

# 2. Receive Input Using a DTO

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

# 3. Protect an Endpoint with Authentication

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

Access one published value:

```php
$id_user = $req->getMiddlewareValue(
	'Login',
	'id'
);
```

Or bind it directly to a DTO field:

```php
#[ODTOField(
	required: true,
	middleware: 'Login',
	middlewareProperty: 'id'
)]
public ?int $idUser = null;
```

---

# 4. Read a URL Parameter

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

# 5. Use a Service Inside a Component

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

# 6. Save or Update a Model

```php
$user = new User();
$user->name = 'Alice';
$user->email = 'alice@mail.com';
$user->save();

$user = User::findOne([
	'id' => 1
]);

if ($user !== null) {
	$user->name = 'Updated Name';
	$user->save();
}
```

---

# 7. Return a List of Models

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

# 8. Handle File Uploads

Use `ORequest::getFile()` to obtain an uploaded file and validate it before moving it.

For complete examples, see `recipes/uploads.md`.

---

# 9. Use a Custom Layout

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

Middlewares can also be applied to `layout()`, `prefix()` and `group()` route groups.

---

# 10. Handle Validation Errors

```php
if (!$dto->isValid()) {
	$this->status = 'error';
	$this->errors = $dto->getValidationErrors();

	return;
}
```

---

# Summary

Canonical 9.10 patterns:

- DTOs for typed and validated input.
- Middlewares for cross-cutting request/response logic.
- Middleware context for trusted server-side values.
- Services for business logic.
- Thin components for orchestration.
- Model Components for representation.
- Layouts configured through routing.
