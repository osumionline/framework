# Common Tasks

This document describes **common, real-world tasks** in Osumi Framework and the **recommended (canonical) way** to solve them.

If multiple approaches are possible, only the idiomatic Osumi Framework solution is shown.

All examples assume:

- PHP 8.3+
- `declare(strict_types=1);`
- Proper namespaces

---

# 1. Create a Simple JSON Endpoint

## Goal

Return JSON from `/api/ping`.

### Route

```php
use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\App\Module\Api\Ping\PingComponent;

ORoute::get('/api/ping', PingComponent::class);
```

### Component

```php
class PingComponent extends OComponent {
  public string $status = 'ok';
}
```

### Template (`PingTemplate.json`)

```json
{
	"status": "{{ status }}"
}
```

---

# 2. Receive Input Using a DTO

## Goal

Create a user using validated input.

### DTO

```php
class CreateUserDTO extends ODTO {
  #[ODTOField(required: true)]
  public ?string $name = null;

  #[ODTOField(required: true)]
  public ?string $email = null;
}
```

### Component

```php
class CreateUserComponent extends OComponent {
  public string $status = 'ok';

  public function run(CreateUserDTO $dto): void {
    if (!$dto->isValid()) {
      $this->status = 'error';
      return;
    }

    $u = new User();
    $u->name = $dto->name;
    $u->email = $dto->email;
    $u->save();
  }
}
```

---

# 3. Protect an Endpoint with Authentication

## Goal

Only authenticated users may access `/api/profile`.

### Route

```php
ORoute::get('/api/profile', ProfileComponent::class, [LoginFilter::class]);
```

### Access Filter Data

```php
public function run(ORequest $req): void {
  $login = $req->getFilter('Login');
  $user_id = $login['id'];
}
```

---

# 4. Read a URL Parameter

## Goal

Access `/user/:id`.

### Route

```php
ORoute::get('/user/:id', UserComponent::class);
```

### Component

```php
public function run(ORequest $req): void {
  $id = $req->getParamInt('id');
  $this->user = User::findOne(['id' => $id]);
}
```

---

# 5. Use a Service Inside a Component

## Goal

Move business logic out of the component.

### Service

```php
class UserService extends OService {
  public function getAll(): array {
    return User::where([]);
  }
}
```

### Component

```php
class UsersComponent extends OComponent {
  private ?UserService $us = null;
  public array $users = [];

  public function __construct() {
    parent::__construct();
    $this->us = inject(UserService::class);
  }

  public function run(): void {
    $this->users = $this->us->getAll();
  }
}
```

---

# 6. Save or Update a Model

## Goal

Insert or update automatically using `save()`.

```php
$user = new User();
$user->name = 'Alice';
$user->email = 'alice@mail.com';
$user->save(); // INSERT

$user = User::findOne(['id' => 1]);
$user->name = 'Updated Name';
$user->save(); // UPDATE
```

---

# 7. Return a List of Models (JSON)

## Goal

Return users using a Model Component.

### Inside Component

```php
public ?UserListComponent $list = null;

public function run(): void {
  $this->list = new UserListComponent();
  $this->list->list = User::where([]);
}
```

### Template

```json
{
  "users": [
    {{ list }}
  ]
}
```

---

# 8. Handle File Upload

## Goal

Upload a file securely.

### DTO

```php
class UploadDTO extends ODTO {
  #[ODTOField(required: true)]
  public ?array $file = null;

  public function __construct(ORequest $req) {
    parent::__construct($req);
    $this->file = $req->getFile('file');
  }
}
```

### Component

```php
public function run(UploadDTO $dto): void {
  if (!$dto->isValid()) return;

  $file = $dto->file;
  $dest = $this->getConfig()->getDir('uploads') . basename($file['name']);
  move_uploaded_file($file['tmp_name'], $dest);
}
```

---

# 9. Use a Custom Layout

## Goal

Apply a layout to a group of routes.

```php
ORoute::layout(MainLayoutComponent::class, function() {
  ORoute::get('/home', HomeComponent::class);
});
```

Or combine prefix + layout:

```php
ORoute::group('/admin', AdminLayoutComponent::class, function() {
  ORoute::get('/dashboard', DashboardComponent::class);
});
```

Layouts wrap the rendered component output and receive:

- `title`
- `body`

---

# 10. Handle Validation Errors Properly

## DTO Validation

```php
if (!$dto->isValid()) {
  $this->status = 'error';
  $this->errors = $dto->getValidationErrors();
  return;
}
```

## Model Not Found

```php
$user = User::findOne(['id' => $id]);
if (is_null($user)) {
  $this->status = 'error';
  return;
}
```

---

# Summary

These recipes show the **canonical way** to perform common tasks in Osumi Framework:

- Use DTOs for input validation
- Use Filters for authentication
- Use Services for business logic
- Keep Components thin
- Use Model Components for JSON representation
- Apply Layouts via routing

Follow these patterns for consistent, predictable applications.
