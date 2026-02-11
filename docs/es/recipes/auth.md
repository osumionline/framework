# Autenticación (Auth) — Recetas y Buenas Prácticas

La autenticación en **Osumi Framework** se implementa típicamente mediante:

- **Un endpoint de inicio de sesión** que valida las credenciales del usuario y emite un **token**
- **Un filtro** (p. ej., `LoginFilter`) que valida el token en cada ruta protegida
- **DTO** para procesar de forma segura los datos de autenticación entrantes
- **Servicios** para mantener la lógica de autenticación reutilizable y limpia
- **Rutas** configuradas para aplicar filtros de autenticación antes de ejecutar los componentes

Este documento proporciona recetas prácticas para implementar un flujo de trabajo de autenticación completo.

---

# 1. Protección de rutas mediante filtros

El método más común para proteger los puntos finales es agregar un filtro a la definición de la ruta.

Según su sistema de enrutamiento, los filtros se pueden especificar de la siguiente manera:

```php
ORoute::post('/profile', ProfileComponent::class, [LoginFilter::class]);
```

Al acceder a la ruta:

1. El enrutador identifica el endpoint.
2. Antes de ejecutar el componente, se ejecuta la cadena de filtros.
3. Si algún filtro devuelve `"status" !== "ok"`, la solicitud **nunca llega al componente**, devolviendo **403 Forbidden** o redirigiendo si `"return"` está configurado.

Esto garantiza que solo los usuarios autenticados accedan a la lógica protegida.

--

# 2. Creación del filtro de inicio de sesión

Un filtro se ve así:

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

Este filtro:

- Lee la cabecera `Authorization`
- Valida un token
- Si es válido, devuelve `"status" => "ok"` y el ID del usuario autenticado
- Si no es válido, devuelve `"error"` y detiene la solicitud

Los valores derivados del token (como `id`) pueden ser utilizados posteriormente por componentes o DTO.

---

# 3. Creación del endpoint de inicio de sesión (emisión de tokens):

1. Recibe las credenciales mediante un DTO
2. Las valida mediante un servicio
3. Genera un token
4. Devuelve el token al cliente
5. El cliente almacena el token y lo usa en el encabezadola cabecera "Authorization"

### Ejemplo de estructura

**DTO para inicio de sesión:**

```php
class LoginDTO extends ODTO {
  #[ODTOField(required: true)]
  public ?string $email = null;

  #[ODTOField(required: true)]
  public ?string $password = null;
}
```

**AuthService gestiona la lógica:**

```php
class AuthService extends OService {
  public function login(string $email, string $password): ?array {
    $user = User::findOne(['email' => $email]);
    if (!$user || !password_verify($password, $user->password)) {
      return null;
    }
    $token = new OToken($this->getConfig()->getExtra('secret'));
    $token->addParam('id', $user->id);
    return ['token' => $token->getToken()];
  }
}
```

**Componente de inicio de sesión:**

```php
class LoginComponent extends OComponent {
  private ?AuthService $auth = null;
  public ?string $token = null;
  public string $status = 'error';

  public function __construct() {
    parent::__construct();
    $this->auth = inject(AuthService::class);
  }

  public function run(LoginDTO $dto): void {
    if (!$dto->isValid()) {
      return;
    }

    $data = $this->auth->login($dto->email, $dto->password);

    if ($data) {
      $this->status = 'ok';
      $this->token = $data['token'];
    }
  }
}
```

El cliente ahora incluye el token en todas las solicitudes posteriores:

    Autorización: <token>

---

# 4. Uso de la salida de filtros en componentes

Una vez que los filtros pasan, el objeto de solicitud contiene los resultados:

```php
$filter = $req->getFilter('Login');
```

Normalmente, haría lo siguiente:

```php
$userId = $filter['id']; // usuario autenticado
```

Después, puede pasar el ID a los servicios, cargar modelos y ejecutar la lógica de negocio de forma segura.

---

# 5. Uso de datos de filtros dentro de DTO

Los DTO pueden recibir automáticamente valores de los filtros:

```php
#[ODTOField(filter: 'Login', filterProperty: 'id')]
public ?int $idUser = null;
```

Esto significa:

- Los usuarios no pueden suplantar su identidad.
- Los DTO reciben el ID del usuario autenticado de forma segura.
- Los componentes no necesitan leer manualmente los datos de los filtros.

Esto simplifica enormemente los endpoints que dependen de la autenticación.

---

# 6. Receta: Creación de un endpoint protegido

Ejemplo: “Obtener mis cines”

### Ruta

```php
ORoute::get('/my-cinemas', GetCinemasComponent::class, [LoginFilter::class]);
```

### Componente

```php
class GetCinemasComponent extends OComponent {
  private ?CinemaService $cs = null;
  public string $status = 'ok';
  public ?CinemaListComponent $list = null;

  public function __construct() {
    parent::__construct();
    $this->cs = inject(CinemaService::class);
    $this->list = new CinemaListComponent();
  }

  public function run(ORequest $req): void {
    $filter = $req->getFilter('Login');

    if (!$filter || !array_key_exists('id', $filter)) {
      $this->status = 'error';
      return;
    }

    $this->list->list = $this->cs->getCinemas($filter['id']);
  }
}
```

---

# 7. Receta: Aplicación de permisos

Puedes ampliar tu filtro para incluir información de rol/permiso del token:

```php
$ret['role'] = $tk->getParam('role');
```

Luego, en los componentes:

```php
$filter = $req->getFilter('Login');
if ($filter['role'] !== 'admin') {
  $this->status = 'forbidden';
  return;
}
```

---

# 8. Receta: Cerrar sesión

Dado que su sistema de autenticación se basa en tokens y no tiene estado:

- "Cerrar sesión" consiste simplemente en eliminar el token del lado del cliente.
- Opcionalmente, puede implementar una **lista negra de tokens** mediante la caché:
    - Marcar el token como inválido en `getCacheContainer()`
    - Filtrar los tokens en la lista negra.

---

# 9. Buenas prácticas

- **Usar DTO** para las solicitudes de inicio de sesión
- **Nunca confíe en los ID de usuario proporcionados por el cliente** siempre derive los ID de los filtros
- **Mantenga los filtros pequeños** (solo validación)
- **Incorpore la lógica de negocio en los servicios**
- **Use secretos seguros** para los tokens (almacene en la configuración)
- **Divida la lógica de forma clara**:
    - Filtros → autenticación/verificación
    - DTO → validación de entrada
    - Servicios → lógica
    - Componentes → orquestación y respuesta

---

# 10. Resumen

Un flujo de trabajo de autenticación completo en Osumi Framework suele incluir:

1. **Endpoint de inicio de sesión** que emite tokens
2. **Filtro de inicio de sesión** que valida tokens para rutas protegidas
3. **DTO** que captura y valida la entrada
4. **Servicios** que ejecutan la lógica de autenticación
5. **Enrutamiento** que aplica filtros antes que los componentes
6. **Propagación segura** de la información del usuario autenticado mediante filtros y DTO

Esta arquitectura garantiza:

- Separación clara de responsabilidades
- Fácil reutilización entre puntos finales
- Fuertes garantías de seguridad
- Canal de solicitudes simple y predecible
