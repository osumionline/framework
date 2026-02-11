# Services

Services in **Osumi Framework** are reusable classes that encapsulate business logic, shared operations, or utility functions used across components, modules, and tasks.
They behave similarly to Angular services: lightweight, composable, and designed to keep components clean and focused.

Services help you:

- Avoid duplicating logic across components
- Organize domain‑specific behaviors
- Centralize interactions with models or external APIs
- Structure your application according to clean architecture principles

---

# 1. What is a Service?

A service is a PHP class that **extends `OService`**.
The base class provides:

- A logger (`OLog`)
- Access to the application configuration
- Access to the global cache container

The service class can then define any public methods needed by your application.

---

# 2. Creating a Service

A service should live inside:

    src/App/Service/

Typical structure:

```php
namespace Osumi\OsumiFramework\App\Service;

use Osumi\OsumiFramework\Core\OService;

class UserService extends OService {
  public function getUserById(int $id): ?User {
    return User::findOne(['id' => $id]);
  }
}
```

Services typically:

- Implement reusable logic
- Query or manipulate models
- Coordinate multi-step operations
- Optionally use other services

---

# 3. Injecting a Service into a Component

You cannot inject a service at the moment of declaring the property in a class.
**This is a PHP language limitation:** PHP does **not** allow calling functions (such as `inject()`) inside property declarations.

For example, this is **invalid** in PHP:

```php
private UserService $us = inject(UserService::class); // Not allowed in PHP
```

Because of this, services must be injected inside the constructor:

```php
class MyComponent extends OComponent {
  private ?UserService $us = null;

  public function __construct() {
    parent::__construct();
    $this->us = inject(UserService::class); // Correct
  }
}
```

This ensures the service is available by the time `run()` executes.

---

# 4. Using the Service in a Component

Once injected, you can access methods from the service normally:

```php
public function run(ORequest $req): void {
  $user = $this->us->getUserById(3);
  $this->user = $user;
}
```

A typical pattern is:

1.  Extract data from request (possibly using filters or DTOs)
2.  Delegate business logic to the service
3.  Prepare the final component output

Components stay small and declarative — services do the heavy lifting.

---

# 5. Lifecycle of a Service

`OService` handles some initialization automatically:

### Logger Initialization

Each service gets its own logger to write debug information.

### Access to Configuration

`$this->getConfig()` gives you global application configuration.

### Access to Cache Container

`$this->getCacheContainer()` gives you the global cache subsystem.

These helpers ensure services are both powerful and decoupled from global state.

---

# 6. Where to Place Services

Follow this structure:

    src/
      App/
        Service/
          CinemaService.php
          UserService.php
          NotificationService.php

Naming conventions:

- Class: `XxxService`
- File: `XxxService.php`

---

# 7. Example: Domain‑Focused Service

```php
class CinemaService extends OService {
  public function getCinemas(int $id_user): array {
    return Cinema::where(['id_user' => $id_user]);
  }

  public function deleteCinema(Cinema $cinema): void {
    $movies = $cinema->getMovies();
    foreach ($movies as $movie) {
      $movie->deleteFull();
    }
    $cinema->delete();
  }
}
```

This service:

- Retrieves data
- Performs multi‑step delete operations
- Encapsulates domain rules

By centralizing this logic, every component that needs cinema operations can reuse it.

---

# 8. Best Practices

- **Keep services stateless** whenever possible
  They should behave like pure helpers.

- **Group related functionality together**
  Avoid huge multipurpose service classes.

- **Use other services when needed**
  Creating service hierarchies is valid (e.g., `OrderService` using `PaymentService`).

- **Avoid rendering or output in services**
  Services should not echo or return HTML; that belongs to components.

- **Use the logger for debugging**
  `$this->getLog()->debug("...")` is extremely helpful.

- **Let components orchestrate**
  Components coordinate request → service → models → template.

---

# 9. When Should You Use a Service?

Use a service when:

- Multiple components share the same logic
- Logic involves non‑trivial model interactions
- You need reusable operations
- You want to separate domain logic from presentation logic
- You need to keep components small, clean, and focused

Do **not** use a service when:

- The logic is purely request‑filtering (use Filters)
- The logic is specific to rendering (use Components)
- The logic relates to validating input data (use DTOs)

---

# 10. Summary

Services are one of the key building blocks of Osumi Framework:

- They promote clean separation of concerns
- They reduce duplication
- They centralize business logic
- They integrate cleanly with components through dependency injection
- They support composition (services using services)

Using services effectively results in a more maintainable, scalable, and organized application architecture.
