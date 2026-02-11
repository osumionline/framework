# Authentication (Auth) — Recipes & Best Practices

Authentication in **Osumi Framework** is typically implemented using:

- **A login endpoint** that validates user credentials and issues a **token**
- **A filter** (e.g., `LoginFilter`) that validates the token on every protected route
- **DTOs** to safely process incoming authentication data
- **Services** to keep authentication logic reusable and clean
- **Routes** configured to apply authentication filters before running components

This document provides practical recipes for implementing a complete authentication workflow.

---

# 1. Protecting Routes Using Filters

The most common method of securing endpoints is adding a filter to the route definition.

According to your routing system, filters can be specified like this:

```php
ORoute::post('/profile', ProfileComponent::class, [LoginFilter::class]);
```

When the route is accessed:

1.  The router identifies the endpoint
2.  Before running the component, the filter chain is executed
3.  If any filter returns `"status" !== "ok"`, the request **never reaches the component**, returning **403 Forbidden** or redirecting if `"return"` is set

This ensures only authenticated users reach protected logic.

---

# 2. Creating the Login Filter

A filter looks like this:

```php
class LoginFilter {
  public static function handle(array $params, array $headers): array {
    global $core;
    $ret = ['status' => 'error', 'id' => null];

    $tk = new OToken($core->config->getExtra('secret'));

    if ($tk->checkToken($headers['Authorization'])) {
        $ret['status'] = 'ok';
        $ret['id'] = intval($tk->getParam('id'));
    }

    return $ret;
  }
}
```

This filter:

- Reads the `Authorization` header
- Validates a token
- If valid → returns `"status" => "ok"` and the authenticated user ID
- If invalid → returns `"error"` and stops the request

Token‑derived values (like `id`) can later be consumed by components or DTOs.

---

# 3. Creating the Login Endpoint (Issuing Tokens):

1.  Receives credentials via a DTO
2.  Validates them using a service
3.  Generates a token
4.  Returns the token to the client
5.  Client stores token and uses it in the `Authorization` header

### Example Structure

**DTO for login:**

```php
class LoginDTO extends ODTO {
  #[ODTOField(required: true)]
  public ?string $email = null;

  #[ODTOField(required: true)]
  public ?string $password = null;
}
```

**AuthService handling the logic:**

```php
class AuthService extends OService {
  public function login(string $email, string $password): ?array {
    $user = User::findOne(['email' => $email]);
    if (!$user || !password_verify($password, $user->password)) {
      return null;
    }
    $token = new OToken($this->getConfig()->getExtra('secret'));
    $token->addParam('id', $user->id);
    return ['token' => $token->getToken()];
  }
}
```

**LoginComponent:**

```php
class LoginComponent extends OComponent {
  private ?AuthService $auth = null;
  public ?string $token = null;
  public string $status = 'error';

  public function __construct() {
    parent::__construct();
    $this->auth = inject(AuthService::class);
  }

  public function run(LoginDTO $dto): void {
    if (!$dto->isValid()) {
      return;
    }

    $data = $this->auth->login($dto->email, $dto->password);

    if ($data) {
      $this->status = 'ok';
      $this->token = $data['token'];
    }
  }
}
```

The client now includes the token in all subsequent requests:

    Authorization: <token>

---

# 4. Using Filter Output in Components

Once filters pass, the request object contains filter results:

```php
$filter = $req->getFilter('Login');
```

Typically you’d do:

```php
$userId = $filter['id']; // authenticated user
```

You can then pass the ID to services, load models, and perform business logic securely.

---

# 5. Using Filter Data Inside DTOs

DTOs can automatically receive values from filters:

```php
#[ODTOField(filter: 'Login', filterProperty: 'id')]
public ?int $idUser = null;
```

This means:

- Users cannot spoof their identity
- DTOs receive the authenticated user ID securely
- Components do not need to read filter data manually

This greatly simplifies authentication‑dependent endpoints.

---

# 6. Recipe: Creating a Protected Endpoint

Example: “Get My Cinemas”

### Route

```php
ORoute::get('/my-cinemas', GetCinemasComponent::class, [LoginFilter::class]);
```

### Component

```php
class GetCinemasComponent extends OComponent {
  private ?CinemaService $cs = null;
  public string $status = 'ok';
  public ?CinemaListComponent $list = null;

  public function __construct() {
    parent::__construct();
    $this->cs = inject(CinemaService::class);
    $this->list = new CinemaListComponent();
  }

  public function run(ORequest $req): void {
    $filter = $req->getFilter('Login');

    if (!$filter || !array_key_exists('id', $filter)) {
      $this->status = 'error';
      return;
    }

    $this->list->list = $this->cs->getCinemas($filter['id']);
  }
}
```

---

# 7. Recipe: Enforcing Permissions

You can extend your filter to include role/permission information from the token:

```php
$ret['role'] = $tk->getParam('role');
```

Then in components:

```php
$filter = $req->getFilter('Login');
if ($filter['role'] !== 'admin') {
  $this->status = 'forbidden';
  return;
}
```

---

# 8. Recipe: Logging Out

Since your auth system is token‑based and stateless:

- "Logout" is simply deleting the token client‑side
- Optionally, you may implement a **token blacklist** using cache:
    - Mark token as invalid in `getCacheContainer()`
    - Filter checks for blacklisted tokens

---

# 9. Best Practices

- **Use DTOs** for login requests
- **Never trust client‑provided user IDs** — always derive IDs from filters
- **Keep filters small** (validation only)
- **Put business logic in services**
- **Use strong secrets** for tokens (store in config)
- **Divide logic cleanly**:
    - Filters → authentication / verification
    - DTOs → input validation
    - Services → logic
    - Components → orchestration and response

---

# 10. Summary

A full authentication workflow in Osumi Framework usually includes:

1.  **Login endpoint** issuing tokens
2.  **LoginFilter** validating tokens for protected routes
3.  **DTOs** capturing and validating input
4.  **Services** performing authentication logic
5.  **Routing** applying filters before components
6.  **Secure propagation** of authenticated user information via filters and DTOs

This architecture ensures:

- Clean separation of responsibilities
- Easy reuse across endpoints
- Strong security guarantees
- Simple and predictable request pipeline
