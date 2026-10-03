# Layouts

En **Osumi Framework**, un layout es un componente especial que envuelve la salida del componente principal de una ruta.

Los layouts se utilizan normalmente para compartir la misma estructura HTML entre varias rutas.

Un layout se aplica después del componente de la ruta y de la fase Middleware `afterRender`. Recibe la salida actual del componente como su `body`.

Las respuestas `OStreamResponse` son una excepción: no tienen un cuerpo materializado que pueda envolverse y **no utilizan layout**.

---

## 1. Flujo de renderizado

Para una respuesta tradicional:

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

Para una respuesta streaming:

```text
Enrutamiento
↓
Middlewares before
↓
Componente → OStreamResponse
↓
Middlewares afterRender
↓
Middlewares afterResponse
↓
Emisión del stream
```

Esto significa:

- `afterRender` se ejecuta antes del layout en respuestas tradicionales.
- El layout envuelve el contenido resultante.
- `afterResponse` se ejecuta después de producir el cuerpo final tradicional.
- En streaming, el layout se omite y `afterResponse` termina antes de enviar el primer byte.

Si `before` detiene la ejecución, se omiten el componente y el layout.

Si `afterRender` detiene la ejecución, se omite el layout.

---

## 2. Layout predeterminado

Los nuevos proyectos incluyen un layout predeterminado. `DefaultLayoutComponent` expone normalmente `title` y `body`.

---

## 3. Definición de layouts en el enrutamiento

```php
ORoute::layout(
	MainLayoutComponent::class,
	static function (): void {
		ORoute::get('/home', HomeComponent::class);
		ORoute::get('/contact', ContactComponent::class);
	}
);
```

`ORoute::layout()` acepta un tercer argumento opcional con Middlewares.

```php
ORoute::group(
	'/admin',
	AdminLayoutComponent::class,
	static function (): void {
		ORoute::get('/dashboard', DashboardComponent::class);
	}
);
```

`ORoute::group()` acepta un cuarto argumento opcional con Middlewares.

Aunque una ruta esté dentro de un grupo con layout, si su componente devuelve `OStreamResponse` ese layout se omite.

Consulta `/docs/es/concepts/middlewares.md`.

---

## 4. Inyección de CSS / JS

Los layouts son el punto donde Osumi Framework inyecta los recursos frontend configurados en documentos que contienen `</head>`.

Esta inyección no se aplica a respuestas streaming.

---

## 5. Mejores prácticas

- Mantén los layouts centrados en estructura y presentación.
- No incluyas lógica de negocio en los layouts.
- Usa layouts dedicados para distintas áreas cuando sea útil.
- No dependas de un layout para cabeceras necesarias en una descarga streaming; defínelas en `OStreamResponse` o en Middlewares.

---

## 6. Resumen

- Los layouts envuelven respuestas tradicionales basadas en componentes.
- `afterRender` se ejecuta antes del layout.
- `afterResponse` se ejecuta después del cuerpo final tradicional.
- Un `stop` en `before` o `afterRender` evita el layout.
- `OStreamResponse` omite siempre el layout.
