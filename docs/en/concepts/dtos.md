# Data Transfer Objects (DTOs)

DTOs (Data Transfer Objects) in **Osumi Framework** are simple classes used to receive, normalize and validate input data coming from an HTTP request.
They provide a structured and safe way for components to access typed and validated request values, headers or filter outputs.

A DTO must **extend `ODTO`** and define its fields using the **`#[ODTOField]`** attribute.

---

# 1. Purpose of a DTO

DTOs are designed to:

- Collect and type‑cast request data (URL params, JSON body, form fields, query strings, headers, filters).
- Apply validation rules _before_ component logic executes.
- Make parameter handling consistent across the entire framework.
- Avoid manual calls to `$req->getParam...()` inside components.
- Prevent unsafe or unexpected values from reaching the business logic.

When a component defines:

```php
public function run(MovieDTO $dto): void
```

...the framework automatically:

1.  Instantiates `MovieDTO`.
2.  Loads request data into it.
3.  Applies validation rules defined in its attributes.
4.  Injects the DTO into the component’s `run()` method.

---

# 2. Base Class: `ODTO`

The `ODTO` class uses **reflection** to inspect all public properties in the DTO, read their `#[ODTOField]` definitions, and load data accordingly.

### 2.1 Data loading process

ODTO loads values in this order of priority:

1.  **Filter result**
    If a field defines `filter` and `filterProperty`, the value is taken from:

    ```php
    $req->getFilter($filterName)[$filterProperty]
    ```

2.  **Header value**
    If a field defines `header: 'X-Header'`, the value is taken from:

    ```php
    $req->getHeader('X-Header')
    ```

3.  **Request parameters**
    The value is type‑cast depending on the property type:
    - `int` → `$req->getParamInt()`
    - `float` → `$req->getParamFloat()`
    - `bool` → `$req->getParamBool()`
    - `string` → `$req->getParamString()`
    - `array` → `$req->getParam()`
    - default → `null`

### 2.2 Validation

After loading all values, ODTO automatically checks:

- **required**
  If `required = true` and the value is missing, a validation error is added.

- **requiredIf**
  If another field has a value, and this field does not, a validation error is added.

Errors are stored internally and can be retrieved via:

```php
$dto->getValidationErrors();
```

You can check if the DTO is valid with:

```php
$dto->isValid();
```

---

# 3. `ODTOField` Attribute

The `ODTOField` attribute is used to configure each DTO property:

```php
#[ODTOField(
  required: false,
  requiredIf: null,
  filter: null,
  filterProperty: null,
  header: null
)]
```

### Attribute options

| Attribute          | Description                                                         |
| ------------------ | ------------------------------------------------------------------- |
| **required**       | The field must have a value.                                        |
| **requiredIf**     | The field is required only if another field has a value.            |
| **filter**         | Name of a filter whose output should be used to populate the field. |
| **filterProperty** | Key of the filter output array to use.                              |
| **header**         | Name of an HTTP header to read the value from.                      |

These options allow powerful combinations, such as obtaining user IDs from filters, extracting API tokens from headers, or enforcing conditional dependencies between DTO fields.

---

# 4. Example DTO

Example from your project:

```php
class MovieDTO extends ODTO {
  #[ODTOField(required: true)]
  public ?int $idCinema = null;

  #[ODTOField(required: true)]
  public ?string $name = null;

  #[ODTOField(required: true)]
  public ?string $cover = null;

  #[ODTOField(required: true)]
  public ?int $coverStatus = null;

  #[ODTOField(required: true)]
  public ?string $ticket = null;

  #[ODTOField(required: true)]
  public ?string $imdbUrl = null;

  #[ODTOField(required: true)]
  public ?string $date = null;

  #[ODTOField(required: true)]
  public ?array $companions = null;

  #[ODTOField(required: true, filter: 'Login', filterProperty: 'id')]
  public ?int $idUser = null;
}
```

This DTO:

- Loads typed values from request parameters (`int`, `string`, `array`).
- Enforces required fields.
- Loads `idUser` from the **Login filter** instead of the client request.

---

# 5. Using a DTO inside a Component

```php
class AddMovieComponent extends OComponent {
  public function run(MovieDTO $dto): void {
    if (!$dto->isValid()) {
      $this->errors = $dto->getValidationErrors();
      return;
    }

    $movie = new Movie();
    $movie->name = $dto->name;
    $movie->date = $dto->date;
    $movie->idUser = $dto->idUser;
    $movie->save();
  }
}
```

### Notes:

- The component does **not** need to read request values manually.
- Values passed to the component are already typed and validated.
- Error handling becomes straightforward.

---

# 6. Best Practices

- Use `?type = null` for all DTO properties to prevent uninitialized typed property errors.
- Prefer DTOs whenever you receive structured data (API endpoints, forms, JSON).
- Use `requiredIf` to express logical dependencies between fields.
- Use `filter` fields to avoid exposing sensitive IDs to the client.
- Keep DTOs simple; they should not contain business logic.
- Always check `$dto->isValid()` before using it.

---

# 7. When to use DTOs

DTOs are ideal for:

- API endpoints receiving complex inputs.
- Form submissions with many fields.
- Endpoints requiring authentication data injected by filters.
- Reusable data structures used across multiple components.
- Replacing repeated request‑parsing logic.
