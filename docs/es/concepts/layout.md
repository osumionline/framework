# Layouts

En **Osumi Framework**, un layout es un componente especial que envuelve la salida del componente principal de una ruta.

Los layouts se utilizan normalmente para compartir la misma estructura HTML entre varias rutas.

Un layout se aplica después del componente de la ruta y de la fase Middleware `afterRender`. Recibe la salida actual del componente como su `body`.

---

## 1. Flujo de renderizado

Para una ruta encontrada normalmente, el flujo relevante es:

```text
Enrutamiento
↓
Middlewares before
↓
Componente
↓
Middlewares afterRender
↓
Layout
↓
Middlewares afterResponse
↓
Respuesta HTTP
```

Esto significa:

- El componente de acción produce el contenido de la página.
- Los Middlewares `afterRender` pueden inspeccionar o sustituir ese contenido antes del layout.
- El layout envuelve el contenido resultante.
- Los Middlewares `afterResponse` se ejecutan después de producir el cuerpo final.

Si un Middleware `before` detiene la ejecución, se omiten el componente y el layout.

Si un Middleware `afterRender` detiene la ejecución, se omite el layout.

En ambos casos, `afterResponse` sigue ejecutándose antes de enviar la respuesta.

---

## 2. Layout predeterminado

Los nuevos proyectos incluyen un layout predeterminado.

### Componente del layout predeterminado

`DefaultLayoutComponent` expone:

- `title`
- `body`

### Plantilla del layout predeterminado

La plantilla predeterminada utiliza:

- `{{title}}`
- `{{body}}`

---

## 3. Definición de layouts en el enrutamiento

### Grupo de layout

```php
use Osumi\OsumiFramework\App\Layout\MainLayoutComponent;
use Osumi\OsumiFramework\Routing\ORoute;

ORoute::layout(
	MainLayoutComponent::class,
	static function (): void {
		ORoute::get('/home', HomeComponent::class);
		ORoute::get('/contact', ContactComponent::class);
	}
);
```

`ORoute::layout()` acepta un tercer argumento opcional con definiciones de Middlewares.

### Grupo de layout + prefijo

```php
ORoute::group(
	'/admin',
	AdminLayoutComponent::class,
	static function (): void {
		ORoute::get('/dashboard', DashboardComponent::class);
		ORoute::get('/settings', SettingsComponent::class);
	}
);
```

`ORoute::group()` acepta un cuarto argumento opcional con definiciones de Middlewares.

Consulta `/docs/es/concepts/middlewares.md`.

---

## 4. Inyección de CSS / JS

Los layouts son el punto donde Osumi Framework inyecta los recursos frontend configurados en documentos que contienen `</head>`.

---

## 5. Mejores prácticas

- Mantén los layouts centrados en la estructura.
- No incluyas lógica de negocio en los layouts.
- Usa layouts dedicados para distintas áreas de la aplicación cuando sea útil.
- Prefiere `ORoute::layout()` y `ORoute::group()` para mantener una configuración de rutas consistente.
- Usa Middlewares `afterRender` cuando haya que modificar la salida antes de envolverla con el layout.

---

## 6. Resumen

- Los layouts envuelven la salida del componente de ruta.
- `afterRender` se ejecuta antes del layout.
- `afterResponse` se ejecuta después de producir el cuerpo final.
- Un `stop` en `before` o `afterRender` evita el renderizado del layout.
- Los grupos de rutas pueden combinar layouts y Middlewares.
