# ORM (Object-Relational Mapping)

The Osumi Framework includes a lightweight ORM that maps PHP classes to database tables using PHP Attributes. All models must extend the `OModel` class.

---

## Defining a Model

Models are stored in `src/Model/`. The framework automatically infers the table name from the class name using snake_case (e.g., `ProductCategory` class maps to `product_category` table).

### Mandatory Fields

For a model to be valid, it **must** define the following attributes:

- **At least one `#[OPK]`**: Defines the Primary Key (supports composite keys).
- **Exactly one `#[OCreatedAt]`**: Automatically stores the creation timestamp.
- **Exactly one `#[OUpdatedAt]`**: Automatically updates on every modification.

### Example Model

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Model;

use Osumi\OsumiFramework\ORM\OModel;
use Osumi\OsumiFramework\ORM\OPK;
use Osumi\OsumiFramework\ORM\OField;
use Osumi\OsumiFramework\ORM\OCreatedAt;
use Osumi\OsumiFramework\ORM\OUpdatedAt;

class User extends OModel {
  #[OPK(comment: 'Unique ID')]
  public ?int $id = null;

  #[OField(max: 100, nullable: false)]
  public ?string $name = null;

  #[OCreatedAt(comment: 'Creation date')]
  public ?string $created_at = null;

  #[OUpdatedAt(comment: 'Last update')]
  public ?string $updated_at = null;
}

```

---

## Core Attributes

| Attribute       | Description                                                                                                                          |
| --------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| `#[OPK]`        | Primary Key. Can be used on multiple fields for composite keys. Defaults to auto-incremental, otherwise add `incr` value as `false`. |
| `#[OField]`     | Standard column. Supports `max`, `nullable`, and `ref`.                                                                              |
| `#[OCreatedAt]` | Mandatory field for record creation tracking.                                                                                        |
| `#[OUpdatedAt]` | Mandatory field for record update tracking.                                                                                          |

> **Note on `ref**`: The `ref`parameter (e.g.,`ref: 'user.id'`) is used to define foreign keys in the generated SQL (CREATE TABLE) but does not trigger automatic object loading.

---

## Working with Data

### Finding Records

Retreive data using static methods:

- **`where(array $criteria)`**: Returns an array of objects matching the criteria.
- **`findOne(array $criteria)`**: Returns a single object or `null`.

```php
  $user = User::where(['id' => 1]);
  if (!is_null($user)) {
    echo "User ".$user->name." found";
  }
```

### Saving and Updating

The `save()` method automatically detects if a record is new or existing based on the PK.

```php
$user = new User();
$user->name = 'New User';
$user->save(); // Performs an INSERT

$user = User::findOne(['id' => 1]);
$user->name = 'New name';
$user->save(); // Performs an UPDATE

```

### Validation

The framework calls `validate()` during the `save()` process. If a value exceeds `max` length or is `null` when not allowed, an **Exception** is thrown.

---

### Exact DECIMAL Fields

For values that must not pass through floating-point representation, such as monetary amounts, percentages or fixed-precision quantities, use `OField::DECIMAL`.

```php
#[OField(
	type: OField::DECIMAL,
	precision: 12,
	scale: 2,
	nullable: false,
	comment: 'Amount'
)]
public ?string $amount = null;
```

`DECIMAL` fields are always represented by `?string`, never by `?float`.

The ORM accepts `.` and `,` as decimal separators on input and normalizes the value to `.` with a fixed scale:

```php
$model->amount = '12,5';
$model->save();

// $model->amount === '12.50'
```

No automatic rounding is performed. If a value exceeds the configured precision or scale, the ORM throws an error.

Query conditions follow the same rules:

```php
Payment::where([
	'amount' => '12,5'
]);
```

is internally normalized to `"12.50"`, while:

```php
Payment::where([
	'amount' => 12.5
]);
```

is invalid because a `float` cannot be used for a `DECIMAL` field.

`DECIMAL` fields cannot be used as primary keys.

---

### DATE and DATETIME Fields

Since Osumi Framework 9.12, SQL `DATE` and `DATETIME` types are represented independently.

An SQL `DATE` field uses `OField::DATE` and a PHP `?string` property:

```php
#[OField(
	type: OField::DATE,
	nullable: false
)]
public ?string $event_date = null;
```

Its canonical format is:

```text
Y-m-d
```

for example:

```text
2026-10-14
```

An SQL `DATETIME` field uses `OField::DATETIME`:

```php
#[OField(
	type: OField::DATETIME,
	nullable: false
)]
public ?string $processed_at = null;
```

Its canonical format is:

```text
Y-m-d H:i:s
```

for example:

```text
2026-10-14 15:30:45
```

The ORM validates temporal values strictly. Invalid calendar dates such as `2026-02-30` and invalid times are rejected.

Automatic `#[OCreatedAt]`, `#[OUpdatedAt]` and `#[ODeletedAt]` fields internally use `DATETIME`.

The `get()` method can format both temporal types with an explicit mask:

```php
$model->get(
	'event_date',
	'd/m/Y'
);

$model->get(
	'processed_at',
	'd/m/Y H:i'
);
```

The template `date` pipe also supports both formats. Without an explicit mask:

- `DATE` uses `d/m/Y`;
- `DATETIME` uses `d/m/Y H:i:s`.

---

### BIGINT Fields

Signed SQL `BIGINT` columns use `OField::BIGINT` and PHP `?int` properties:

```php
#[OField(
	type: OField::BIGINT
)]
public ?int $external_id = null;
```

They may also be used as primary keys:

```php
#[OPK(
	type: OField::BIGINT
)]
public ?int $id = null;
```

`BIGINT` requires a 64-bit PHP environment.

The ORM does not support `BIGINT UNSIGNED` columns or `ZEROFILL` modifiers.

---

## CLI Integration

- **`generateModel`**: Generates the SQL structure from model definitions, including `DECIMAL(precision,scale)`, `DATE`, `DATETIME` and `BIGINT`.
- **`generateModelFrom`**: Generates models from a JSON definition and supports `OField::DECIMAL`, `OField::DATE`, `OField::DATETIME` and `OField::BIGINT`.
- **`generateModelFromDB`**: Interprets SQL `DECIMAL(p,s)`, `DATE`, `DATETIME` and `BIGINT` columns, preserving the types and metadata supported by the ORM.

---

## Best Practices

- **Typed Properties**: Use public nullable typed properties (e.g., `public ?string $name = null`) for clean initialization.
- **Default Values**: Always initialize properties to `null` to avoid "uninitialized property" errors.
