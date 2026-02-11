# Osumi Framework – LLM Testuingurua (Guztia Batean)

**Helburua**
Dokumentu honek testuinguru trinkoa baina osoa eta autoritarioa eskaintzen du edozein Hizkuntza Eredu Handi (LLM) **Osumi Framework**-rako kodea behar bezala ulertzeko, azaltzeko eta sortzeko.

**ChatGPT, GitHub Copilot, Gemini, Claude** eta beste laguntzaile batzuekin bateragarria da.

> Dokumentu hau **egiaren iturri** gisa hartu.
> Zerbait hemen deskribatzen ez bada, **ez** eman existitzen denik.

---

## 0. Esparruaren Identitatea

- **Izena:** Osumi Framework
- **Bertsioa:** 9.8
- **Hizkuntza:** PHP
- **Gutxieneko PHP Bertsioa:** 8.3+
- **Idazketa:** `declare(strict_types=1)` derrigorrezkoa da
- **Estiloa:** esplizitua, aurreikusgarria, magia ezkuturik gabe

---

## 1. Filosofia (KRITIKOA)

Osumi Framework-ek honako hau lehenesten du:

- Kode esplizitua magiaren gainetik
- Aurreikusgarritasuna erosotasunaren gainetik
- Kezkak argi bereiztea
- Konposizioa herentzia-zuhaitz konplexuen gainetik

**Ez** asmatu laguntzailerik, lasterbiderik edo bizi-zikloko kakorik.

---

## 2. Eskaeraren Bizitza Zikloa (AUTORITATZAILEA)

HTTP eskaera guztiek hodi hodi hau jarraitzen dute:

```
Bezeroaren Eskaera
↓
Bideratzea (ORoute)
↓
Iragazkiak (0..n)
↓
DTO hidratazioa eta baliozkotzea (aukerakoa)
↓
Component::run()
↓
Txantiloiaren errendatzea
↓
Diseinuaren bilgarritzea (aukerakoa)
↓
HTTP Erantzuna
```

### Arau nagusiak

- **Iragazkiak beti osagaien aurretik exekutatzen dira**.
- Iragazki batek huts egiten badu → eskaera gelditu egiten da (403 edo birbideratzea).
- DTOak `run()` parametro gisa deklaratzen badira bakarrik sortzen dira.
- Txantiloiek osagaien propietate publikoak errendatzen dituzte.

---

## 3. Eraikuntza Bloke Nagusiak

### 3.1 Bideratzea (ORoute)

Ibilbideek URLak Osagaiei mapatzen dizkiete.

- HTTP aditzak: GET, POST, PUT, DELETE
- Ibilbide-parametroak onartzen ditu `:name` bidez
- Onartzen ditu:
    - Aurrizki taldeak (`ORoute::prefix()`)
    - Diseinu taldeak (`ORoute::layout()`)
    - Talde konbinatuak (`ORoute::group(prefix, layout, fn)`)

Adibideak:

```php
ORoute::get('/', HomeComponent::class);
ORoute::get('/user/:id', UserComponent::class);
```

---

### 3.2 Iragazkiak

**Helburua:** autentifikazioa/baimena, eskaerak blokeatzea, testuingurua kargatzea.

#### Kontratua

```php
public static function handle(array $params, array $headers): array
```

Itzulitako balioak honako hauek izan behar ditu:

```php
['status' => 'ok' | 'error']
```

Aukerakoa:

- `return` → birbideratze URLa
- Beste edozein testuinguru-propietate

#### Portaera

- Iragazkiak ordenan exekutatzen dira.
- Lehenengo huts egiten duen iragazkiak eskaera gelditzen du.
- Huts egiten duenean:
- Birbideratu `return` existitzen bada
- Bestela HTTP 403

Iragazkiaren irteerak geroago eskuragarri daude `ORequest->getFilter('Name')` bidez.

---

### 3.3 DTOak (ODTO)

**Helburua:** idatzitako eskaeraren sarrera + balidazioa, negozio logikaren aurretik.

- DTOek `ODTO` hedatzen dute
- Eremuak `#[ODTOField]`-rekin deklaratzen dira
- Esparruak instantziatzen du, balioak kargatzen ditu, baliozkotzen ditu eta gero `run()`-n injektatzen du.

#### Datu-iturriaren lehentasuna

1. Iragazkiaren emaitza (`filter` + `filterProperty`)
2. Goiburua (`header`)
3. Eskaera-parametroak (motatutako getter-ak)

#### Balidazioa

- `required`
- `requiredIf`

Beti egiaztatu:

```php
if (!$dto->isValid()) {
  $errors = $dto->getValidationErrors();
}
```

DTOek ez lukete negozio-logikarik izan behar.

---

### 3.4 Osagaiak (OComponent)

**Helburua:** eskaera antolatzea → zerbitzuak/ereduak → irteera prestatzea → errendatzea.

- Mota publikoko propietateak txantiloietan agertzen dira.
- Aukerako `run()` metodoa.
- `run()`-k hau onartu dezake:
    - DTO bat (sarrera idatzia)
    - `ORequest` bat (parametro/goiburu/iragazki/fitxategietarako sarbide gordina)

Mantendu osagaiak meheak; eraman negozio logika Zerbitzuetara.

---

### 3.5 Txantiloiak

Txantiloi fitxategiak hauek izan daitezke:

- `.php`
- `.html`
- `.json`
- `.xml`

Txantiloi estatikoek (`.html/.json/.xml`) irteera kizkurra erabiltzen dute:

```
{{ aldagaia }}
{{ balioa | pipe }}
```

**JSON txantiloiek beti JSON balioduna eman behar dute**.

---

### 3.6 Diseinuak

**Helburua:** ibilbide osagai baten irteera errendatua orrialde egitura partekatu batekin biltzea.

#### Errendatze-fluxu autoritarioa

1. Ibilbide-osagaia exekutatu eta errendatzen da
2. Ibilbiderako diseinu bat definitzen bada, diseinuak hau jasotzen du:
    - `title` (orrialde-izenburu lehenetsia)
    - `body` (ibilbide-osagaiaren irteera errendatua)
3. Diseinu-txantiloia azken erantzun gisa errendatzen da

Diseinuak dira egitura globalaren eta aktiboen injekzioaren leku naturala.

#### Diseinu lehenetsia

Proiektu berriek `title` eta `body` dituen diseinu-osagai lehenetsi bat eta horiek injektatzen dituen txantiloi bat dituzte (normalean `<title>` eta `<body>`-tan).

#### Bideratzean diseinu pertsonalizatu bat definitzea (GARRANTZITSUA)

Routing-etik diseinu pertsonalizatu bat defini dezakezu:

```php
ORoute::layout(MainLayoutComponent::class, function() {
  ORoute::get('/home', HomeComponent::class);
  ORoute::get('/contact', ContactComponent::class);
});
```

Edo konbinatu aurrizkia + diseinua:

```php
ORoute::group('/admin', AdminLayoutComponent::class, function() {
  ORoute::get('/dashboard', DashboardComponent::class);
});
```

---

### 3.7 Hodiak

Txantiloiek Angular estiloko hodiak onartzen dituzte:

- `data` → formatua `Y-m-d H:i:s` maskara batera (lehenetsia `d/m/Y H:i:s`)
- `zenbakia` → `number_format` estiloko formatua
- `string` → `urlencode` JSON-seguruko komatxoekin
- `bool` → `true | false | null`

---

### 3.8 Zerbitzuak (OService)

**Helburua:** negozio logika eta berrerabilgarriak diren eragiketak.

- Zerbitzuek `OService` hedatzen dute.
- Konfigurazio/erregistro/katxea oinarrizko laguntzaileen bidez atzitu dezakete.
- Injekzioa **eraikitzailean** egiten da (PHP-k ezin du `inject()` deitu propietate deklarazioetan).
- Zerbitzuek errendatzea saihestu eta ahalik eta egoerarik gabekoenak izan behar dituzte.

---

## 4. ORM (OModel)

Osumi Framework-ek PHP atributuak erabiltzen dituen ORM arin bat dauka.

### Nahitaezko arauak

Modelo guztiek **definitu** behar dute:

- ≥ 1 `#[OPK]`
- zehazki 1 `#[OCreatedAt]`
- zehazki 1 `#[OUpdatedAt]`

### Atributuak

- `OPK` (lehen mailako gakoa; PK konposatua onartzen du)
- `OField` (zutabe normala)
- `OCreatedAt` (derrigorrezko sortutako denbora-zigilua)
- `OUpdatedAt` (derrigorrezko eguneratutako denbora-zigilua)
- `ODeletedAt` (aukerakoa den denbora-zigilua ezabatzeko modu leuna)

### Motak (OField konstanteak)

- `OField::NUMBER`
- `OField::TEXT`
- `OField::LONGTEXT`
- `OField::FLOAT`
- `OField::BOOL` (`TINYINT(1)` gisa gordeta)
- `OField::DATE` (data eta ordu katea)

### Portaera

- Taularen izena `snake_case` erabiliz klase-izenetik ondorioztatua.
- `save()` INSERT vs UPDATE aukeratzen du PK/egoeraren arabera.
- `validate()` gordetzean exekutatzen da; hutsegiteek salbuespenak sortzen dituzte.
- `ref: 'table.field'` SQL kanpoko gakoak sortzeko erabiltzen da, baina **ez ditu** erlazioak automatikoki kargatzen.

---

## 5. CLI (OTask)

Osumi Framework CLI-n oinarritzen da lehenik.

- Sarrera puntua: `php of`
- Tasks extend `OTask`
- Sinadura:

```php
public function run(array $options = []): void
```

Eraikuntza, mantentze, eskema esportazio eta alderantzizko ingeniaritzarako erabiltzen da.

---

## 6. Pluginak

Pluginak Composer bidez instalatzen dira eta honen azpian agertzen dira:

```
Osumi\OsumiFramework\Plugins
```

Adibideak: `OToken`, `OEmail`, `OImage`, `OWeBSocket`, etab.

Pluginak aukerakoak eta independenteak dira.

---

## 7. Konfigurazioa

- JSON fitxategiak `src/Config/`-n
- Ingurunearen gainidazketa fitxategiak onartzen ditu (adibidez, `Config_prod.json`)
- Sarbidea `OConfig` bidez:

```php
$this->getConfig()->getExtra('secret');
$this->getConfig()->getDir('uploads');
$this->getConfig()->getDB('name');
```

---

## 8. Hitzarmenak (ZORROTZA)

- Beti `declare(strict_types=1)`
- Klaseak: PascalCase
- Fitxategiak: PascalCase.php
- Taulak: snake_case
- Propietateak/eremuak: snake_case
- Hobetsi `public ?Type $prop = null` hasieratu gabeko motako propietateen erroreak saihesteko

---

## 9. Zer EZ da suposatu behar (GARRANTZITSUA)

**Ez** suposatu:

- DI automatikoa propietateen deklarazioetan
- erregistro aktiboen harremanaren karga
- middleware kanalizazioak
- serializazio magia ezkutua
- laguntzaile dokumentatu gabeak

Portaera dokumentatua bakarrik existitzen da.

---

## 10. Nola erantzun behar dituen galderak LLM batek

Osumi Framework-i buruz erantzuterakoan:

- Erabili dokumentu hau autoritate gisa
- Hobetsi kode esplizitu eta idatzia
- Errespetatu arkitektura mugak (Iragazkiak vs DTOak vs Zerbitzuak vs Osagaiak)
- Ziur ez bazaude, galdetu falta diren xehetasunak asmatu beharrean

---

## Testuinguruaren amaiera

Fitxategi hau nahita trinkoa, esplizitua eta determinista da.
Edozein LLMk zuzenean irensteko egokia da.
