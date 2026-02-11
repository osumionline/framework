# ORM Attributes Reference

In Osumi Framework, database mappings are defined using PHP Attributes on model class properties. This document details all available attributes, their parameters, and expected behaviors.

---

## `#[OPK]`

Defines a Primary Key field. Every model must have at least one.

| Parameter  | Type     | Default          | Description                                      |
| :--------- | :------- | :--------------- | :----------------------------------------------- |
| `type`     | `string` | `OField::NUMBER` | Data type (see Types below).                     |
| `incr`     | `bool`   | `true`           | Whether the field is auto-incremental.           |
| `comment`  | `string` | `''`             | Comment for the database column.                 |
| `ref`      | `string` | `''`             | Foreign key reference (format: `'table.field'`). |
| `nullable` | `bool`   | `true`           | Whether the field can store `null` values.       |
| `default`  | `mixed`  | `null`           | Default value for the column.                    |

---

## `#[OField]`

Defines a standard database column.

| Parameter  | Type     | Default | Description                                               |
| :--------- | :------- | :------ | :-------------------------------------------------------- |
| `type`     | `string` | `null`  | Data type (Mandatory).                                    |
| `nullable` | `bool`   | `true`  | Whether the field can store `null` values.                |
| `default`  | `mixed`  | `null`  | Default value for the column.                             |
| `max`      | `int`    | `50`    | Maximum size/length for the field.                        |
| `comment`  | `string` | `''`    | Comment for the database column.                          |
| `visible`  | `bool`   | `true`  | Whether the field is included when serializing the model. |
| `ref`      | `string` | `''`    | Foreign key reference (format: `'table.field'`).          |

---

## Temporal Attributes

These attributes handle automatic timestamping. Each model **must** have exactly one `#[OCreatedAt]` and one `#[OUpdatedAt]`.

### `#[OCreatedAt]`

Automatically set when a record is first created (INSERT).

- **Parameter**: `comment` (string) - Column comment.

### `#[OUpdatedAt]`

Automatically updated whenever the record is modified (UPDATE).

- **Parameter**: `comment` (string) - Column comment.

### `#[ODeletedAt]`

Used for soft-delete functionality (tracking when a record was "removed").

- **Parameter**: `comment` (string) - Column comment.

---

## Data Types (`OField` Constants)

When defining `type` in `#[OPK]` or `#[OField]`, use these constants from the `Osumi\OsumiFramework\ORM\OField` class:

- `OField::NUMBER`: Integer values.
- `OField::TEXT`: Short strings (usually mapped to `VARCHAR`).
- `OField::LONGTEXT`: Large text blocks (usually mapped to `LONGTEXT`).
- `OField::FLOAT`: Floating-point numbers.
- `OField::BOOL`: Boolean values.
- `OField::DATE`: Date/Time strings.

---

## Usage Example

```php
use Osumi\OsumiFramework\ORM\OModel;
use Osumi\OsumiFramework\ORM\OPK;
use Osumi\OsumiFramework\ORM\OField;
use Osumi\OsumiFramework\ORM\OCreatedAt;
use Osumi\OsumiFramework\ORM\OUpdatedAt;

class Product extends OModel {
    #[OPK(comment: 'Unique product ID')]
    public ?int $id = null;

    #[OField(type: OField::TEXT, max: 150, nullable: false, comment: 'Product name')]
    public ?string $name = null;

    #[OField(type: OField::NUMBER, ref: 'category.id', comment: 'Link to category')]
    public ?int $id_category = null;

    #[OCreatedAt(comment: 'Creation timestamp')]
    public ?string $created_at = null;

    #[OUpdatedAt(comment: 'Last update timestamp')]
    public ?string $updated_at = null;
}

```

---

## Validation Logic

The `OModel` base class uses these attributes during the `save()` process:

1. **Length Check**: If a property string exceeds the `max` value, an exception is thrown.
2. **Nullability**: If a property is `null` but `nullable` is set to `false`, an exception is thrown.
3. **Primary Key**: Ensures at least one `#[OPK]` is defined before proceeding.
