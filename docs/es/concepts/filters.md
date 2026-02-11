# Filtros

Los filtros en **Osumi Framework** son clases pequeñas y reutilizables que se ejecutan **antes** de que se ejecute un componente.
Se utilizan comúnmente para:

- Autenticación y autorización
- Validación de claves API/tokens
- Preprocesamiento de solicitudes
- Verificación de permisos
- Precarga de datos contextuales (usuario, inquilino, configuración regional...)

Los filtros permiten centralizar la lógica que debe ejecutarse _antes de cada solicitud a ciertas rutas_, manteniendo los componentes limpios y enfocados.

---

# 1. ¿Qué es un filtro?

Un filtro es una clase PHP, generalmente ubicada en `src/App/Filter/`, que implementa un método estático:

```php
public static function handle(array $params, array $headers): array
```

Debe devolver un **array asociativo** con al menos:

```php
[
  'status' => 'ok' | 'error',
  // otros valores...
]
```

El filtro revisa un encabezado de `Autorización`, valida un token y devuelve un estado `"ok"` o `"error"`, además de un ID de usuario opcional.

---

# 2. Filtro de ejemplo

Aquí está un `LoginFilter` real:

```php
class LoginFilter {
  public static function handle(array $params, array $headers): array {
    global $core;
    $ret = ['status' => 'error', 'id' => null];

    $tk = new OToken($core->config->getExtra('secret'));

    if ($tk->checkToken($headers['Authorization'])) {
      $ret['status'] = 'ok';
      $ret['id'] = intval($tk->getParam('id'));
    }

    return $ret;
  }
}
```

Este ejemplo demuestra:

- Lectura de encabezados
- Validación de un token
- Devolución de datos contextuales (`id`) que posteriormente utilizarán los DTO o componentes

---

# 3. Cómo se ejecutan los filtros (Flujo del framework)

OCore ejecuta los filtros como parte del ciclo de vida de la solicitud, _antes de que se instancia el componente_.
El proceso se puede resumir:

1. El sistema de enrutamiento identifica la ruta coincidente y su lista de filtros.

2. OCore crea `$url_result` a partir de la solicitud. 3. Para cada filtro (en orden):

- Instanciar la clase de filtro
- Llamar a su `handle($params, $headers)`
- Validar el `"status"` devuelto
- Almacenar el resultado si el estado es `"ok"`
- Detener y devolver un error si es `"error"`

Lógica exacta en OCore:

```php
foreach ($url_result['filters'] as $filter) {
  $filter_instance = new $filter();
  $value = $filter_instance->handle(
    $url_result['params'],
    $url_result['headers']
  );

  if ($value['status'] !== 'ok') {
    // Gestionar error o redirección
    ...
    break;
  }

  $filter_results[$class_name] = $value;
}
```

---

# 4. ¿Qué sucede cuando falla un filtro?

Si algún filtro devuelve `"status" !== "ok"`:

### La solicitud se detiene inmediatamente.

OCore deja de procesar el resto de los filtros e impide que el componente se ejecute.

### Si se incluye `"return"`

El framework redirige a la URL especificada.

### De lo contrario, el framework devuelve **403 Prohibido**.

OCore establece HTTP 403 y muestra una página de error.

Esto garantiza que las solicitudes no autorizadas nunca lleguen a la lógica de negocio.

---

# 5. Acceso a los resultados de los filtros dentro de la aplicación

Después de que todos los filtros sean correctos, OCore crea un objeto `ORequest`:

```php
$req = new ORequest($url_result, $filter_results);

```

Esto hace que todos los resultados del filtro estén disponibles a través de:

```php
$req->getFilter('Login');
```

Por ejemplo:

```php
$login = $req->getFilter('Login');

if ($login['status'] === 'ok') {
  $userId = $login['id'];
}
```

Los nombres de los filtros se normalizan eliminando el sufijo `"Filter"` del nombre de la clase, tal como lo hace OCore mediante la reflexión.

---

# 6. Uso de datos de filtro de una DTO

Los campos de una DTO pueden asignar automáticamente valores de filtros mediante:

```php
#[ODTOField(filter: 'Login', filterProperty: 'id')]
public ?int $idUser = null;
```

Esto significa:

- El cliente no puede falsificar ni anular este valor.
- El DTO obtiene el ID de usuario inyectado de forma segura.
- El componente no necesita acceder manualmente al filtro.

Esto funciona porque ODTO lee los datos del filtro mediante `$req->getFilter()` al rellenar los campos.

---

# 7. Definición de filtros en rutas

En un archivo de rutas:

```php
ORoute::post(
  '/profile',
  ProfileComponent::class,
  [LoginFilter::class]
);
```

Cuando se llama a este punto final:

1. El enrutador detecta `/profile`.
2. Antes de ejecutar el componente, OCore ejecuta `LoginFilter`.
3. Si el filtro falla, la solicitud finaliza.
4. Si pasa la prueba, el componente se ejecuta normalmente.

El procesamiento de filtros de OCore confirma este flujo exacto.

---

# 8. Formato de retorno del filtro

Un filtro siempre debe devolver una matriz como:

```php
[
  'status' => 'ok' | 'error',
  'return' => '/login', // redirección opcional
  // propiedades personalizadas...
]
```

Por ejemplo:

```php
[
  'status' => 'ok',
  'id' => 123,
  'role' => 'admin'
]
```

---

# 9. Mejores prácticas

### Mantener los filtros sin estado

No deben depender del estado mutable global (excepto para leer la configuración o la sesión).

### Siempre devolver `"status" => "ok"` o `"error"`

OCore depende de este campo para decidir si continúa la solicitud.

### Usar filtros para autenticación/autorización

Los DTO no deben recibir credenciales directamente de los usuarios cuando se pueden inyectar de forma segura.

### Filtros de nombres con `XxxFilter`

Esto garantiza la normalización de nombres de clase de es OCore se comporta de forma predecible.

### Evite la lógica compleja

Mantenga los filtros pequeños; traslade la lógica de negocio a los servicios.

### Use `"return"` para redirigir a los usuarios

Útil para la protección del inicio de sesión o los flujos de incorporación.

---

#10. ¿Cuándo debería usar un filtro?

Use un filtro cuando:

- Una ruta requiere autenticación.
- Una ruta requiere la validación de una clave API o un token.
- Desea bloquear o redirigir a usuarios no autorizados _antes_ de ejecutar un componente.
- Desea precargar la información del usuario, inquilino o sesión.
- Varias rutas comparten las mismas comprobaciones de seguridad.

No utilice filtros para:

- Validación de modelos
- Formatear respuestas
- Crear estructuras de datos complejas
  (estas deben residir en DTO o servicios)

---

# 11. Flujo de solicitud completo, incluidos los filtros

    Solicitud del cliente
          ↓
    Enrutamiento → ruta encontrada
          ↓
    Los filtros se ejecutan (en orden)
          ↓
    Si algún filtro falla → 403 o redirección
          ↓
    Se crea ORequest (con los datos del filtro)
          ↓
    Component::run($dtoOrRequest)
          ↓
    Representación de la plantilla
          ↓
    Respuesta
