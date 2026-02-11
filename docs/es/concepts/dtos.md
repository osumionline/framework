# Objetos de Transferencia de Datos (DTO)

Los DTO (Objetos de Transferencia de Datos) en **Osumi Framework** son clases simples que se utilizan para recibir, normalizar y validar datos de entrada provenientes de una solicitud HTTP.
Proporcionan una forma estructurada y segura para que los componentes accedan a valores de solicitud tipificados y validados, encabezados o resultados de filtros.

Un DTO debe **extender `ODTO`** y definir sus campos mediante el atributo **`#[ODTOField]`**.

---

# 1. Propósito de un DTO

Los DTO están diseñados para:

- Recopilar y convertir datos de solicitud (parámetros de URL, cuerpo JSON, campos de formulario, cadenas de consulta, encabezados, filtros).
- Aplicar reglas de validación _antes_ de que se ejecute la lógica del componente.
- Lograr que la gestión de parámetros sea consistente en todo el framework.
- Evitar llamadas manuales a `$req->getParam...()` dentro de los componentes.
- Evitar que valores inseguros o inesperados lleguen a la lógica de negocio.

Cuando un componente define:

```php
public function run(MovieDTO $dto): void
```

...el framework automáticamente:

1. Instancia `MovieDTO`.
2. Carga los datos de la solicitud.
3. Aplica las reglas de validación definidas en sus atributos.
4. Inyecta el DTO en el método `run()` del componente.

---

# 2. Clase base: `ODTO`

La clase `ODTO` usa **reflexión** para inspeccionar todas las propiedades públicas del DTO, leer sus definiciones `#[ODTOField]` y cargar los datos según corresponda.

### 2.1 Proceso de carga de datos

ODTO carga los valores en este orden de prioridad:

1. **Resultado del filtro**
   Si un campo define `filter` y `filterProperty`, el valor se obtiene de:

```php
$req->getFilter($filterName)[$filterProperty]
```

2. **Valor del encabezado**
   Si un campo define `header: 'X-Header'`, el valor se obtiene de:

```php
$req->getHeader('X-Header')
```

3. **Parámetros de la solicitud**
   El valor se convierte en tipo según el tipo de propiedad:

- `int` → `$req->getParamInt()`
- `float` → `$req->getParamFloat()`
- `bool` → `$req->getParamBool()`
- `string` → `$req->getParamString()`
- `array` → `$req->getParam()`
- default → `null`

### 2.2 Validación

Tras cargar todos los valores, ODTO comprueba automáticamente:

- **required**
  Si `required = true` y falta el valor, se añade un error de validación.

- **requiredIf**
  Si otro campo tiene un valor y este no, se añade un error de validación.

Los errores se almacenan internamente y se pueden recuperar mediante:

```php
$dto->getValidationErrors();
```

Puede comprobar si el DTO es válido con:

```php
$dto->isValid();
```

---

# 3. Atributo `ODTOField`

El atributo `ODTOField` se utiliza para configurar cada propiedad DTO:

```php
#[ODTOField(
  required: false,
  requiredIf: null,
  filter: null,
  filterProperty: null,
  header: null
)]
```

### Opciones de atributo

| Atributo           | Descripción                                                        |
| ------------------ | ------------------------------------------------------------------ |
| **required**       | El campo debe tener un valor.                                      |
| **requiredIf**     | El campo es obligatorio solo si otro campo tiene un valor.         |
| **filter**         | Nombre del filtro cuya salida se debe usar para rellenar el campo. |
| **filterProperty** | Clave de la matriz de salida del filtro que se usará.              |
| **header**         | Nombre del encabezado HTTP del que se leerá el valor.              |

Estas opciones permiten combinaciones eficaces, como obtener ID de usuario de filtros, extraer tokens de API de encabezados o aplicar dependencias condicionales entre campos DTO.

---

# 4. Ejemplo de DTO

Ejemplo de tu proyecto:

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

Este DTO:

- Carga valores tipificados de los parámetros de la solicitud (`int`, `string`, `array`).
- Aplica los campos obligatorios.
- Carga `idUser` del **filtro de inicio de sesión** en lugar de la solicitud del cliente.

---

# 5. Uso de un DTO dentro de un componente

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

### Notas:

- El componente **no** necesita leer manualmente los valores de la solicitud.
- Los valores pasados ​​al componente ya están tipificados y validados.
- La gestión de errores se simplifica.

---

# 6. Mejores prácticas

- Use `?type = null` para todas las propiedades DTO para evitar errores de propiedades tipificadas sin inicializar.
- Prefiera las DTO siempre que reciba datos estructurados (puntos finales de API, formularios, JSON).
- Use `requiredIf` para expresar dependencias lógicas entre campos.
- Use campos `filter` para evitar exponer identificadores confidenciales al cliente.
- Mantenga las DTO simples; no deben contener lógica de negocio.
- Siempre verifique `$dto->isValid()` antes de usarla.

---

# 7. Cuándo usar DTO

Las DTO son ideales para:

- Puntos finales de API que reciben entradas complejas.
- Envíos de formularios con muchos campos.
- Puntos finales que requieren datos de autenticación inyectados por filtros.
- Estructuras de datos reutilizables utilizadas en múltiples componentes.
- Reemplazar la lógica repetida de análisis de solicitudes.
