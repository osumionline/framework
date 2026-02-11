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

## CLI Integration

- **`generateModel`**: Generates the SQL structure (CREATE TABLE) based on your model definitions, including indexes and foreign keys defined in `ref`.

---

## Best Practices

- **Typed Properties**: Use public nullable typed properties (e.g., `public ?string $name = null`) for clean initialization.
- **Default Values**: Always initialize properties to `null` to avoid "uninitialized property" errors.
