# Iragazkiak

**Osumi Framework**-eko iragazkiak osagai bat exekutatu aurretik exekutatzen diren klase txiki eta berrerabilgarriak dira.
Ohikoena honetarako da:

- Autentifikazioa eta baimena
- API giltza / token baliozkotzea
- Eskaerak aurre-prozesatzea
- Baimen-egiaztapenak
- Testuinguru-datuak (erabiltzailea, maizterra, lokalea...) aurre-kargatzea

Iragazkiek _bide jakin batzuetarako eskaera bakoitzaren aurretik_ exekutatu behar den logika zentralizatzea ahalbidetzen dute, osagaiak garbi eta fokatuta mantenduz.

---

# 1. Zer da iragazki bat?

Iragazki bat PHP klase bat da —normalean `src/App/Filter/`-n jartzen dena—, metodo estatiko bat inplementatzen duena:

```php
public static function handle(array $params, array $headers): array
```

Gutxienez honako hauek dituen **array asoziatibo** bat itzuli behar du:

```php
[
  'status' => 'ok' | 'error',
  // beste balio batzuk...
]
```

Iragazkiak `Authorization` goiburua egiaztatzen du, token bat balioztatzen du eta `"ok"` egoera edo `"error"` itzultzen du, gehi aukerako erabiltzaile ID bat.

---

# 2. Iragazkiaren adibidea

Hona hemen benetako `LoginFilter`:

```php
class LoginFilter {
  public static function handle(array $params, array $headers): array {
    global $core;
    $ret = ['status' => 'error', 'id' => null];

    $tk = new OToken($core->config->getExtra('secret'));

    if ($tk->checkToken($headers['Authorization'])) {
      $ret['status'] = 'ok';
      $ret['id'] = intval($tk->getParam('id'));
    }

    return $ret;
  }
}
```

Adibide honek honako hau erakusten du:

- Goiburuak irakurtzea
- Token bat balioztatzea
- Geroago DTOek edo osagaiek erabiliko dituzten testuinguru-datuak (`id`) itzultzea

---

# 3. Nola exekutatzen diren iragazkiak (Framework Fluxua)

Iragazkiak **OCore**-k exekutatzen ditu eskaeraren bizi-zikloaren barruan, _osagaia instantziatu aurretik_.

Prozesua laburbildu daiteke:

1. Bideratze-sistemak bat datorren ibilbidea eta bere iragazkien zerrenda identifikatzen ditu.
2. OCore-k `$url_result` sortzen du eskaeratik.
3. Iragazki bakoitzerako (ordenan):
    - Iragazki klasea instantziatu
    - Bere `handle($params, $headers)` deitu
    - Itzulitako `"status"` balioztatu
    - Emaitza gorde `"ok"` bada
    - Gelditu eta errore bat itzuli `"error"` bada

Logika zehatza OCore-n:

```php
foreach ($url_result['filters'] as $filter) {
  $filter_instance = new $filter();
  $value = $filter_instance->handle(
    $url_result['params'],
    $url_result['headers']
  );

  if ($value['status'] !== 'ok') {
    // Errore edo birbideratzea kudeatu
    ...
    break;
  }

  $filter_results[$class_name] = $value;
}
```

---

# 4. Zer gertatzen da iragazki batek huts egiten duenean?

Iragazkiren batek `"status" !== "ok"` itzultzen badu:

### Eskaera berehala gelditzen da

OCore-k gainerako iragazkiak prozesatzeari uzten dio eta osagaia exekutatzea eragozten du.

### `"return"` sartzen bada

Framework-ak zehaztutako URL-ra birbideratzen du.

### Bestela, framework-ak **403 Forbidden** itzultzen du

OCore-k HTTP 403 ezartzen du eta errore-orri bat bistaratzen du.

Horrek bermatzen du baimenik gabeko eskaerak ez direla inoiz zure negozio-logikara iritsiko.

---

# 5. Zure aplikazioaren barruan iragazki-emaitzetara sartzea

Iragazki guztiek arrakasta izan ondoren, OCore-k `ORequest` objektu bat sortzen du:

```php
$req = new ORequest($url_result, $filter_results);
```

Horrek iragazki-emaitza guztiak eskuragarri jartzen ditu honen bidez:

```php
$req->getFilter('Login');
```

Adibidez:

```php
$login = $req->getFilter('Login');

if ($login['status'] === 'ok') {
  $userId = $login['id'];
}
```

Iragazkien izenak normalizatzen dira klase-izenetik `"Filter"` atzizkia kenduz, OCore-k islapena erabiliz egiten duen bezala.

---

# 6. DTO bateko iragazkien datuak erabiltzea

DTO eremuek automatikoki mapa ditzakete iragazkien balioak honako hau erabiliz:

```php
#[ODTOField(filter: 'Login', filterProperty: 'id')]
public ?int $idUser = null;
```

Horrek esan nahi du:

- Bezeroak ezin du balio hau faltsutu edo gainidatzi.
- DTO-k erabiltzaile-IDa modu seguruan injektatzen du.
- Osagaiak ez du eskuz sartu behar iragazkira.

Honek funtzionatzen du ODTOk iragazkiaren datuak `$req->getFilter()` bidez irakurtzen dituelako eremuak betetzean.

---

# 7. Iragazkiak Ibilbideetan Definitzea

Ibilbide fitxategi batean:

```php
ORoute::post(
  '/profile',
  ProfileComponent::class,
  [LoginFilter::class]
);
```

Amaierako puntu hau deitzen denean:

1. Bideratzaileak `/profile` detektatzen du.
2. Osagaia exekutatu aurretik, OCorek `LoginFilter` exekutatzen du.
3. Iragazkiak huts egiten badu → eskaera amaitzen da.
4. Gainditzen badu → osagaia normal exekutatzen da.

OCore-ren iragazkiaren prozesamenduak fluxu zehatz hau baieztatzen du.

---

# 8. Iragazkiaren Itzulera Formatua

Iragazki batek beti itzuli behar du array bat honela:

```php
[
  'status' => 'ok' | 'error',
  'return' => '/login', // hautazko birbideraketa
  // balio pertsonalizatuak...
]
```

Adibidez:

```php
[
  'status' => 'ok',
  'id'     => 123,
  'role'   => 'admin'
]
```

---

# 9. Praktika Onak

### Iragazkiak egoerarik gabe mantendu

Ez lukete egoera aldakor globalaren menpe egon behar (konfigurazioa edo saioa irakurtzea izan ezik).

### Beti itzuli `"status" => "ok"` edo `"error"`

OCore-k eremu honen menpe dago eskaera jarraitu ala ez erabakitzeko.

### Erabili iragazkiak autentifikaziorako / baimenerako

DTOek ez lukete kredentzialik zuzenean erabiltzaileengandik jaso behar segurtasunez injektatu daitezkeenean.

### Izendatu iragazkiak `XxxFilter`-rekin

Horrek ziurtatzen du OCore-ren klase-izenaren normalizazioa aurreikusteko moduan jokatzen dela.

### Saihestu logika astuna

Mantendu iragazkiak txikiak; mugitu negozio-logika zerbitzuetara.

### Erabili `"return"` erabiltzaileak birbideratzeko

Erabilgarria saioa hasteko babesleentzat edo onboarding fluxuentzat.

---

# 10. Noiz Erabili Behar Duzu Iragazki Bat?

Erabili iragazki bat honako kasuetan:

- Ibilbide batek autentifikazioa behar du.
- Ibilbide batek API gako edo token bat baliozkotzea behar du.
- Osagai bat exekutatu aurretik baimenik gabeko erabiltzaileak blokeatu edo birbideratu nahi dituzu.
- Erabiltzailearen, maizterraren edo saioaren informazioa aurrez kargatu nahi duzu.
- Ibilbide anitzek segurtasun-egiaztapen berdinak partekatzen dituzte.

Ez erabili iragazkiak honetarako:

- Modeloaren balidazioa
- Erantzunak formateatzea
- Datu-egitura konplexuak eraikitzea
  (hauek DTOetan edo zerbitzuetan egon beharko lirateke)

---

# 11. Eskaera-fluxu osoa, iragazkiak barne

    Bezeroaren eskaera
          ↓
    Bideratzea → ibilbidea aurkitu da
          ↓
    Iragazkiak exekutatzen dira (ordenan)
          ↓
    Iragazkiren batek huts egiten badu → 403 edo birbideratu
          ↓
    ORequest sortzen da (iragazki-datuekin)
          ↓
    Component::run($dtoOrRequest)
          ↓
    Txantiloiaren errendatzea
          ↓
    Erantzuna
