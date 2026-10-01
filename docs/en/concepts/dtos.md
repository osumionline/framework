# Data Transfer Objects (DTOs)

DTOs in **Osumi Framework** are simple classes used to receive, normalize and validate input data coming from an HTTP request.

They provide a structured and safe way for components to access typed and validated request values, headers or Middleware context.

A DTO must extend `ODTO` and define its fields using `#[ODTOField]`.

---

## 1. Purpose of a DTO

DTOs are designed to:

- Collect and type-cast request data.
- Read trusted values from Middleware context.
- Read values from HTTP headers.
- Apply validation rules before component logic executes.
- Keep request parsing consistent across the framework.
- Avoid repeated `$req->getParam...()` calls inside components.

When a component defines:

```php
public function run(MovieDTO $dto): void
```

the framework automatically:

1. Instantiates `MovieDTO`.
2. Loads its values.
3. Applies its validation rules.
4. Injects it into `run()`.

---

## 2. Base Class: `ODTO`

`ODTO` uses reflection to inspect public DTO properties and their `#[ODTOField]` definitions.

### 2.1 Data Sources

A field can use one explicit source.

#### Middleware Context

If both `middleware` and `middlewareProperty` are defined, the value is obtained from:

```php
$req->getMiddlewareValue(
	$middlewareName,
	$middlewareProperty
);
```

Middleware context is an explicit source. If the context value does not exist, the DTO value remains `null`; it does not fall back to client input.

#### Header

If `header` is defined, the value is obtained from the corresponding HTTP header.

A field cannot define both a Middleware source and a header source.

#### Request Parameters

When no explicit source is defined, the value is loaded from request parameters according to the property type:

- `int` → `getParamInt()`
- `float` → `getParamFloat()`
- `bool` → `getParamBool()`
- `string` → `getParamString()`
- `array` → `getParam()`

### 2.2 Validation

After loading values, ODTO evaluates:

- `required`
- `requiredIf`

Validation errors can be read with:

```php
$dto->getValidationErrors();
```

Validity can be checked with:

```php
$dto->isValid();
```

---

## 3. `ODTOField`

```php
#[ODTOField(
	required: false,
	requiredIf: null,
	middleware: null,
	middlewareProperty: null,
	header: null
)]
```

| Attribute | Description |
| --------- | ----------- |
| `required` | The field must contain a value. |
| `requiredIf` | The field is required when another DTO field contains a value. |
| `middleware` | Public Middleware name used as the field source. |
| `middlewareProperty` | Context property read from that Middleware. |
| `header` | HTTP header used as the field source. |

Rules:

- `middleware` and `middlewareProperty` must be defined together.
- Empty Middleware names or properties are invalid.
- A field cannot use both Middleware context and a header as explicit sources.
- `requiredIf` must reference another DTO field and cannot reference itself.

---

## 4. Example DTO

```php
class MovieDTO extends ODTO {
	#[ODTOField(required: true)]
	public ?int $idCinema = null;

	#[ODTOField(required: true)]
	public ?string $name = null;

	#[ODTOField(
		required: true,
		middleware: 'Login',
		middlewareProperty: 'id'
	)]
	public ?int $idUser = null;
}
```

Here `idUser` is read from context published by `LoginMiddleware`, not from client input.

---

## 5. Using a DTO in a Component

```php
class AddMovieComponent extends OComponent {
	public function run(MovieDTO $dto): void {
		if (!$dto->isValid()) {
			$this->errors = $dto->getValidationErrors();
			return;
		}

		$movie = new Movie();
		$movie->name = $dto->name;
		$movie->idUser = $dto->idUser;
		$movie->save();
	}
}
```

---

## 6. Best Practices

- Use strict property types.
- Prefer nullable defaults when a field can initially be absent.
- Use DTOs for structured request input.
- Use `requiredIf` for field dependencies.
- Use Middleware context for trusted server-side values such as authenticated user IDs.
- Keep business logic out of DTOs.
- Check `isValid()` before using validated input.

---

## 7. When to Use DTOs

DTOs are useful for:

- API endpoints with structured input.
- Form submissions.
- Endpoints requiring authentication data published by Middlewares.
- Reusable request structures.
- Replacing repeated request parsing logic.
