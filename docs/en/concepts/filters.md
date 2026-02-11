# Filters

Filters in **Osumi Framework** are small, reusable classes executed **before** a component runs.
They are commonly used for:

- Authentication and authorization
- API key / token validation
- Request pre‑processing
- Permission checks
- Pre‑loading contextual data (user, tenant, locale...)

Filters allow you to centralize logic that should run _before every request to certain routes_, keeping components clean and focused.

---

# 1. What is a Filter?

A filter is a PHP class—typically placed in `src/App/Filter/`—that implements a static method:

```php
public static function handle(array $params, array $headers): array
```

It must return an **associative array** with at least:

```php
[
  'status' => 'ok' | 'error',
  // other values...
]
```

The filter checks an `Authorization` header, validates a token, and returns either an `"ok"` status or `"error"` plus an optional user id.

---

# 2. Example Filter

Here is an actual `LoginFilter`:

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

This example demonstrates:

- Reading headers
- Validating a token
- Returning contextual data (`id`) that will later be used by DTOs or components

---

# 3. How Filters Are Executed (Framework Flow)

Filters are executed by **OCore** as part of the request lifecycle, _before the component is instantiated_.
The process can be summarized:

1.  The routing system identifies the matched route and its list of filters.
2.  OCore creates `$url_result` from the request.
3.  For each filter (in order):
    - Instantiate filter class
    - Call its `handle($params, $headers)`
    - Validate the returned `"status"`
    - Store result if status `"ok"`
    - Stop and return an error if `"error"`

Exact logic in OCore:

```php
foreach ($url_result['filters'] as $filter) {
  $filter_instance = new $filter();
  $value = $filter_instance->handle(
    $url_result['params'],
    $url_result['headers']
  );

  if ($value['status'] !== 'ok') {
    // Handle error or redirection
    ...
    break;
  }

  $filter_results[$class_name] = $value;
}
```

---

# 4. What Happens When a Filter Fails?

If any filter returns `"status" !== "ok"`:

### The request is stopped immediately

OCore stops processing the rest of the filters and prevents the component from running.

### If `"return"` is included

The framework redirects to the specified URL.

### Otherwise, framework returns **403 Forbidden**

OCore sets HTTP 403 and displays an error page.

This ensures that unauthorized requests never reach your business logic.

---

# 5. Accessing Filter Results Inside Your App

After all filters succeed, OCore creates an `ORequest` object:

```php
$req = new ORequest($url_result, $filter_results);
```

This makes all filter results available through:

```php
$req->getFilter('Login');
```

For example:

```php
$login = $req->getFilter('Login');

if ($login['status'] === 'ok') {
  $userId = $login['id'];
}
```

Filter names are normalized by removing the `"Filter"` suffix from the class name, exactly as OCore does using reflection.

---

# 6. Using Filter Data from a DTO

DTO fields can automatically map values from filters using:

```php
#[ODTOField(filter: 'Login', filterProperty: 'id')]
public ?int $idUser = null;
```

This means:

- The client cannot spoof or override this value.
- The DTO gets the user id injected securely.
- The component doesn’t need to manually access the filter.

This works because ODTO reads filter data via `$req->getFilter()` when populating fields.

---

# 7. Defining Filters in Routes

In a routes file:

```php
ORoute::post(
  '/profile',
  ProfileComponent::class,
  [LoginFilter::class]
);
```

When this endpoint is called:

1.  The router detects `/profile`.
2.  Before running the component, OCore runs `LoginFilter`.
3.  If the filter fails → request ends.
4.  If it passes → the component executes normally.

OCore’s filter processing confirms this exact flow.

---

# 8. Filter Return Format

A filter must always return an array like:

```php
[
  'status' => 'ok' | 'error',
  'return' => '/login', // optional redirect
  // custom properties...
]
```

For example:

```php
[
  'status' => 'ok',
  'id'     => 123,
  'role'   => 'admin'
]
```

---

# 9. Best Practices

### Keep filters stateless

They should not depend on global mutable state (except reading config or session).

### Always return `"status" => "ok"` or `"error"`

OCore depends on this field to decide whether to continue the request.

### Use filters for authentication / authorization

DTOs should not receive credentials directly from users when they can be injected securely.

### Name filters with `XxxFilter`

This ensures OCore’s class name normalization behaves predictably.

### Avoid heavy logic

Keep filters small; move business logic to services.

### Use `"return"` for redirecting users

Useful for login guards or onboarding flows.

---

# 10. When Should You Use a Filter?

Use a filter when:

- A route requires authentication.
- A route requires validating an API key or token.
- You want to block or redirect unauthorized users _before_ executing a component.
- You want to pre-load user, tenant, or session info.
- Multiple routes share the same security checks.

Do _not_ use filters for:

- Model validation
- Formatting responses
- Building complex data structures
  (these should live in DTOs or services)

---

# 11. Full Request Flow Including Filters

    Client Request
          ↓
    Routing → route found
          ↓
    Filters execute (in order)
          ↓
    IF any filter fails → 403 or redirect
          ↓
    ORequest is created (with filter data)
          ↓
    Component::run($dtoOrRequest)
          ↓
    Template rendering
          ↓
    Response
