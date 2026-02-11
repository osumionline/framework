# Quickstart Guide

This guide walks you through creating a fresh **Osumi Framework** project, installing the token plugin, generating actions and filters with the CLI, creating a model, defining routes, and modifying the component and template to build a working authenticated API endpoint.

By the end of this guide you will have:

- A new Osumi Framework application
- The OToken plugin installed
- A working LoginFilter
- A `User` model
- An authenticated `/api/get-users` endpoint returning JSON

---

# 1. Create a New Project

Run the following command to create a fresh Osumi Framework project:

```bash
composer create-project osumionline/new myapp
```

This will generate a complete folder structure with example components, routes, models, etc.

---

# 2. Install the OToken Plugin

The OToken plugin allows you to generate and validate JWT‑like tokens.

Install it via Composer:

```bash
composer require osumionline/plugin-token
```

After installation, your application can use:

```php
use Osumi\OsumiFramework\Plugins\OToken;
```

---

# 3. Remove Example Data

Every new project includes example modules, components, routes and models.
You can clean all of them with:

```bash
php of reset
```

This keeps the framework structure but removes all example functionality.

---

# 4. Create a New Action (Component)

Use the CLI to generate a new action component that will serve as an API endpoint.

```bash
php of add --option action --name api/getUsers --url /api/get-users --type json
```

This generates:

- `/src/App/Module/Api/GetUsers/GetUsersComponent.php`
- `/src/App/Module/Api/GetUsers/GetUsersTemplate.json`
- A route definition inside the routes folder (unless you disable auto‑route creation)

---

# 5. Create a Login Filter

Now generate a filter using the CLI:

```bash
php of add --option filter --name login
```

This creates:

- `/src/App/Filter/LoginFilter.php`

You must now replace the generated file with _your real LoginFilter_ implementation:

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Filter;

use Osumi\OsumiFramework\Plugins\OToken;

class LoginFilter {
  /**
   * Security filter for users
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

This filter:

- Reads the `Authorization` header
- Validates the token using the configured secret
- Returns `"status" => "ok"` only when the token is valid
- Injects `id` so components know which user is authenticated

---

# 6. Create the `User` Model

Models are created manually.
Inside `src/App/Model/User.php` create something like:

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
	comment: "Unique id for a user"
  )]
  public ?int $id = null;

  #[OField(
    comment: "User's name",
    max: 100,
    nullable: false
  )]
  public ?string $name = null;

  #[OField(
    comment: "User's email address",
    max: 100,
    nullable: false
  )]
  public ?string $email = null;

  #[OCreatedAt(
    comment: "Record creation date"
  )]
  public ?string $created_at = null;

  #[OUpdatedAt(
    comment: "Record's last update date"
  )]
  public ?string $updated_at = null;
}
```

You can adjust the fields based on your needs.

---

# 7. Create the API Route

Create the file (if not created automatically):

    /src/Routes/Api.php

Add a prefix for clean grouping of future endpoints and apply the LoginFilter to protect the API route:

```php
<?php declare(strict_types=1);

use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\App\Filter\LoginFilter;
use Osumi\OsumiFramework\App\Module\Api\GetUsers\GetUsersComponent;

ORoute::prefix('/api', function() {
  ORoute::get('/get-users', GetUsersComponent::class, [LoginFilter::class]);
});
```

Now any call to `/api/get-users` must include a valid `Authorization` token.

# 8. Create a Model Component

Model components are components that represent a user model in a JSON way. Generate a Model Component for the User Model class:

```bash
php of add --option modelComponent --name User
```

When a Model Component is created 2 components are generated:

    /src/App/Component/Model/User/UserComponent.php
    /src/App/Component/Model/User/UserTemplate.php
    /src/App/Component/Model/UserList/UserListComponent.php
    /src/App/Component/Model/UserList/UserListTemplate.php

Using this components you can query the database for a single user or a set of users and display their data easily.

Edit the `UserTemplate.php` file to add or remove whatever you nedd, for example remove the create/update dates:

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

# 9. Modify the Generated Component

Open:

    /src/App/Module/Api/GetUsers/GetUsersComponent.php

Modify it so it:

1.  Reads the LoginFilter output
2.  Uses the authenticated user ID
3.  Queries the users table
4.  Passes them to the JSON template

Example:

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

    // Example: get all users (or filter by authenticated user ID if needed)
    $this->list->list = User::where([]);
  }
}
```

---

# 9. Modify the JSON Template

Open:

    /src/App/Module/Api/GetUsers/GetUsersTemplate.json

Replace contents with:

```json
{
  "status": "{{ status }}",
  "users": [
    {{ list }}
  ]
}
```

The template:

- Outputs `"status"`
- Loops through user data
- Using sub-components displays their data
- Builds a JSON array

---

# 10. Testing the Endpoint

To call your API:

1.  Generate a valid token (using your own login endpoint or manual OToken creation)
2.  Send a request:

```bash
curl -X GET http://localhost:8000/api/get-users \
  -H "Authorization: YOUR_TOKEN_HERE"
```

If the token is valid, you will receive:

```json
{
	"status": "ok",
	"users": [
		{ "id": 1, "name": "Alice", "email": "alice@mail.com" },
		{ "id": 2, "name": "Bob", "email": "bob@mail.com" }
	]
}
```

If the token is invalid or missing:

```json
{
	"status": "error",
	"users": []
}
```

---

# 11. Summary

This quickstart covered:

- Creating a new Osumi project
- Installing OToken
- Removing demo data
- Generating a new API action
- Creating a LoginFilter
- Writing a User model
- Adding an authenticated route
- Creating a Model Component
- Modifying the component and template

You now have a working foundation for building APIs with authentication support in Osumi Framework.
