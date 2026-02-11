# Servicios

Los servicios en **Osumi Framework** son clases reutilizables que encapsulan la lógica de negocio, las operaciones compartidas o las funciones de utilidad que se utilizan en componentes, módulos y tareas.
Se comportan de forma similar a los servicios de Angular: ligeros, componibles y diseñados para mantener los componentes limpios y enfocados.

Los servicios te ayudan a:

- Evitar la duplicación de lógica entre componentes
- Organizar comportamientos específicos del dominio
- Centralizar las interacciones con modelos o API externas
- Estructurar tu aplicación según los principios de la arquitectura limpia

---

# 1. ¿Qué es un servicio?

Un servicio es una clase PHP que **extiende `OService`**.
La clase base proporciona:

- Un registrador (`OLog`)
- Acceso a la configuración de la aplicación
- Acceso al contenedor de caché global

La clase de servicio puede definir cualquier método público que necesite tu aplicación.

---

# 2. Creación de un servicio

Un servicio debe residir en:

    src/App/Service/

Estructura típica:

```php
namespace Osumi\OsumiFramework\App\Service;

use Osumi\OsumiFramework\Core\OService;

class UserService extends OService {
  public function getUserById(int $id): ?User {
    return User::findOne(['id' => $id]);
  }
}
```

Los servicios suelen:

- Implementan lógica reutilizable
- Consultan o manipulan modelos
- Coordinan operaciones de varios pasos
- Opcionalmente, utilizan otros servicios

---

# 3. Inyección de un servicio en un componente

No se puede inyectar un servicio al declarar la propiedad en una clase. **Esta es una limitación del lenguaje PHP:** PHP **no** permite llamar a funciones (como `inject()`) dentro de las declaraciones de propiedades.

Por ejemplo, esto es **inválido** en PHP:

```php
private UserService $us = inject(UserService::class); // No permitido en PHP
```

Por lo tanto, los servicios deben inyectarse dentro del constructor:

```php
class MyComponent extends OComponent {
  private ?UserService $us = null;

  public function __construct() {
    parent::__construct();
    $this->us = inject(UserService::class); // Correcto
  }
}
```

Esto garantiza que el servicio esté disponible para cuando se ejecute `run()`.

---

# 4. Uso del servicio en un componente

Una vez inyectado, se puede acceder a los métodos del servicio con normalidad:

```php
public function run(ORequest $req): void {
  $user = $this->us->getUserById(3);
  $this->user = $user;
}
```

Un patrón típico es:

1. Extraer datos de la solicitud (posiblemente mediante filtros o DTO)
2. Delegar la lógica de negocio al servicio
3. Preparar la salida final del componente

Los componentes son pequeños y declarativos; los servicios se encargan del trabajo pesado.

---

# 5. Ciclo de vida de un servicio

`OService` gestiona parte de la inicialización automáticamente:

### Inicialización del registrador

Cada servicio obtiene su propio registrador para escribir información de depuración.

### Acceso a la configuración

`$this->getConfig()` proporciona la configuración global de la aplicación.

### Acceso al contenedor de caché

`$this->getCacheContainer()` proporciona el subsistema de caché global.

Estos asistentes garantizan que los servicios sean potentes y estén desacoplados del estado global.

---

# 6. Dónde ubicar los servicios

Siga esta estructura:

    src/
      App/
        Service/
          CinemaService.php
          UserService.php
          NotificationService.php

Convenciones de nomenclatura:

- Clase: `XxxService`
- Archivo: `XxxService.php`

---

# 7. Ejemplo: Servicio enfocado en el dominio

```php
class CinemaService extends OService {
  public function getCinemas(int $id_user): array {
    return Cinema::where(['id_user' => $id_user]);
  }

  public function deleteCinema(Cinema $cinema): void {
    $movies = $cinema->getMovies();
    foreach ($movies as $movie) {
      $movie->deleteFull();
    }
    $cinema->delete();
  }
}
```

Este servicio:

- Recupera datos
- Realiza operaciones de eliminación de varios pasos
- Encapsula las reglas del dominio

Al centralizar esta lógica, todos los componentes que necesitan operaciones de cinema pueden reutilizarla.

---

# 8. Mejores prácticas

- **Mantenga los servicios sin estado** siempre que sea posible
  Deben comportarse como simples ayudantes.

- **Agrupe las funciones relacionadas**

Evite clases de servicio multipropósito de gran tamaño.

- **Use otros servicios cuando sea necesario**

Es válido crear jerarquías de servicios (por ejemplo, `OrderService` usando `PaymentService`).

- **Evite la representación o la salida en los servicios**

Los servicios no deben repetir ni devolver HTML; esto pertenece a los componentes.

- **Usa el registrador para depurar**
  `$this->getLog()->debug("...")` es extremadamente útil.

- **Deja que los componentes organicen**
  Los componentes coordinan solicitud → servicio → modelos → plantilla.

---

# 9. ¿Cuándo deberías usar un servicio?

Usa un servicio cuando:

- Varios componentes comparten la misma lógica
- La lógica implica interacciones de modelos no triviales
- Necesitas operaciones reutilizables
- Quieres separar la lógica del dominio de la lógica de presentación
- Necesitas mantener los componentes pequeños, limpios y enfocados

**No** uses un servicio cuando:

- La lógica es puramente de filtrado de solicitudes (usa filtros)
- La lógica es específica de la renderización (usa componentes)
- La lógica se relaciona con la validación de los datos de entrada (usa DTO)

---

# 10. Resumen

Los servicios son uno de los componentes clave de Osumi Framework:

- Promueven una clara separación de preocupaciones
- Reducen la duplicación
- Centralizan la lógica de negocio
- Se integran perfectamente con los componentes mediante la inyección de dependencias
- Facilitan la composición (servicios que utilizan servicios)

El uso eficaz de los servicios da como resultado una arquitectura de aplicaciones más mantenible, escalable y organizada.
