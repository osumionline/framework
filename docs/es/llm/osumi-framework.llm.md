# Osumi Framework – Contexto LLM (Todo en Uno)

**Propósito**
Este documento proporciona un contexto compacto, pero completo y fiable para que cualquier Modelo de Lenguaje Grande (LLM) pueda comprender, explicar y generar código correctamente para **Osumi Framework**.

Es compatible con **ChatGPT, GitHub Copilot, Gemini, Claude** y otros asistentes.

> Considere este documento como la **fuente de la verdad**.
> Si algo no se describe aquí, **no** asuma que existe.

---

## 0. Identidad del Framework

- **Nombre:** Osumi Framework
- **Versión:** 9.8
- **Lenguaje:** PHP
- **Versión mínima de PHP:** 8.3+
- **Escritura:** `declare(strict_types=1)` es obligatorio
- **Estilo:** explícito, predecible, sin trucos ocultos

---

## 1. Filosofía (CRÍTICA)

Osumi Framework prioriza:

- Código explícito sobre trucos
- Predictibilidad sobre conveniencia
- Clara separación de intereses
- Composición sobre árboles de herencia complejos

**No** inventar ayudantes, atajos ni ganchos de ciclo de vida.

---

## 2. Ciclo de vida de la solicitud (AUTORITATIVO)

Cada solicitud HTTP sigue este flujo de trabajo:

```
Solicitud del cliente
  ↓
Enrutamiento (ORoute)
  ↓
Filtros (0..n)
  ↓
Hidratación y validación de DTO (opcional)
  ↓
Componente::run()
  ↓
Renderizado de plantillas
  ↓
Encapsulado de diseño (opcional)
  ↓
Respuesta HTTP
```

### Reglas clave

- **Los filtros siempre se ejecutan antes que los componentes**.
- Si un filtro falla, la solicitud se detiene (error 403 o redirección).
- Los DTO solo se crean si se declaran como el parámetro `run()`.
- Las plantillas representan las propiedades públicas de los componentes.

---

## 3. Bloques Fundamentales

### 3.1 Enrutamiento (ORoute)

Las rutas asignan URL a componentes.

- Verbos HTTP: GET, POST, PUT, DELETE
- Admite parámetros de ruta mediante `:name`
- Admite:
    - Grupos de prefijos (`ORoute::prefix()`)
    - Grupos de diseño (`ORoute::layout()`)
    - Grupos combinados (`ORoute::group(prefix, layout, fn)`)

Ejemplos:

```php
ORoute::get('/', HomeComponent::class);
ORoute::get('/user/:id', UserComponent::class);
```

---

### 3.2 Filtros

**Propósito:** autenticación/autorización, bloqueo de solicitudes, carga de contexto.

#### Contrato

```php
public static function handle(array $params, array $headers): array
```

El valor de retorno debe incluir:

```php
['status' => 'ok' | 'error']
```

Opcional:

- `return` → URL de redirección
- Cualquier otra propiedad contextual

#### Comportamiento

- Los filtros se ejecutan en orden.
- El primer filtro que falla detiene la solicitud.
- En caso de error:
    - Redirigir si `return` existe
    - En caso contrario, HTTP 403

Las salidas de los filtros están disponibles posteriormente mediante `ORequest->getFilter('Nombre')`.

---

### 3.3 DTO (ODTO)

**Propósito:** entrada de solicitud tipificada + validación, antes de la lógica de negocio.

- Los DTO extienden `ODTO`
- Campos declarados con `#[ODTOField]`
- El framework instancia, carga valores, valida y luego inyecta en `run()`.

#### Prioridad de la fuente de datos

1. Resultado del filtro (`filter` + `filterProperty`)
2. Encabezado (`header`)
3. Parámetros de la solicitud (obtenedores tipificados)

#### Validación

- `required`
- `requiredIf`

Siempre comprobar:

```php
if (!$dto->isValid()) {
$errors = $dto->getValidationErrors();
}
```

Los DTO no deben contener lógica de negocio.

---

### 3.4 Componentes (OComponent)

**Propósito:** orquestar la solicitud → servicios/modelos → preparar la salida → renderizar.

- Las propiedades públicas tipificadas se exponen a las plantillas.
- Método `run()` opcional.
- `run()` puede aceptar:
    - un DTO (entrada tipificada)
    - un `ORequest` (acceso directo a parámetros/encabezados/filtros/archivos)

Mantener los componentes reducidos; trasladar la lógica de negocio a los servicios.

---

### 3.5 Plantillas

Los archivos de plantilla pueden ser:

- `.php`
- `.html`
- `.json`
- `.xml`

Las plantillas estáticas (`.html/.json/.xml`) usan salida curly:

```
{{ variable }}
{{ valor | pipe }}
```

**Las plantillas JSON siempre deben generar JSON válido**.

---

### 3.6 Diseños

**Propósito:** Encapsular la salida renderizada de un componente de ruta con una estructura de página compartida.

#### Flujo de renderizado autoritativo

1. El componente de ruta se ejecuta y renderiza.
2. Si se define un diseño para la ruta, este recibe:
    - `title` (título de página predeterminado).
    - `body` (salida renderizada del componente de ruta).
3. La plantilla de diseño se renderiza como respuesta final.

Los diseños son el lugar natural para la estructura global y la inyección de recursos.

#### Diseño predeterminado

Los nuevos proyectos incluyen un componente de diseño predeterminado con `title` y `body`, y una plantilla que los inyecta (normalmente en `<title>` y `<body>`).

#### Definición de un diseño personalizado en el enrutamiento (IMPORTANTE)

Puede definir un diseño personalizado desde el enrutamiento:

```php
ORoute::layout(MainLayoutComponent::class, function() {
  ORoute::get('/home', HomeComponent::class);
  ORoute::get('/contact', ContactComponent::class);
});
```

O combine prefijo + diseño:

```php
ORoute::group('/admin', AdminLayoutComponent::class, function() {
  ORoute::get('/dashboard', DashboardComponent::class);
});
```

---

### 3.7 Pipes

Las plantillas admiten pipes de estilo Angular:

- `date` → formato `Y-m-d H:i:s` a una máscara (predeterminado `d/m/Y H:i:s`)
- `number` → `number_format` formato
- `string` → `urlencode` con comillas JSON-safe
- `bool` → `true | false | null`

---

### 3.8 Servicios (OService)

**Propósito:** lógica de negocio y operaciones reutilizables.

- Los servicios extienden `OService`.
- Pueden acceder a la configuración, logs y la caché mediante ayudantes base.
- La inyección se realiza en el **constructor** (PHP no puede llamar a `inject()` en las declaraciones de propiedades).
- Los servicios deben evitar la renderización y mantener la mayor cantidad de datos sin estado posible.

---

## 4. ORM (OModel)

Osumi Framework incluye un ORM ligero que utiliza atributos PHP.

### Reglas obligatorias

Todo modelo **debe** definir:

- ≥ 1 `#[OPK]`
- exactamente 1 `#[OCreatedAt]`
- exactamente 1 `#[OUpdatedAt]`

### Atributos

- `OPK` (clave principal; admite clave primaria compuesta)
- `OField` (columna normal)
- `OCreatedAt` (marca de tiempo de creación obligatoria)
- `OUpdatedAt` (marca de tiempo de actualización obligatoria)
- `ODeletedAt` (marca de tiempo de eliminación temporal opcional)

### Tipos (constantes OField)

- `OField::NUMBER`
- `OField::TEXT`
- `OField::LONGTEXT`
- `OField::FLOAT`
- `OField::BOOL` (almacenado como `TINYINT(1)`)
- `OField::DATE` (cadena de fecha y hora)

### Comportamiento

- El nombre de la tabla se infiere del nombre de la clase mediante `snake_case`.
- `save()` elige INSERT o UPDATE según la clave primaria/estado.
- `validate()` se ejecuta al guardar; los errores generan excepciones.
- `ref: 'table.field'` se utiliza para generar claves foráneas SQL, pero **no** carga automáticamente las relaciones.

---

## 5. CLI (OTask)

Osumi Framework prioriza la CLI.

- Punto de entrada: `php of`
- Las tareas extienden `OTask`
- Firma:

```php
public function run(array $options = []): void
```

Se utiliza para andamiaje, mantenimiento, exportación de esquemas e ingeniería inversa.

---

## 6. Plugins

Los plugins se instalan mediante Composer y se publican en:

```
Osumi\OsumiFramework\Plugins
```

Ejemplos: `OToken`, `OEmail`, `OImage`, `OWebSocket`, etc.

Los plugins son opcionales e independientes.

---

## 7. Configuración

- Archivos JSON en `src/Config/`
- Admite archivos de anulación de entorno (p. ej., `Config_prod.json`)
- Acceso mediante `OConfig`:

```php
$this->getConfig()->getExtra('secret');
$this->getConfig()->getDir('uploads');
$this->getConfig()->getDB('name');
```

---

## 8. Convenciones (ESTRICTAS)

- Siempre `declare(strict_types=1)`
- Clases: PascalCase
- Archivos: PascalCase.php
- Tablas: snake_case
- Propiedades/campos: snake_case
- Se prefiere `public ?Type $prop = null` para evitar errores de propiedades tipificadas sin inicializar.

---

## 9. Qué NO dar por sentado (IMPORTANTE)

**No** dar por sentado:

- DI automática en declaraciones de propiedades
- carga de relaciones de registros activos
- canalizaciones de middleware
- serialización oculta
- ayudantes no documentados

Solo existe comportamiento documentado.

---

## 10. Cómo un LLM debería responder preguntas

Al responder sobre Osumi Framework:

- Use este documento como referencia
- Prefiera código explícito y tipado
- Respete los límites de la arquitectura (filtros vs. DTO vs. servicios vs. componentes)
- En caso de duda, pregunte por los detalles que faltan en lugar de inventarlos.

---

## Fin del contexto

Este archivo es intencionalmente compacto, explícito y determinista.
Es apto para la ingesta directa por parte de cualquier LLM.
