# Diseños

En **Osumi Framework**, un **diseño** es un tipo especial de componente que envuelve la salida del componente de acción principal.

Los diseños se suelen usar para compartir la misma estructura HTML en múltiples rutas (por ejemplo: `<head>`, metadatos, encabezado/pie de página, inyección de scripts/estilos, etc.).

Un diseño se aplica **después** de que el componente de ruta se ejecute y renderice, y recibe la salida renderizada como su `body`.

---

## 1. Cómo funcionan los diseños (Flujo de renderizado)

Cuando se encuentra una ruta, Osumi Framework ejecuta esta secuencia:

1. Se resuelve la ruta (Enrutamiento).
2. Se ejecutan los filtros (si los hay).
3. Se instancia y renderiza el componente de ruta.
4. Si se define un diseño para la ruta, este se instancia y recibe:
    - `title`: título predeterminado de la configuración
    - `body`: la salida renderizada del componente de ruta
5. Se renderiza la plantilla de diseño, generando la respuesta final.

Esto significa:

- Tu **componente de acción** se centra en producir el **contenido de la página**.
- Tu **diseño** proporciona la estructura compartida y encapsula ese contenido.

---

## 2. Diseño predeterminado

Al crear un nuevo proyecto de Osumi Framework, se genera un diseño predeterminado.

### 2.1 Componente de Diseño Predeterminado

`DefaultLayoutComponent` es un componente muy simple que solo define las propiedades públicas utilizadas por su plantilla:

- `title`: título de la página
- `body`: contenido HTML del componente de acción

### 2.2 Plantilla de Diseño Predeterminado

La plantilla de diseño predeterminado contiene un esqueleto HTML estándar y utiliza dos marcadores de posición:

- `{{title}}` → se inserta en `<title>`
- `{{body}}` → se inserta en `<body>`

Esto convierte al diseño predeterminado en un contenedor genérico para la mayoría de las páginas renderizadas por el servidor.

---

## 3. Definición de un diseño en el enrutamiento (IMPORTANTE)

Puede asignar un diseño en el enrutamiento de dos maneras:

### 3.1 Grupo de diseños

Use `ORoute::layout()` para aplicar un diseño a varias rutas:

```php
use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\App\Layout\MainLayoutComponent;

ORoute::layout(MainLayoutComponent::class, function() {
  ORoute::get('/home', HomeComponent::class);
  ORoute::get('/contact', ContactComponent::class);
});
```

### 3.2 Grupo de Diseño + Prefijo

Usa `ORoute::group()` para combinar un prefijo de URL y un diseño:

```php
use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\App\Layout\AdminLayoutComponent;

ORoute::group('/admin', AdminLayoutComponent::class, function() {
  ORoute::get('/dashboard', DashboardComponent::class);
  ORoute::get('/settings', SettingsComponent::class);
});
```

> Esta es la forma recomendada de aplicar un diseño personalizado de forma consistente a un área de tu aplicación.

---

## 4. Inyección de CSS/JS

Los diseños también son el lugar donde Osumi Framework inyecta recursos CSS y JS.

Cuando la salida del diseño contiene una etiqueta `</head>`, el framework inserta automáticamente:

- CSS en línea (`<style>...</style>`) de los archivos configurados
- JS en línea (`<script>...</script>`) de los archivos configurados
- CSS externo (`<link ...>`) de la configuración `ext_css_list`
- JS externo (`<script src=...>`) de la configuración `ext_js_list`

Esto convierte al diseño en el punto natural donde se ensamblan los recursos globales del frontend.

---

## 5. Mejores prácticas

- Mantenga los diseños puramente estructurales (esqueleto HTML + interfaz de usuario compartida).
- No incluya lógica de negocio en los diseños.
- Utilice un diseño dedicado por sección si es necesario (por ejemplo, `MainLayout`, `AdminLayout`).
- Prefiera `ORoute::layout()` / `ORoute::group()` para mayor consistencia.

---

## 6. Resumen

- Los diseños envuelven la salida renderizada de los componentes de ruta.
- Los diseños reciben `title` y `body`.
- Se genera un diseño predeterminado en los nuevos proyectos.
- Puedes definir un **diseño personalizado en el enrutamiento** usando `ORoute::layout()` o `ORoute::group()`.
- Los diseños son donde se produce la inyección global de CSS/JS.
