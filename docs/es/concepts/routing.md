# Enrutamiento

El enrutamiento en Osumi Framework se gestiona mediante la clase `ORoute`. Esta clase asigna las solicitudes HTTP entrantes (URL) a componentes específicos que actúan como acciones.

Las rutas se definen normalmente en archivos PHP ubicados en el directorio `src/Routes/`. Puede crear varios archivos en esta carpeta para organizar las rutas de forma lógica (por ejemplo, un archivo por módulo).

Cuando un usuario accede a una URL, `ORoute` localiza la ruta, ejecuta filtros, instancia el componente y llama a `run()`, pasando un `DTO` definido por el usuario o un `ORequest` genérico.

---

## Definición de rutas

Para definir una ruta, utilice los métodos estáticos de `ORoute` correspondientes a los verbos HTTP: `get()`, `post()`, `put()` o `delete()`.

### Sintaxis básica

```php
use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\App\Module\Home\Index\IndexComponent;

ORoute::get('/', IndexComponent::class);

```

### Parámetros de ruta

- **URL (cadena)**: La ruta a la que se responde.
- **Componente (cadena)**: El FQCN (Nombre de clase completo) del componente que se ejecutará.
- **Filtros (array, opcional)**: Una lista de clases de filtro que se ejecutarán antes del componente.
- **Diseño (cadena, opcional)**: Un componente de diseño específico para esta ruta.

---

## Filtros

Los filtros son clases que se ejecutan antes del componente principal. Se utilizan comúnmente para la autenticación (comprobación de tokens), el registro o la validación de solicitudes.

Documentación: /docs/es/concepts/filters.md

```php
use Osumi\OsumiFramework\App\Filter\LoginFilter;
use Osumi\OsumiFramework\App\Module\User\Profile\ProfileComponent;

ORoute::post('/profile', ProfileComponent::class, [LoginFilter::class]);

```

---

## Agrupación de rutas

Osumi Framework ofrece tres maneras de agrupar rutas con características comunes:

### 1. Prefijos

Se utilizan cuando varias rutas comparten la misma URL de inicio (por ejemplo, una API).

```php
ORoute::prefix('/api', function() {
  ORoute::post('/login', LoginComponent::class);
  ORoute::post('/register', RegisterComponent::class);
});

```

### 2. Diseños

Se utiliza cuando varias rutas comparten la misma estructura visual (encabezado, pie de página, etc.).

```php
ORoute::layout(MainLayoutComponent::class, function() {
  ORoute::get('/home', HomeComponent::class);
  ORoute::get('/contact', ContactComponent::class);
});

```

### 3. Grupos (Prefijo + Diseño)

Combina la asignación de prefijos y diseños en un solo bloque.

```php
ORoute::group('/admin', AdminLayoutComponent::class, function() {
  ORoute::get('/dashboard', DashboardComponent::class);
  ORoute::get('/settings', SettingsComponent::class);
});

```

---

## Vistas estáticas

Si necesita servir un archivo estático o una plantilla simple sin la lógica de un componente de acción completo, use `ORoute::view()`.

```php
ORoute::view('/about-us', 'about-us.html');

```

## Parámetros en las rutas

Se pueden definir URLs con parámetros usando la sintaxis `:name`.

```php
ORoute::get('/user/:id', UserComponent::class);
ORoute::get('/location/:name', LocationComponent::class);
```

El método `run(ORequest $req)` del componente puede acceder a ese parámetro mediante métodos como `getParamInt('id')` o `getParamString('name')`.

---

## Resumen de los métodos de `ORoute`

| Método     | Descripción                                                       |
| ---------- | ----------------------------------------------------------------- |
| `get()`    | Registra una ruta GET.                                            |
| `post()`   | Registra una ruta POST.                                           |
| `put()`    | Registra una ruta PUT.                                            |
| `delete()` | Registra una ruta DELETE.                                         |
| `view()`   | Registra una ruta que renderiza un archivo estático directamente. |
| `prefix()` | Agrupa las rutas bajo un prefijo de URL común.                    |
| `layout()` | Agrupa las rutas bajo un componente de diseño común.              |
| `group()`  | Agrupa rutas con un prefijo y un diseño.                          |

---

## Mejores prácticas

- **Organizar por archivo**: Crea archivos diferentes en `src/Routes/` para cada módulo o área funcional de tu aplicación.
- **Usar filtros**: Mantén tus componentes limpios delegando la lógica de autenticación y validación a los filtros.
- **Constantes de clase**: Usa siempre la notación `::class` para componentes y filtros para aprovechar el autocompletado del IDE y el análisis estático.
