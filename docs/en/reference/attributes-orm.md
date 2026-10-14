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

| Parameter   | Type     | Default | Description                                                                                                     |
| :---------- | :------- | :------ | :-------------------------------------------------------------------------------------------------------------- |
| `type`      | `string` | `null`  | Data type (Mandatory).                                                                                          |
| `nullable`  | `bool`   | `true`  | Whether the field can store `null` values.                                                                      |
| `default`   | `mixed`  | `null`  | Default value for the column.                                                                                   |
| `max`       | `int`    | `50`    | Maximum size/length for the field.                                                                              |
| `comment`   | `string` | `''`    | Comment for the database column.                                                                                |
| `visible`   | `bool`   | `true`  | Whether the field is included when serializing the model.                                                       |
| `ref`       | `string` | `''`    | Foreign key reference (format: `'table.field'`).                                                                |
| `precision` | `?int`   | `null`  | Total precision of a `DECIMAL` field. Only valid with `OField::DECIMAL`; when omitted, `10` is used.            |
| `scale`     | `?int`   | `null`  | Number of fractional digits of a `DECIMAL` field. Only valid with `OField::DECIMAL`; when omitted, `2` is used. |

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

- `OField::NUMBER`: Integer values (`?int`).
- `OField::TEXT`: Short strings (`?string`, normally mapped to `VARCHAR`).
- `OField::LONGTEXT`: Large text blocks (`?string`, normally mapped to `LONGTEXT`).
- `OField::FLOAT`: Floating-point numbers (`?float`).
- `OField::BOOL`: Boolean values (`?bool`).
- `OField::DATE`: Date/time strings (`?string`).
- `OField::DECIMAL`: Exact decimal values (`?string`, generates `DECIMAL(precision,scale)`).

### DECIMAL Fields

`OField::DECIMAL` must always use a `?string` property. The ORM does not accept `float` or `int` values for these fields, avoiding intermediate floating-point conversions.

```php
#[OField(
	type: OField::DECIMAL,
	precision: 12,
	scale: 2,
	nullable: false
)]
public ?string $amount = null;
```

The default precision is `10` and the default scale is `2`. Valid precision ranges from `1` to `65`; valid scale ranges from `0` to `30`, and scale can never exceed precision.

Both `.` and `,` are accepted as decimal separators on input:

```text
"12"    → "12.00"
"12.5"  → "12.50"
"12,5"  → "12.50"
"-0,50" → "-0.50"
```

The internal representation always uses `.` and exactly the number of fractional digits defined by `scale`.

Scientific notation, thousands separators and values requiring rounding are not accepted. A value with more fractional digits than allowed causes an error.

`precision` and `scale` can only be used with `OField::DECIMAL`. Using them with any other type causes a model definition error.

`OField::DECIMAL` is not allowed in `#[OPK]`.

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
