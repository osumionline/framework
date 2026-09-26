# Osagaiak

Osumi Framework-eko osagaiak txantiloi bat errendatzen duten kode zati txiki eta berrerabilgarriak dira. Osagai bat honako hauek osatzen dute:

- `OComponent` hedatzen duen PHP klase bat.
- Txantiloi fitxategi bat (php/html/json/xml erabileraren arabera).

Osagai instantzia bat sortzen da, propietateak esleitzen zaizkio eta gero osagaia errendatzen da, normalean `render()` bidez edo objektua kate batera bihurtuz.

---

## Oinarrizko osagaien egitura

### Osagai-klasea

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Email\LostPassword;

use Osumi\OsumiFramework\Core\OComponent;

class LostPasswordComponent extends OComponent {
  public ?string $token = null;
}
```

### Txantiloi-fitxategia

```php
<div>
  Token: {{ token }}
</div>
```

---

## Ezaugarri aurreratuak

### Eduki mota goiburu automatikoak

Osagai bat URL baten ekintza nagusi gisa erabiltzen denean, framework-ak automatikoki bidaltzen du dagokion `Content-Type` goiburua txantiloiaren fitxategi-luzapenaren arabera:

- `.json`: `Content-type: application/json`.
- `.xml`: `Content-type: application/xml`.
- `.html` / `.php`: `Content-type: text/html`.

### Osagaien habiaratzea

Osagaiak kateatu edo habiaratu daitezke, osagai txikiagoak berrerabiltzeko.

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Father;

use Osumi\OsumiFramework\App\Component\Child\ChildComponent;
use Osumi\OsumiFramework\Core\OComponent;

class FatherComponent extends OComponent {
  public ?ChildComponent $child = null;

  public function run(): void {
    $this->child = new ChildComponent();
    $this->child->name = 'Semearen izena';
  }
}
```

### Txantiloiaren sintaxia eta sarbidea

1. **PHP txantiloiak (`.php`)** PHP kode natiboa exekutatu dezakete eta propietate publikoak aldagai estandar gisa atzitu.
2. **Txantiloi estatiko/egituratuek (`.html`, `.json`, `.xml`)** giltza bikoitzak erabiltzen dituzte: `{{ variable_name }}`.

---

## `run()` metodoa (aukerakoa)

Osagai batek aukerako `run()` metodo bat defini dezake. Definituta badago, automatikoki exekutatzen da `render()` prozesuaren hasieran, txantiloia prozesatu aurretik datuak prestatzeko.

Osagaia ekintza gisa erabiltzen denean (aktibatutako bide baten emaitza gisa errendatutako osagaia), `run()` metodoak honako sinadura hauetako bat izan behar du:

```php
public function run(): void
```

```php
public function run(ORequest $req): void
```

```php
public function run(MyDTO $dto): void
```

Portaera erabilitako sinaduraren araberakoa da:

- `run()` metodoak ez du eskaeraren daturik jasotzen, eta osagaiak uneko eskaera atzitu behar ez duenean erabil daiteke.
- `run(ORequest $req)` metodoak uneko eskaera `ORequest` instantzia gisa jasotzen du.
- `run(MyDTO $dto)` metodoak uneko eskaeraren datuekin automatikoki betetako DTO bat jasotzen du. `MyDTO` klaseak `ODTO` hedatu behar du.
- DTOak `ODTO`-ren herentziaren bidez detektatzen dira, ez namespace-aren bidez. Beraz, DTO klaseak aplikazioaren edozein lekutan egon daitezke.
- Ez da beste sinadurarik onartzen. Metodoak gehienez parametro bat jaso dezake, eta parametro horrek nullable ez den `ORequest` bat edo `ODTO` hedatzen duen klase bat izan behar du.

`ORequest` klaseak jasotako datuak eskuratzeko metodoak eskaintzen ditu, hala nola formulario-balioak edo URLaren bidez jasotako parametroak:

- **`getParamString('name')`**: bideari pasatutako `name` eremuaren balioa kate gisa itzultzen du (null ez badago).
- **`getParamInt('name')`**: bideari pasatutako `name` eremuaren balioa zenbaki oso gisa itzultzen du (null ez badago).
- **`getParamFloat('name')`**: bideari pasatutako `name` eremuaren balioa float gisa itzultzen du (null ez badago).
- **`getParamBool('name')`**: bideari pasatutako `name` eremuaren balioa boolear gisa itzultzen du (null ez badago).

Bide batek iragazki bat definituta badu, `ORequest` klaseak exekuzioaren emaitza atzitzeko moduak ere eskaintzen ditu:

```php
public function run(ORequest $req): void {
  $login_filter = $req->getFilter('login');
  $filters = $req->getFilters();
}
```

**Adibideak:**

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

---

## Aukera globaletara sartzea

Osagaiek aplikazioaren konfigurazioa, log-ak edo saio-datuak bezalako aukera globaletara sartzeko metodoak dituzte:

- **`getConfig()`**: `OConfig` globala itzultzen du.
- **`getLog()`**: Osagaiaren `OLog` instantzia itzultzen du.
- **`getSession()`**: `OSession` instantzia itzultzen du.

---

## Osagaiak errendatzea

```php
$cmp = new BooksComponent();
echo strval($cmp);
```

---

# Txantiloi-pipeak

Osumi Framework-eko txantiloiek **Angular estiloko pipeak** onartzen dituzte, balioak txantiloiaren barruan zuzenean eraldatzeko aukera emanez.

### Sintaxia

    {{ value | pipeName }}
    {{ value | pipeName:param }}
    {{ value | pipeName:param1:param2 }}

### Helburua

Pipeek formatua ahalbidetzen dute:

- Datak
- Zenbakiak
- Kateak
- Boolearrak

Pipeak barneko **OPipeFunctions** klaseak prozesatzen ditu.

---

# Eskuragarri dauden pipeak

## 1. `date`

Data-kate bat (`Y-m-d H:i:s` formatua) formatu berri batean formateatzen du.

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

    {{ price | number }}
    {{ price | number:2 }}
    {{ price | number:2:".":"," }}

### Portaera

- Lehenetsitako hamartarrak: **2**
- Lehenetsitako hamartarren bereizlea: `"."`
- Lehenetsitako milakoen bereizlea: `""`
- Balioa nulua bada → `"null"`

---

## 3. `string`

`urlencode()` aplikatzen dio kate bati.

### Sintaxia

    {{ user.name | string }}

### Portaera

- Nulua → `null`
- Balioa → URL bidez kodetutako katea komatxo artean

Adibidea:

    John Doe → "John+Doe"

---

## 4. `plain`

Kate bat JSONerako balio seguru gisa kodetzen du, URL kodetzea aplikatu gabe.

### Sintaxia

    {{ user.name | plain }}

### Portaera

- Nulua → `null`
- Balioa → JSONerako baliozko katea komatxo artean
- Unicode karaktereak mantentzen dira
- Barrak ez dira ihes egiten
- Komatxoak eta JSONerako bereziak diren beste karaktereak behar bezala ihes egiten dira

Adibideak:

    John Doe → "John Doe"
    "Kaixo" esan zuen → "\"Kaixo\" esan zuen"

Pipe hau bereziki erabilgarria da JSON txantiloietan jatorrizko katearen balioa URL kodetzerik gabe mantendu nahi denean.

---

## 5. `bool`

Boolearrak bihurtzen ditu:

    true
    false
    null

### Sintaxia

    {{ user.isAdmin | bool }}

---

# Nola jokatzen duten pipeek JSON txantiloietan

Pipeek txantiloi egituratuetarako balio egokiak sortzen dituzte:

- `string` pipeak URL bidez kodetutako katea sortzen du komatxo artean.
- `plain` pipeak JSONerako kate segurua sortzen du komatxo artean, URL kodetzerik gabe.
- Boolearrak komatxorik gabe agertzen dira.
- Zenbakiak komatxorik gabe agertzen dira.
- Balio nuluak `null` gisa agertzen dira.

---

# Adibideak

```json
{
  "id": {{ user.id | number }},
  "name": {{ user.name | plain }},
  "slug": {{ user.slug | string }},
  "created": {{ user.created_at | date:"d/m/Y" }},
  "active": {{ user.active | bool }}
}
```

---

# Pipeen laburpena

| Pipe     | Helburua                        | Oharrak                              |
| -------- | ------------------------------- | ------------------------------------ |
| `date`   | Data-balioak formateatzea       | Maskara pertsonalizatuak onartzen ditu |
| `number` | Zenbakizko balioak formateatzea | Dezimalak eta bereizleak onartzen ditu |
| `string` | Kateak URL gisa kodetzea        | Komatxoak gehitzen ditu              |
| `plain`  | URL kodetu gabeko kateak        | JSONerako irteera segurua            |
| `bool`   | Irteera boolearra normalizatzea | `true` / `false` / `null`            |


### Ereduari lotutako osagaiak

Osagaiek ereduaren ikuspegiak adierazten dituztenean, motatutako propietateak erabil ditzakezu.

```php
namespace Osumi\OsumiFramework\App\Component\Model\User;

use Osumi\OsumiFramework\App\Model\User;
use Osumi\OsumiFramework\Core\OComponent;

class UserComponent extends OComponent {
  public ?User $user = null;
}
```

---

## Praktika onak

- **Mantendu txantiloiak sinpleak**: Mugatu bistaratze-logika minimora.
- **Erabili `run()`**: Erabili datuak prestatzeko edo kalkuluak egiteko errendatu aurretik.
- **Motatutako propietateak**: Erabili motatutako propietate publikoak argitasunerako.
- **Balio lehenetsiak**: Hobetsi `?type = null` hasieratu gabeko propietateen erroreak saihesteko.
