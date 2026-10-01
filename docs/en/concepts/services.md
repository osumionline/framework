# Services

Services in **Osumi Framework** are reusable classes that encapsulate business logic, shared operations or utility functions used across components, modules and tasks.

Services help you:

- Avoid duplicated logic.
- Organize domain behavior.
- Centralize model or external API interactions.
- Keep components focused on orchestration and presentation.

---

## 1. What Is a Service?

A service extends `OService`.

It can use framework facilities such as logging, configuration and cache access.

---

## 2. Creating a Service

Application services live under:

```text
src/Service/
```

Example:

```php
namespace Osumi\OsumiFramework\App\Service;

use Osumi\OsumiFramework\Core\OService;

class UserService extends OService {
	public function getUserById(int $id): ?User {
		return User::findOne([
			'id' => $id
		]);
	}
}
```

---

## 3. Injecting a Service into a Component

Services should be injected from executable code such as the constructor:

```php
class MyComponent extends OComponent {
	private ?UserService $us = null;

	public function __construct() {
		parent::__construct();

		$this->us = inject(
			UserService::class
		);
	}
}
```

---

## 4. Using a Service in a Component

A common flow is:

1. Read request or DTO data, possibly including trusted Middleware context.
2. Delegate business logic to a service.
3. Prepare component output.

```php
public function run(ORequest $req): void {
	$id = $req->getMiddlewareValue(
		'Login',
		'id'
	);

	if (!is_int($id)) {
		return;
	}

	$this->user = $this->us->getUserById(
		$id
	);
}
```

---

## 5. Service Lifecycle

`OService` provides access to framework facilities such as:

- logging
- application configuration
- cache

---

## 6. Naming and Location

Use:

```text
src/Service/UserService.php
```

with class names such as:

```text
UserService
OrderService
PaymentService
```

---

## 7. Best Practices

- Keep services stateless where practical.
- Group related functionality.
- Avoid rendering or direct output.
- Keep request/response cross-cutting concerns in Middlewares.
- Keep input validation in DTOs.
- Let components orchestrate request → service → model → template flows.

---

## 8. When to Use a Service

Use a service for reusable business or domain logic.

Do not use a service when:

- The logic is a cross-cutting request/response concern better implemented as a Middleware.
- The logic is specifically presentation/rendering logic.
- The logic is input validation better represented by a DTO.

---

## 9. Summary

Services provide reusable business logic and help keep components, Middlewares and DTOs focused on their respective responsibilities.
