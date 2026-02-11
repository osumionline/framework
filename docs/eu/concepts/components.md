# Osagaiak

Osumi Framework-eko osagaiak txantiloi bat errendatzen duten kode zati txiki eta berrerabilgarriak dira. Osagai bat honako hauek osatzen dute:

- `OComponent` hedatzen duen PHP klase bat.
- Txantiloi fitxategi bat (php/html/json/xml erabileraren arabera).

Osagai instantzia bat sortzen da, propietateak esleitzen zaizkio eta gero osagaia errendatzen da, normalean `render()` bidez edo objektua kate batera bihurtuz.

---

## Oinarrizko osagaien egitura

### Osagai Klasea

Osagai klase fitxategi baten adibidea (`LostPasswordComponent.php`):

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Email\LostPassword;

use Osumi\OsumiFramework\Core\OComponent;

class LostPasswordComponent extends OComponent {
  /**
  * Propietate publikoak automatikoki agertzen dira txantiloian.
  */
  public ?string $token = null;
}

```

### Txantiloi Fitxategia

Txantiloi baten adibidea (`LostPasswordTemplate.php`):

```php
<div>
  Token: {{ token }}
</div>

```

---

## Ezaugarri Aurreratuak

### Eduki Mota Goiburu Automatikoak

Osagai bat URL baten ekintza nagusi gisa erabiltzen denean, esparruak automatikoki bidaltzen du dagokion `Content-Type` goiburua txantiloiaren fitxategi luzapenaren arabera:

- `.json`: `Content-type: application/json` bidaltzen du.
- `.xml`: `Content-type: application/xml` bidaltzen du.
- `.html` / `.php`: `Content-type: text/html` bidaltzen du.

### Osagaien Habiaratzea

Osagaiak kateatu edo habiaratu daitezke. Osagai handiago batek osagai txikiagoak sartu eta errendatu ditzake bere logika edo txantiloian berrerabilgarritasuna sustatzeko.

Seme osagai klase fitxategi baten adibidea (`ChildComponent.php`):

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Child;

use Osumi\OsumiFramework\Core\OComponent;

class ChildComponent extends OComponent {
  public ?string $name = null;
}

```

### Txantiloi Fitxategia

Txantiloi baten adibidea (`ChildTemplate.php`):

```php
<div>
  Izena: {{ name }}
</div>

```

Seme osagai bat erabiliz aita osagai klase fitxategi baten adibidea (`FatherComponent.php`):

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Father;

use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\App\Component\Child\ChildComponent;

class FatherComponent extends OComponent {
  public ?ChildComponent $child = null;

  public function run(): void {
    $this->child = new ChildComponent();
    $this->child->name = 'Semearen Izena';
  }
}

```

### Txantiloi Fitxategia

Txantiloi baten adibidea (`FatherTemplate.php`):

```php
<div>
  Semea: {{ child }}
</div>

```

Emaitza hau izango litzateke:

```php
Semea: Izena: Semearen Izena
```

### Txantiloiaren Sintaxia eta Sarbidea

Txantiloiek osagaiaren propietate publikoetara modu ezberdinean sartzen dira fitxategiaren luzapenaren arabera:

1. **PHP Txantiloiak (`.php`)**:

- PHP kode natiboa exekutatu dezakete.
- Propietate publikoetara aldagai estandar gisa sartzen dira (adibidez, `$token`).

2. **Txantiloi Estatikoak/Egituratuak (`.html`, `.json`, `.xml`)**:

- Erabili kortxete bikoitzen notazioa propietate publikoak ateratzeko: `{{ aldagai_izena }}`.

---

## `run()` metodoa (Hautazkoa)

Osagai batek `run()` metodo bat defini dezake aukeran. Baldin badago, automatikoki exekutatzen da `render()` prozesuaren hasieran, txantiloia prozesatu aurretik datuak prestatzeko.

Osagaia ekintza gisa erabiltzen denean (ibilbide aktibatu baten ondorioz errendatutako osagaia), `run()` funtzioak eskaeren datuak jaso ditzake:

- `run()` funtzioak DTO bat badu, eskaerako datuak erabiliko dira bere eremuak betetzeko.
- Bestela, `ORequest` objektu generiko bat pasatuko zaio.

`ORequest` klaseak metodoak ditu pasatako datuak lortzeko, hala nola, formularioen balioak edo URL bidez pasatako parametroak:

- **`getParamString('izena')`**: ibilbideari pasatako 'izena' eremuaren balioa kate gisa itzultzen du (nuloa ez badago).
- **`getParamInt('izena')`**: ibilbideari pasatako 'izena' eremuaren balioa zenbaki oso gisa itzultzen du (nuloa ez badago).
- **`getParamFloat('izena')`**: ibilbideari pasatako 'izena' eremuaren balioa itzultzen du float gisa (null ez badago).
- **`getParamBool('izena')`**: ibilbideari pasatako 'izena' eremuaren balioa itzultzen du boolear gisa (null ez badago).

Ibilbide batek iragazki bat definituta badu, `ORequest` klaseak haien exekuzioaren emaitza atzitzeko moduak ere eskaintzen ditu:

```php
  public function run(ORequest $req): void {
    $login_filter = $req->getFilter('login'); // LoginFilter fitxategitik itzulitako emaitza atzituko luke
    $filters = $req->getFilters(); // Aplikatutako iragazki bakoitzaren itzulitako emaitza asoziazio-array gisa atzituko luke ['login' => [...]]
  }
```

**Adibidea:**

```php
class BooksComponent extends OComponent {
  public array $books = [];

  public function run(): void {
    $this->books = ['A Liburua', 'B Liburua'];
  }
}

```

```php
class GetBookComponent extends OComponent {
  public ?Book $book = null;

  public function run(ORequest $req): void {
    $id_book = $req->getParamInt('id');
    $this->book = Book::findOne(['id' => $id_book]);
  }
}

```

## Aukera globaletara sartzea

Osagaiek metodoak dituzte aplikazioaren konfigurazioa, erregistroak edo saio-datuak bezalako aukera globaletara sartzeko:

- **`getConfig()`**: `OConfig` globala itzultzen du bideak edo erabiltzaileak definitutako balioak (sekretuak, helbide elektronikoak...) irakurtzeko
    - Dokumentazioa: docs/eu/concepts/config.md
- **`getLog()`**: Osagaiko `OLog` instantzia itzultzen du. Erabiltzaileak informazioa erregistratu dezake `debug`, `info` edo `error` bezalako metodoak erabiliz.
    - Dokumentazioa: docs/eu/concepts/log.md
- **`getSession()`**: `OSession` instantzia itzultzen du, $\_SESSION parametroetara sartzeko erabil daitekeena.

---

## Izendapen-konbentzioak

Koherentzia mantentzeko, jarraitu izendapen-eredu hauek:

| Fitxategi mota    | Konbentzioa         | Adibidea            |
| ----------------- | ------------------- | ------------------- |
| **Osagai-klasea** | `XxxComponent.php`  | `UserComponent.php` |
| **Txantiloia**    | `XxxTemplate.<ext>` | `UserTemplate.json` |

---

## Osagaiak Errendatzea

Errendatze-fluxu tipiko batek osagaia instantziatzea, datuak esleitzea eta emaitza irteeratzea dakar.

```php
$cmp = new BooksComponent();

// Kate bihurtu dezakezu run() eta render() abiarazteko
echo strval($cmp);

```

# Txantiloi-hodiak

Osumi Framework-eko txantiloiek **Angular erako hodiak** onartzen dituzte, balioak txantiloiaren barruan zuzenean eraldatzeko aukera emanez.

### Sintaxia

{{ value | pipeName }}
{{ value | pipeName:param }}
{{ value | pipeName:param1:param2 }}

### Helburua

Hodiek formatua ahalbidetzen dute:

- Datak
- Zenbakiak
- Kateak
- Boolearrak

Hodiak barneko **OPipeFunctions** klaseak prozesatzen ditu.

---

# Eskuragarri dauden hodiak

Jarraian, `OPipeFunctions.php`-ko funtzioetatik eratorriak diren hodi integratu guztiak eta haien portaera ageri dira.

---

## 1. `date`

Data-kate bat formatu berri batean formateatzen du (`Y-m-d H:i:s` formatua).

### Sintaxia

{{ user.created_at | date }}
{{ user.created_at | date:"d/m/Y" }}
{{ user.created_at | date:"d-m-Y H:i" }}

### Portaera

- Sarrera `Y-m-d H:i:s` izan behar da
- Irteera PHP `DateTime::format()` erabiliz formateatzen da
- Data baliogabea bada → `"null"`

### Formatu lehenetsia

d/m/Y H:i:s

---

## 2. `number`

Zenbakiak PHP-ren `number_format()` erabiliz formateatzen ditu.

### Sintaxia

{{ prezioa | number }}
{{ prezioa | number:2 }}
{{ prezioa | number:2:".":"," }}

### Portaera

- Lehenetsitako hamartarrak: **2**
- Lehenetsitako hamartarren bereizlea: `".."`
- Lehenetsitako milakoen bereizlea: `""`
- Balioa nulua bada → `"null"`

Adibideak:

1234.5 → 1234.50
1234.5 → 1,234.50 (milakoen bereizlea "," bada)

---

## 3. `string`

`urlencode()` aplikatzen dio kate bati.

### Sintaxia

{{ erabiltzaile.izena | string }}

### Portaera

- Nulua → `"null"`
- Balioa → `"urlencode-rekin kodeatutako katea"`

Adibidea:

"John Doe" → "John+Doe"

---

## 4. `bool`

Boolearrak bihurtzen ditu:

    true
    false
    null

### Sintaxia

{{ erabiltzailea.isAdmin | bool }}

---

# Nola jokatzen duten hodiak JSON txantiloietan

`.json` bezalako txantiloiak kate gisa errendatzen direnez, hodiek automatikoki ziurtatzen dute:

- Kateak komatxo artean agertzen dira behar direnean
- Boolearrak komatxorik gabe agertzen dira
- Zenbakiak komatxorik gabe agertzen dira
- Balio nuluak `null` gisa agertzen dira

Honek JSON irteera baliozkoa bermatzen du.

---

# Adibideak

```json
{
  "id": {{ user.id | number }},
  "name": {{ user.name | string }},
  "created": {{ user.created_at | date:"d/m/Y" }},
  "active": {{ user.active | bool }}
}
```

---

# Hodien laburpena

| Hodia    | Helburua                      | Oharrak                                  |
| -------- | ----------------------------- | ---------------------------------------- |
| `date`   | Data balioak formateatu       | Maskara pertsonalizatuak onartzen ditu   |
| `number` | Zenbakizko balioak formateatu | Hamartarrak eta bereizleak onartzen ditu |
| `string` | URL kodeketa kateak           | Komatxoak gehitzen ditu                  |
| `bool`   | Normalizatu irteera boolearra | `true` / `false` / `null`                |

### Ereduari lotutako osagaiak

Osagaiek ereduaren ikuspegiak adierazten dituztenean, motatutako propietateak erabil ditzakezu zure eredu klaseekin.

```php
namespace Osumi\OsumiFramework\App\Component\Model\User;

use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\App\Model\User;

class UserComponent extends OComponent {
  public ?User $user = null;
}

```

---

## Praktika onak

- **Mantendu txantiloiak sinpleak**: Mugatu bistaratze logika minimora.
- **Erabili `run()`**: Erabili datuak prestatzeko edo kalkuluak egiteko errendatu aurretik.
- **Motatutako propietateak**: Erabili motatutako propietate publikoak argitasunerako.
- **Balio lehenetsiak**: Hobetsi `?type = null` lehenetsitako balioak PHP 8.3+-n "hasi gabeko propietatea" erroreak saihesteko.
