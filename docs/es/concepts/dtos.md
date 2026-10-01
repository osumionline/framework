# Objetos de Transferencia de Datos (DTO)

Los DTO en **Osumi Framework** son clases sencillas utilizadas para recibir, normalizar y validar datos de entrada procedentes de una petición HTTP.

Proporcionan una forma estructurada y segura para que los componentes accedan a valores de petición tipificados y validados, cabeceras o contexto de Middlewares.

Un DTO debe extender `ODTO` y definir sus campos mediante `#[ODTOField]`.

---

## 1. Propósito de un DTO

Los DTO están diseñados para:

- Recopilar y convertir tipos de datos de la petición.
- Leer valores de confianza desde contexto de Middlewares.
- Leer valores desde cabeceras HTTP.
- Aplicar reglas de validación antes de ejecutar la lógica del componente.
- Mantener consistente el análisis de peticiones en todo el framework.
- Evitar llamadas repetidas a `$req->getParam...()` dentro de los componentes.

Cuando un componente define:

```php
public function run(MovieDTO $dto): void
```

el framework automáticamente:

1. Instancia `MovieDTO`.
2. Carga sus valores.
3. Aplica sus reglas de validación.
4. Lo inyecta en `run()`.

---

## 2. Clase base: `ODTO`

`ODTO` utiliza reflexión para inspeccionar las propiedades públicas del DTO y sus definiciones `#[ODTOField]`.

### 2.1 Orígenes de datos

Un campo puede utilizar un origen explícito.

#### Contexto de Middleware

Si se definen `middleware` y `middlewareProperty`, el valor se obtiene mediante:

```php
$req->getMiddlewareValue(
	$middlewareName,
	$middlewareProperty
);
```

El contexto de Middleware es un origen explícito. Si el valor de contexto no existe, el valor del DTO permanece en `null`; no se recurre como alternativa a los datos enviados por el cliente.

#### Cabecera

Si se define `header`, el valor se obtiene de la cabecera HTTP correspondiente.

Un campo no puede definir a la vez un origen de Middleware y un origen de cabecera.

#### Parámetros de la petición

Cuando no se define un origen explícito, el valor se carga desde los parámetros de la petición según el tipo de la propiedad:

- `int` → `getParamInt()`
- `float` → `getParamFloat()`
- `bool` → `getParamBool()`
- `string` → `getParamString()`
- `array` → `getParam()`

### 2.2 Validación

Después de cargar los valores, ODTO evalúa:

- `required`
- `requiredIf`

Los errores de validación se pueden consultar con:

```php
$dto->getValidationErrors();
```

La validez se puede comprobar con:

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

| Atributo | Descripción |
| -------- | ----------- |
| `required` | El campo debe contener un valor. |
| `requiredIf` | El campo es obligatorio cuando otro campo DTO contiene un valor. |
| `middleware` | Nombre público del Middleware utilizado como origen del campo. |
| `middlewareProperty` | Propiedad de contexto que se lee de ese Middleware. |
| `header` | Cabecera HTTP utilizada como origen del campo. |

Reglas:

- `middleware` y `middlewareProperty` deben definirse juntos.
- Los nombres de Middleware o propiedades vacíos no son válidos.
- Un campo no puede usar simultáneamente contexto de Middleware y una cabecera como orígenes explícitos.
- `requiredIf` debe hacer referencia a otro campo DTO y no puede referenciarse a sí mismo.

---

## 4. Ejemplo de DTO

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

En este ejemplo, `idUser` se obtiene del contexto publicado por `LoginMiddleware`, no de los datos enviados por el cliente.

---

## 5. Uso de un DTO en un componente

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

## 6. Mejores prácticas

- Usa tipos estrictos en las propiedades.
- Prefiere valores nullable cuando un campo pueda estar inicialmente ausente.
- Usa DTO para entradas estructuradas de peticiones.
- Usa `requiredIf` para dependencias entre campos.
- Usa contexto de Middleware para valores internos de confianza, como el ID del usuario autenticado.
- Mantén la lógica de negocio fuera de los DTO.
- Comprueba `isValid()` antes de usar la entrada validada.

---

## 7. Cuándo usar DTO

Los DTO son útiles para:

- Endpoints API con entrada estructurada.
- Envíos de formularios.
- Endpoints que requieren datos de autenticación publicados por Middlewares.
- Estructuras reutilizables de petición.
- Sustituir lógica repetida de análisis de peticiones.
