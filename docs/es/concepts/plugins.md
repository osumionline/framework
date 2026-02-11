# Plugins

Osumi Framework está diseñado para ser ligero. Se pueden añadir funcionalidades adicionales mediante plugins independientes. Estos plugins están disponibles en Packagist y se pueden instalar mediante Composer.

---

## Instalación

Para instalar un plugin, utilice el comando estándar de Composer desde la raíz del proyecto:

```bash
composer require osumionline/plugin-{plugin_name}

```

Una vez instalado, las clases del plugin estarán disponibles automáticamente a través del autocargador del framework en el espacio de nombres `Osumi\OsumiFramework\Plugins`.

---

## Plugins disponibles

| Plugin         | Clase      | Descripción                                                                               |
| -------------- | ---------- | ----------------------------------------------------------------------------------------- |
| **browser**    | OBrowser   | Recuperar datos del navegador del usuario (plataforma, tipo de navegador, versión, etc.). |
| **crypt**      | OCrypt     | Cifrado y descifrado simétrico de cadenas.                                                |
| **email**      | OEmail     | Utilidad sencilla para enviar correos electrónicos.                                       |
| **email_smtp** | OEmailSMTP | Envío avanzado de correos electrónicos mediante SMTP.                                     |
| **file**       | OFile      | Manipulación del sistema de archivos (copiar, mover, eliminar, etc.).                     |
| **ftp**        | OFTP       | Cliente FTP para gestionar archivos de servidores remotos.                                |
| **image**      | OImage     | Procesamiento de imágenes (redimensionar, convertir formato, crear desde cero).           |
| **instagram**  | OInstagram | Integración con la API de Instagram.                                                      |
| **paypal**     | OPaypal    | Integración con la pasarela de pagos de PayPal.                                           |
| **pdf**        | OPDF       | Generación de documentos PDF.                                                             |
| **ticketbai**  | OTicketBai | Integración con TicketBaiWS (requisitos fiscales).                                        |
| **token**      | OToken     | Gestión y validación de JWT (JSON Web Token).                                             |
| **websocket**  | OWebSocket | Utilidades para crear y gestionar un servidor WebSocket.                                  |

---

## Ejemplo práctico: Uso del plugin Token

El plugin `token` se usa ampliamente para proteger las API. A continuación, se explica cómo usar la clase `OToken` dentro de un componente para gestionar un proceso de inicio de sesión.

### Generación de un token

En este ejemplo, validamos un usuario y generamos un JWT que caduca en 24 horas.

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Api\Login;

use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\App\Model\User;
use Osumi\OsumiFramework\Plugins\OToken;

class LoginComponent extends OComponent {
  public string $token = '';

  public function run(array $data): void {
    $u = User::findOne(['name' => $data['name']]);

    if (!is_null($u) && password_verify($data['pass'], $u->pass)) {
      // Inicializar OToken con una clave secreta de la configuración
      $tk = new OToken($this->getConfig()->getExtra('secret'));

      // Añadir notificaciones/parámetros personalizados
      $tk->addParam('id', $u->id);
      $tk->addParam('name', $u->name);

      // Establecer la fecha de expiración (hora actual + 24 h)
      $tk->setEXP(time() + (24 * 60 * 60));

      // Generar la cadena final
      $this->token = $tk->getToken();
    }
  }
}

```

---

## Mejores prácticas

- **Configuración**: Muchos plugins requieren datos confidenciales (como claves API o contraseñas SMTP). Guárdalos en `src/Config/Config.json`, en la sección `extra`, y accede a ellos mediante `$this->getConfig()->getExtra('key')`.
- **Gestión de errores**: Al trabajar con servicios externos (FTP, PayPal, Instagram), siempre encierra las llamadas a tus plugins en bloques `try-catch` para gestionar posibles problemas de conexión o errores de API.
- **Independencia**: Instala solo los plugins que realmente necesitas. Esto mantiene la carpeta `vendor` optimizada y tu aplicación más rápida.
