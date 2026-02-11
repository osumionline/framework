# Zerbitzuak

**Osumi Framework**-eko zerbitzuak berrerabilgarriak diren klaseak dira, negozio-logika, eragiketa partekatuak edo osagai, modulu eta zereginetan erabiltzen diren erabilgarritasun-funtzioak biltzen dituztenak.

Angular zerbitzuen antzera jokatzen dute: arinak, konposagarriak eta osagaiak garbi eta fokatuta mantentzeko diseinatuta daude.

Zerbitzuek honako hau egiten laguntzen dizute:

- Osagaien arteko logika bikoiztea saihestea
- Domeinu espezifikoen portaerak antolatzea
- Modeloekin edo kanpoko APIekin elkarreraginak zentralizatzea
- Zure aplikazioa arkitektura garbiaren printzipioen arabera egituratzea

---

# 1. Zer da Zerbitzu bat?

Zerbitzu bat **`OService` hedatzen duen** PHP klase bat da.
Oinarrizko klaseak honako hau eskaintzen du:

- Erregistro bat (`OLog`)
- Aplikazioaren konfiguraziorako sarbidea
- Cache globaleko edukiontzirako sarbidea

Zerbitzu klaseak zure aplikazioak behar dituen edozein metodo publiko defini ditzake.

---

# 2. Zerbitzu bat sortzea

Zerbitzu bat honako honetan egon beharko litzateke:

    src/App/Service/

Ohiko egitura:

```php
namespace Osumi\OsumiFramework\App\Service;

use Osumi\OsumiFramework\Core\OService;

class UserService extends OService {
  public function getUserById(int $id): ?User {
    return User::findOne(['id' => $id]);
  }
}
```

Zerbitzuek normalean:

- Logika berrerabilgarria inplementatzen dute
- Ereduak kontsultatzen edo manipulatzen dituzte
- Urrats anitzeko eragiketak koordinatzen dituzte
- Aukeran beste zerbitzu batzuk erabiltzen dituzte

---

# 3. Zerbitzu bat osagai batean txertatzea

Ezin duzu zerbitzu bat txertatu klase batean propietatea deklaratzen den unean.
**Hau PHP hizkuntzaren muga bat da:** PHP-k **ez** du onartzen funtzioak (adibidez, `inject()`) deitzea propietateen deklarazioen barruan.

Adibidez, hau **baliogabea** da PHPn:

```php
private UserService $us = inject(UserService::class); // Ez da onartzen PHPn
```

Horregatik, zerbitzuak eraikitzailearen barruan injektatu behar dira:

```php
class MyComponent extends OComponent {
  private ?UserService $us = null;

  public function __construct() {
    parent::__construct();
    $this->us = inject(UserService::class); // Zuzena
  }
}
```

Horrek ziurtatzen du zerbitzua eskuragarri dagoela `run()` exekutatzen denerako.

---

# 4. Zerbitzua osagai batean erabiltzea

Injektatu ondoren, zerbitzuko metodoetara normal sar zaitezke:

```php
public function run(ORequest $req): void {
  $user = $this->us->getUserById(3);
  $this->user = $user;
}
```

Eredu tipiko bat hau da:

1. Eskaeratik datuak atera (iragazkiak edo DTOak erabiliz, agian)
2. Negozio-logika zerbitzuari delegatu
3. Azken osagaiaren irteera prestatu

Osagaiak txikiak eta deklaratiboak izaten jarraitzen dute — zerbitzuek egiten dute lan astuna.

---

# 5. Zerbitzu baten bizi-zikloa

`OService`-k hasieratze batzuk automatikoki kudeatzen ditu:

### Erregistratzailearen hasieraketa

Zerbitzu bakoitzak bere erregistratzailea du arazketa-informazioa idazteko.

### Konfiguraziorako sarbidea

`$this->getConfig()`-k aplikazioaren konfigurazio orokorra ematen dizu.

### Cache edukiontzirako sarbidea

`$this->getCacheContainer()`-k cache azpisistema orokorra ematen dizu.

Laguntzaile hauek zerbitzuak indartsuak eta egoera orokorretik deskonektatuak direla ziurtatzen dute.

---

# 6. Zerbitzuak non jarri

Jarraitu egitura hau:

    src/
      App/
        Service/
          CinemaService.php
          UserService.php
          NotificationService.php

Izendatzeko konbentzioak:

- Klasea: `XxxService`
- Fitxategia: `XxxService.php`

---

# 7. Adibidea: Domeinuan zentratutako zerbitzua

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

Zerbitzu honek:

- Datuak berreskuratzen ditu
- Ezabatze-eragiketa anitzak egiten ditu
- Domeinu-arauak kapsulatzen ditu

Logika hau zentralizatuz, zinema-eragiketak behar dituen osagai guztiek berrerabili dezakete.

---

# 8. Praktika onak

- **Mantendu zerbitzuak egoerarik gabe** ahal den guztietan
  Laguntzaile hutsak bezala jokatu behar dute.

- **Elkarrekin lotutako funtzionaltasunak taldekatu**
  Saihestu zerbitzu-klase multiuso erraldoiak.

- **Erabili beste zerbitzu batzuk behar denean**
  Zerbitzu-hierarkiak sortzea baliozkoa da (adibidez, `OrderService` `PaymentService` erabiliz).

- **Saihestu zerbitzuetan errendatzea edo irteera**
  Zerbitzuek ez lukete HTML oihartzunik edo itzultzerik izan behar; hori osagaiei dagokie.

- **Erabili erregistratzailea arazketarako**
  `$this->getLog()->debug("...")` oso lagungarria da.

- **Utzi osagaiei orkestratzen**
  Osagaiak eskaera → zerbitzua → modeloak → txantiloia koordinatzen dituzte.

---

# 9. Noiz erabili behar duzu zerbitzu bat?

Erabili zerbitzu bat honako kasu hauetan:

- Osagai anitzek logika bera partekatzen dutenean
- Logikak ereduen arteko elkarrekintzak ez-tribialak dakartzanean
- Erabilgarriak diren eragiketak behar dituzunean
- Domeinu logika aurkezpen logikatik bereizi nahi duzunean
- Osagaiak txikiak, garbiak eta fokatuak mantendu behar dituzunean

Ez erabili zerbitzu bat honako kasu hauetan:

- Logika eskaera-iragazkia da soilik (erabili Iragazkiak)
- Logika errendatzeari buruzkoa da (erabili Osagaiak)
- Logika sarrera datuak baliozkotzearekin lotuta dago (erabili DTOak)

---

# 10. Laburpena

Zerbitzuak Osumi Framework-aren oinarrizko eraikuntza-blokeetako bat dira:

- Kezkak garbi bereiztea sustatzen dute
- Bikoiztasunak murrizten dituzte
- Negozio logika zentralizatzen dute
- Osagaiak modu garbian integratzen dira mendekotasun injekzioaren bidez
- Konposizioa onartzen dute (zerbitzuek zerbitzuak erabiltzen dituzte)

Zerbitzuak modu eraginkorrean erabiltzeak aplikazio arkitektura mantentze-lanetarako errazago, eskalagarriagoa eta antolatuagoa lortzen du.
