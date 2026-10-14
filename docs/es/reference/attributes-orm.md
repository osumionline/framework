# Referencia de Atributos ORM

En Osumi Framework, las asignaciones de bases de datos se definen mediante atributos PHP en las propiedades de la clase del modelo. Este documento detalla todos los atributos disponibles, sus parámetros y comportamientos esperados.

---

## `#[OPK]`

Define un campo de clave principal. Cada modelo debe tener al menos uno.

| Parámetro  | Tipo     | Predeterminado   | Descripción                                             |
| :--------- | :------- | :--------------- | :------------------------------------------------------ |
| `type`     | `string` | `OField::NUMBER` | Tipo de dato (ver Tipos a continuación).                |
| `incr`     | `bool`   | `true`           | Si el campo es autoincremental.                         |
| `comment`  | `string` | `''`             | Comentario para la columna de la base de datos.         |
| `ref`      | `string` | `''`             | Referencia de clave externa (formato: `'tabla.campo'`). |
| `nullable` | `bool`   | `true`           | Si el campo puede almacenar valores `null`.             |
| `default`  | `mixed`  | `null`           | Valor predeterminado de la columna.                     |

---

## `#[OField]`

Define una columna de base de datos estándar.

| Parámetro   | Tipo     | Predeterminado | Descripción                                                                                        |
| :---------- | :------- | :------------- | :------------------------------------------------------------------------------------------------- |
| `type`      | `string` | `null`         | Tipo de dato (obligatorio).                                                                        |
| `nullable`  | `bool`   | `true`         | Si el campo puede almacenar valores `null`.                                                        |
| `default`   | `mixed`  | `null`         | Valor predeterminado de la columna.                                                                |
| `max`       | `int`    | `50`           | Tamaño/longitud máximos del campo.                                                                 |
| `comment`   | `string` | `''`           | Comentario para la columna de la base de datos.                                                    |
| `visible`   | `bool`   | `true`         | Si el campo se incluye al serializar el modelo.                                                    |
| `ref`       | `string` | `''`           | Referencia de clave externa (formato: `'tabla.campo'`).                                            |
| `precision` | `?int`   | `null`         | Precisión total de un campo `DECIMAL`. Solo válido con `OField::DECIMAL`; si se omite usa `10`.    |
| `scale`     | `?int`   | `null`         | Número de decimales de un campo `DECIMAL`. Solo válido con `OField::DECIMAL`; si se omite usa `2`. |

---

## Atributos temporales

Estos atributos gestionan el sellado de tiempo automático. Cada modelo **debe** tener exactamente un `#[OCreatedAt]` y un `#[OUpdatedAt]`.

### `#[OCreatedAt]`

Se configura automáticamente al crear un registro por primera vez (INSERTAR).

- **Parámetro**: `comment` (cadena) - Comentario de la columna.

### `#[OUpdatedAt]`

Se actualiza automáticamente al modificar el registro (ACTUALIZAR).

- **Parámetro**: `comment` (cadena) - Comentario de la columna.

### `#[ODeletedAt]`

Se utiliza para la función de eliminación temporal (seguimiento de cuándo se eliminó un registro).

- **Parámetro**: `comment` (cadena) - Comentario de la columna.

---

## Tipos de datos (Constantes `OField`)

Al definir `type` en `#[OPK]` o `#[OField]`, utilice estas constantes de la clase `Osumi\OsumiFramework\ORM\OField`:

- `OField::NUMBER`: valores enteros (`?int`).
- `OField::TEXT`: cadenas cortas (`?string`, normalmente `VARCHAR`).
- `OField::LONGTEXT`: bloques de texto grandes (`?string`, normalmente `LONGTEXT`).
- `OField::FLOAT`: números de punto flotante (`?float`).
- `OField::BOOL`: valores booleanos (`?bool`).
- `OField::DATE`: cadenas de fecha/hora (`?string`).
- `OField::DECIMAL`: valores decimales exactos (`?string`, genera `DECIMAL(precision,scale)`).

### Campos DECIMAL

`OField::DECIMAL` debe utilizar siempre una propiedad `?string`. El ORM no acepta valores `float` ni `int` para estos campos, evitando conversiones intermedias de punto flotante.

```php
#[OField(
	type: OField::DECIMAL,
	precision: 12,
	scale: 2,
	nullable: false
)]
public ?string $amount = null;
```

La precisión predeterminada es `10` y la escala predeterminada es `2`. La precisión válida está entre `1` y `65`; la escala entre `0` y `30`, y nunca puede superar la precisión.

Como entrada se aceptan `.` y `,` como separadores decimales:

```text
"12"    → "12.00"
"12.5"  → "12.50"
"12,5"  → "12.50"
"-0,50" → "-0.50"
```

La representación interna siempre utiliza `.` y exactamente el número de decimales definido por `scale`.

No se admiten notación exponencial, separadores de miles ni valores que requieran redondeo. Un valor con más decimales de los permitidos produce un error.

`precision` y `scale` solo pueden utilizarse con `OField::DECIMAL`. Utilizarlos con cualquier otro tipo produce un error de definición del modelo.

`OField::DECIMAL` no está permitido en `#[OPK]`.

---

## Ejemplo de uso

```php
use Osumi\OsumiFramework\ORM\OModel;
use Osumi\OsumiFramework\ORM\OPK;
use Osumi\OsumiFramework\ORM\OField;
use Osumi\OsumiFramework\ORM\OCreatedAt;
use Osumi\OsumiFramework\ORM\OUpdatedAt;

class Product extends OModel {
    #[OPK(comment: 'ID único del producto')]
    public ?int $id = null;

    #[OField(type: OField::TEXT, max: 150, nullable: false, comment: 'Nombre del producto')]
    public ?string $name = null;

    #[OField(type: OField::NUMBER, ref: 'category.id', comment: 'Enlace a la categoría')]
    public ?int $id_category = null;

    #[OCreatedAt(comment: 'Marca de tiempo de creación')]
    public ?string $created_at = null;

    #[OUpdatedAt(comment: 'Marca de tiempo de la última actualización')]
    public ?string $updated_at = null;
}
```

---

## Lógica de validación

La clase base `OModel` utiliza estos atributos durante el proceso `save()`:

1. **Comprobación de longitud**: Si una cadena de propiedad supera el valor `max`, se genera una excepción.
2. **Nulabilidad**: Si una propiedad es `null` pero `nullable` se establece en `false`, se genera una excepción.
3. **Clave principal**: garantiza que al menos un `#[OPK]` esté definido antes de continuar.
