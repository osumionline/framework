# Servicios

Los servicios en **Osumi Framework** son clases reutilizables que encapsulan lógica de negocio, operaciones compartidas o funciones de utilidad utilizadas por componentes, módulos y tareas.

Los servicios ayudan a:

- Evitar lógica duplicada.
- Organizar el comportamiento del dominio.
- Centralizar interacciones con modelos o API externas.
- Mantener los componentes centrados en la orquestación y presentación.

---

## 1. ¿Qué es un servicio?

Un servicio extiende `OService`.

Puede utilizar facilidades del framework como logging, configuración y acceso a caché.

---

## 2. Creación de un servicio

Los servicios de la aplicación se almacenan en:

```text
src/Service/
```

Ejemplo:

```php
namespace Osumi\OsumiFramework\App\Service;

use Osumi\OsumiFramework\Core\OService;

class UserService extends OService {
	public function getUserById(int $id): ?User {
		return User::findOne([
			'id' => $id
		]);
	}
}
```

---

## 3. Inyección de un servicio en un componente

Los servicios deben inyectarse desde código ejecutable, como el constructor:

```php
class MyComponent extends OComponent {
	private ?UserService $us = null;

	public function __construct() {
		parent::__construct();

		$this->us = inject(
			UserService::class
		);
	}
}
```

---

## 4. Uso de un servicio en un componente

Un flujo habitual es:

1. Leer datos de la petición o del DTO, incluyendo cuando corresponda contexto de confianza publicado por Middlewares.
2. Delegar la lógica de negocio a un servicio.
3. Preparar la salida del componente.

```php
public function run(ORequest $req): void {
	$id = $req->getMiddlewareValue(
		'Login',
		'id'
	);

	if (!is_int($id)) {
		return;
	}

	$this->user = $this->us->getUserById(
		$id
	);
}
```

---

## 5. Ciclo de vida de un servicio

`OService` proporciona acceso a facilidades del framework como:

- logging
- configuración de la aplicación
- caché

---

## 6. Nombres y ubicación

Usa:

```text
src/Service/UserService.php
```

con nombres de clase como:

```text
UserService
OrderService
PaymentService
```

---

## 7. Mejores prácticas

- Mantén los servicios sin estado cuando sea práctico.
- Agrupa funcionalidad relacionada.
- Evita renderizado o salida directa.
- Mantén las responsabilidades transversales de petición/respuesta en Middlewares.
- Mantén la validación de entrada en DTOs.
- Deja que los componentes coordinen petición → servicio → modelo → plantilla.

---

## 8. Cuándo usar un servicio

Usa un servicio para lógica de negocio o dominio reutilizable.

No uses un servicio cuando:

- La lógica sea una responsabilidad transversal de petición/respuesta más adecuada para un Middleware.
- La lógica sea específicamente de presentación/renderizado.
- La lógica sea validación de entrada más adecuada para un DTO.

---

## 9. Resumen

Los servicios proporcionan lógica de negocio reutilizable y ayudan a mantener componentes, Middlewares y DTOs centrados en sus respectivas responsabilidades.
