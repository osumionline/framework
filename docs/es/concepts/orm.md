# ORM (Mapeo Objeto-Relacional)

Osumi Framework incluye un ORM ligero que mapea clases PHP a tablas de bases de datos mediante atributos PHP. Todos los modelos deben extender la clase `OModel`.

---

## Definición de un Modelo

Los modelos se almacenan en `src/Model/`. El framework infiere automáticamente el nombre de la tabla a partir del nombre de la clase mediante snake_case (por ejemplo, la clase `ProductCategory` se mapea a la tabla `product_category`).

### Campos Obligatorios

Para que un modelo sea válido, **debe** definir los siguientes atributos:

- **Al menos un `#[OPK]`**: Define la Clave Primaria (admite claves compuestas).
- **Exactamente un `#[OCreatedAt]`**: Almacena automáticamente la marca de tiempo de creación.
- **Exactamente un `#[OUpdatedAt]`**: Se actualiza automáticamente con cada modificación.

### Modelo de ejemplo

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Model;

use Osumi\OsumiFramework\ORM\OModel;
use Osumi\OsumiFramework\ORM\OPK;
use Osumi\OsumiFramework\ORM\OField;
use Osumi\OsumiFramework\ORM\OCreatedAt;
use Osumi\OsumiFramework\ORM\OUpdatedAt;

class User extiende OModel {
  #[OPK(comment: 'ID único')]
  public ?int $id = null;

  #[OField(max: 100, nullable: false)]
  public ?string $name = null;

  #[OCreatedAt(comment: 'Fecha de creación')]
  public ?string $created_at = null;

  #[OUpdatedAt(comment: 'Última actualización')]
  public ?string $updated_at = null;
}

```

---

## Atributos principales

| Atributo        | Descripción                                                                                                                                                                |
| --------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `#[OPK]`        | Clave principal. Se puede usar en varios campos para claves compuestas. El valor predeterminado es autoincremental; de lo contrario, agregue el valor `incr` como `false`. |
| `#[OField]`     | Columna estándar. Admite `max`, `nullable` y `ref`.                                                                                                                        |
| `#[OCreatedAt]` | Campo obligatorio para el seguimiento de la creación de registros.                                                                                                         |
| `#[OUpdatedAt]` | Campo obligatorio para el seguimiento de la actualización de registros.                                                                                                    |

> **Nota sobre `ref**`: El parámetro `ref`(p. ej.,`ref: 'user.id'`) se utiliza para definir claves foráneas en la sentencia SQL generada (CREATE TABLE), pero no activa la carga automática de objetos.

---

## Trabajando con datos

### Búsqueda de registros

Recuperación de datos mediante métodos estáticos:

- **`where(array $criteria)`**: Devuelve una matriz de objetos que cumplen los criterios.
- **`findOne(array $criteria)`**: Devuelve un solo objeto o `null`.

```php
  $user = User::where(['id' => 1]);
  if (!is_null($user)) {
    echo "User ".$user->name." found";
  }
```

### Guardado y actualización

El método `save()` detecta automáticamente si un registro es nuevo o existente según la clave primaria.

```php
$user = new User();
$user->name = 'New User';
$user->save(); // Realiza una INSERT

$user = User::findOne(['id' => 1]);
$user->name = 'New name';
$user->save(); // Realiza una UPDATE

```

### Validación

El framework llama a `validate()` durante el proceso `save()`. Si un valor excede la longitud `max` o es `null` cuando no está permitido, se genera una **Exception**.

---

### Campos DECIMAL exactos

Para valores que no deben pasar por representación de punto flotante, como importes monetarios, porcentajes o cantidades de precisión fija, utilice `OField::DECIMAL`.

```php
#[OField(
	type: OField::DECIMAL,
	precision: 12,
	scale: 2,
	nullable: false,
	comment: 'Importe'
)]
public ?string $amount = null;
```

Los campos `DECIMAL` se representan siempre mediante `?string`, nunca mediante `?float`.

El ORM acepta `.` y `,` como separadores decimales de entrada y normaliza el valor a `.` y escala fija:

```php
$model->amount = '12,5';
$model->save();

// $model->amount === '12.50'
```

No se realizan redondeos automáticos. Si un valor supera la precisión o la escala configurada, el ORM genera un error.

Las condiciones de consulta siguen las mismas reglas:

```php
Payment::where([
	'amount' => '12,5'
]);
```

se normaliza internamente a `"12.50"`, mientras que:

```php
Payment::where([
	'amount' => 12.5
]);
```

es inválido porque un `float` no puede utilizarse para un campo `DECIMAL`.

Los campos `DECIMAL` no pueden utilizarse como claves primarias.

---

## Integración CLI

- **`generateModel`**: genera la estructura SQL a partir de los modelos, incluyendo `DECIMAL(precision,scale)`.
- **`generateModelFrom`**: genera modelos desde una definición JSON y admite campos `OField::DECIMAL`.
- **`generateModelFromDB`**: interpreta columnas SQL `DECIMAL(p,s)` como propiedades PHP `?string`, conservando precisión, escala y defaults exactos.

---

## Mejores prácticas

- **Propiedades tipificadas**: Utilice propiedades tipificadas públicas que admitan valores nulos (p. ej., `public ?string $name = null`) para una inicialización limpia.
- **Valores predeterminados**: Siempre inicialice las propiedades con `null` para evitar errores de "propiedad no inicializada".
