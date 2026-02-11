# Pluginak

Osumi Framework arina izateko diseinatuta dago. Funtzionalitate gehigarriak plugin independenteen bidez gehi daitezke. Plugin hauek Packagist-en daude eskuragarri eta Composer bidez instala daitezke.

---

## Instalazioa

Plugin bat instalatzeko, erabili zure proiektuaren erroko Composer komando estandarra:

```bash
composer require osumionline/plugin-{plugin_name}

```

Instalatu ondoren, pluginaren klaseak automatikoki eskuragarri daude framework-aren autokargatzailearen bidez `Osumi\OsumiFramework\Plugins` izen-espazioaren azpian.

---

## Eskuragarri dauden pluginak

| Plugin            | Klasea     | Deskribapena                                                                                            |
| ----------------- | ---------- | ------------------------------------------------------------------------------------------------------- |
| **nabigatzailea** | OBrowser   | Erabiltzailearen nabigatzailearen datuak berreskuratu (plataforma, nabigatzaile mota, bertsioa, etab.). |
| **crypt**         | OCrypt     | Kateen enkriptazio eta deskodetze simetrikoa.                                                           |
| **email**         | OEmail     | Mezu elektronikoak bidaltzeko utilitate sinplea.                                                        |
| **email_smtp**    | OEmailSMTP | SMTP bidezko mezu elektroniko aurreratuak bidaltzeko.                                                   |
| **file**          | OFile      | Fitxategi sistemaren manipulazioa (kopiatu, mugitu, ezabatu, etab.).                                    |
| **ftp**           | OFTP       | Urruneko zerbitzari fitxategiak kudeatzeko FTP bezeroa.                                                 |
| **image**         | OImage     | Irudien prozesamendua (tamaina aldatu, formatua bihurtu, hutsetik sortu).                               |
| **instagram**     | OInstagram | Instagramen APIarekin integrazioa.                                                                      |
| **paypal**        | OPaypal    | PayPalen ordainketa-pasabidearekin integrazioa.                                                         |
| **pdf**           | OPDF       | PDF dokumentuen sorrera.                                                                                |
| **ticketbai**     | OTicketBai | TicketBaiWS-ekin integrazioa (fiskaltasun-eskakizunak).                                                 |
| **token**         | OToken     | JWT (JSON Web Token) kudeaketa eta baliozkotzea.                                                        |
| **websocket**     | OWebSocket | WebSocket zerbitzari bat sortu eta kudeatzeko utilitateak.                                              |

---

## Adibide praktikoa: Token plugina erabiltzea

`token` plugina oso erabilia da APIak ziurtatzeko. Hona hemen nola erabil dezakezun `OToken` klasea osagai baten barruan saioa hasteko prozesu bat kudeatzeko.

### Token bat sortzea

Adibide honetan, erabiltzaile bat baliozkotzen dugu eta 24 ordutan iraungitzen den JWT bat sortzen dugu.

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
      // OToken konfigurazioko gako sekretu batekin hasieratu
      $tk = new OToken($this->getConfig()->getExtra('secret'));

      // Erreklamazio/parametro pertsonalizatuak gehitu
      $tk->addParam('id', $u->id);
      $tk->addParam('name', $u->name);

      // Iraungitze-denbora ezarri (uneko ordua + 24h)
      $tk->setEXP(time() + (24 * 60 * 60));

      // Azken katea sortu
      $this->token = $tk->getToken();
    }
  }
}

```

---

## Praktika Onak

- **Konfigurazioa**: Plugin askok datu sentikorrak behar dituzte (API gakoak edo SMTP pasahitzak bezala). Gorde hauek zure `src/Config/Config.json` fitxategian `extra` atalean eta sartu `$this->getConfig()->getExtra('key')` bidez.
- **Erroreen Kudeaketa**: Kanpoko zerbitzuekin lan egitean (FTP, PayPal, Instagram), bildu beti zure plugin deiak `try-catch` blokeetan, konexio arazo edo API errore potentzialak kudeatzeko.
- **Independentzia**: Benetan behar dituzun pluginak bakarrik instalatu. Horrela, zure `vendor` karpeta arinagoa eta zure aplikazioa azkarragoa izango da.
