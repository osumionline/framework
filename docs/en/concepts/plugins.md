# Plugins

Osumi Framework is designed to be lightweight. Additional functionalities can be added through independent plugins. These plugins are available on Packagist and can be installed via Composer.

---

## Installation

To install a plugin, use the standard Composer command from your project root:

```bash
composer require osumionline/plugin-{plugin_name}

```

Once installed, the plugin's classes are automatically available through the framework's autoloader under the `Osumi\OsumiFramework\Plugins` namespace.

---

## Available Plugins

| Plugin         | Class      | Description                                                         |
| -------------- | ---------- | ------------------------------------------------------------------- |
| **browser**    | OBrowser   | Retrieve user browser data (platform, browser type, version, etc.). |
| **crypt**      | OCrypt     | Symmetric encryption and decryption of strings.                     |
| **email**      | OEmail     | Simple email sending utility.                                       |
| **email_smtp** | OEmailSMTP | Advanced email sending via SMTP.                                    |
| **file**       | OFile      | File system manipulation (copy, move, delete, etc.).                |
| **ftp**        | OFTP       | FTP client to manage remote server files.                           |
| **image**      | OImage     | Image processing (resize, convert format, create from scratch).     |
| **instagram**  | OInstagram | Integration with Instagram's API.                                   |
| **paypal**     | OPaypal    | Integration with PayPal's payment gateway.                          |
| **pdf**        | OPDF       | PDF document generation.                                            |
| **ticketbai**  | OTicketBai | Integration with TicketBaiWS (fiscal requirements).                 |
| **token**      | OToken     | JWT (JSON Web Token) management and validation.                     |
| **websocket**  | OWebSocket | Utilities to create and manage a WebSocket server.                  |

---

## Practical Example: Using the Token Plugin

The `token` plugin is widely used for securing APIs. Here is how you can use the `OToken` class within a Component to handle a login process.

### Generating a Token

In this example, we validate a user and generate a JWT that expires in 24 hours.

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
      // Initialize OToken with a secret key from config
      $tk = new OToken($this->getConfig()->getExtra('secret'));

      // Add custom claims/parameters
      $tk->addParam('id',   $u->id);
      $tk->addParam('name', $u->name);

      // Set expiration time (current time + 24h)
      $tk->setEXP(time() + (24 * 60 * 60));

      // Generate the final string
      $this->token = $tk->getToken();
    }
  }
}

```

---

## Best Practices

- **Configuration**: Many plugins require sensitive data (like API keys or SMTP passwords). Store these in your `src/Config/Config.json` under the `extra` section and access them via `$this->getConfig()->getExtra('key')`.
- **Error Handling**: When working with external services (FTP, PayPal, Instagram), always wrap your plugin calls in `try-catch` blocks to handle potential connection issues or API errors.
- **Independence**: Only install the plugins you actually need. This keeps your `vendor` folder lean and your application faster.
