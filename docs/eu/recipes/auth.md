# Autentifikazioa (Auth) — Errezetak eta Praktika Onenak

**Osumi Framework**-en autentifikazioa normalean honako hauek erabiliz ezartzen da:

- Erabiltzailearen kredentzialak balioztatzen dituen eta **token** bat jaulkitzen duen **Saioa hasteko amaiera-puntu bat**
- Babestutako ibilbide guztietan tokena balioztatzen duen **iragazki bat** (adibidez, `LoginFilter`)
- Sarrerako autentifikazio-datuak modu seguruan prozesatzeko **DTOak**
- Autentifikazio-logika berrerabilgarria eta garbia mantentzeko **Zerbitzuak**
- Osagaiak exekutatu aurretik autentifikazio-iragazkiak aplikatzeko konfiguratutako **Ibilbideak**

Dokumentu honek autentifikazio-fluxu oso bat ezartzeko errezeta praktikoak eskaintzen ditu.

---

# 1. Ibilbideak Iragazkiak Erabiliz Babestea

Amaiera-puntuak babesteko metodo ohikoena ibilbidearen definizioari iragazki bat gehitzea da.

Zure bideratze-sistemaren arabera, iragazkiak honela zehaztu daitezke:

```php
ORoute::post('/profile', ProfileComponent::class, [LoginFilter::class]);
```

Ibilbidea atzitzen denean:

1. Bideratzaileak amaiera-puntua identifikatzen du
2. Osagaia exekutatu aurretik, iragazki-katea exekutatzen da
3. Iragazkiren batek `"status" !== "ok"` itzultzen badu, eskaerak **ez du inoiz osagaira iristen**, **403 Debekatuta** itzuliz edo `"return"` ezarrita badago birbideratuz

Horrek ziurtatzen du autentifikatutako erabiltzaileek bakarrik iristen direla babestutako logikara.

---

# 2. Saioa Hasteko Iragazkia Sortzea

Iragazki batek honelako itxura du:

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

Iragazki honek:

- `Authorization` goiburua irakurtzen du
- Token bat balioztatzen du
- Baliozkoa bada → `"status" => "ok"` eta autentifikatutako erabiltzaile IDa itzultzen ditu
- Baliogabezkoa bada → `"error"` itzultzen du eta eskaera gelditzen du

Tokenetik eratorritako balioak (`id` bezala) geroago osagaiek edo DTOek kontsumi ditzakete.

---

# 3. Saioa Hasteko Amaierako Puntua Sortzea (Tokenak Jaulkitzea):

1. Kredentzialak DTO baten bidez jasotzen ditu
2. Zerbitzu bat erabiliz baliozkotzen ditu
3. Token bat sortzen du
4. Tokena bezeroari itzultzen dio
5. Bezeroak tokena gordetzen du eta `Authorization` goiburuan erabiltzen du

### Egitura Adibidea

**Saioa Hasteko DTO:**

```php
class LoginDTO extends ODTO {
  #[ODTOField(required: true)]
  public ?string $email = null;

  #[ODTOField(required: true)]
  public ?string $password = null;
}
```

**AuthService logika kudeatzen du:**

```php
class AuthService extends OService {
  public function login(string $email, string $password): ?array {
    $user = User::findOne(['email' => $email]);
    if (!$user || !password_verify($password, $user->password)) {
      return null;
    }
    $token = new OToken($this->getConfig()->getExtra('secret'));
    $token->addParam('id', $user->id);
    return ['token' => $token->getToken()];
  }
}
```

**LoginComponent:**

```php
class LoginComponent extends OComponent {
  private ?AuthService $auth = null;
  public ?string $token = null;
  public string $status = 'error';

  public function __construct() {
    parent::__construct();
    $this->auth = inject(AuthService::class);
  }

  public function run(LoginDTO $dto): void {
    if (!$dto->isValid()) {
      return;
    }

    $data = $this->auth->login($dto->email, $dto->password);

    if ($data) {
      $this->status = 'ok';
      $this->token = $data['token'];
    }
  }
}
```

Bezeroak orain tokena sartzen du ondorengo eskaera guztietan:

    Baimena: <token>

---

# 4. Iragazki Irteera Erabiltzea Osagaienetan

Iragazkiak gainditu ondoren, eskaera objektuak iragazki emaitzak ditu:

```php
$filter = $req->getFilter('Login');
```

Normalean hau egingo zenuke:

```php
$userId = $filter['id']; // autentifikatutako erabiltzailea
```

Ondoren, IDa zerbitzuei pasa diezaiekezu, ereduak kargatu eta negozio logika modu seguruan egin.

---

# 5. Iragazki Datuak Erabiltzea DTOen Barruan

DTOek automatikoki jaso ditzakete balioak iragazkietatik:

```php
#[ODTOField(filter: 'Login', filterProperty: 'id')]
public ?int $idUser = null;
```

Horrek esan nahi du:

- Erabiltzaileek ezin dute beren identitatea faltsutu
- DTOek autentifikatutako erabiltzaile IDa modu seguruan jasotzen dute
- Osagaiek ez dute iragazki datuak eskuz irakurri beharrik

Honek asko errazten ditu autentifikazioaren menpeko amaiera-puntuak.

---

# 6. Errezeta: Babestutako amaiera-puntu bat sortzea

Adibidea: “Lortu nire zinemak”

### Ibilbidea

```php
ORoute::get('/nire-zinemak', GetCinemasComponent::class, [LoginFilter::class]);
```

### Osagaia

```php
class GetCinemasComponent extends OComponent {
  private ?CinemaService $cs = null;
  public string $status = 'ok';
  public ?CinemaListComponent $list = null;

  public function __construct() {
    parent::__construct();
    $this->cs = inject(CinemaService::class);
    $this->list = new CinemaListComponent();
  }

  public function run(ORequest $req): void {
    $filter = $req->getFilter('Login');

    if (!$filter || !array_key_exists('id', $filter)) {
      $this->status = 'error';
      return;
    }

    $this->list->list = $this->cs->getCinemas($filter['id']);
  }
}
```

---

# 7. Errezeta: Baimenak Betearaztea

Zure iragazkia zabaldu dezakezu tokenetik rol/baimen informazioa sartzeko:

```php
$ret['role'] = $tk->getParam('role');
```

Ondoren, osagaietan:

```php
$filter = $req->getFilter('Login');
if ($filter['role'] !== 'admin') {
  $this->status = 'forbidden';
  return;
}
```

---

# 8. Errezeta: Saioa ixtea

Zure autentifikazio sistema tokenetan oinarrituta eta egoerarik gabekoa denez:

- "Saioa ixtea" bezero aldeko tokena ezabatzea besterik ez da
- Aukeran, **token zerrenda beltza** inplementa dezakezu cachea erabiliz:
    - Markatu tokena baliogabetzat `getCacheContainer()`-n
    - Iragazki bidez egiaztatu zerrenda beltzean dauden tokenak

---

# 9. Praktika onenak

- **Erabili DTOak** saioa hasteko eskaeretarako
- **Ez fidatu inoiz bezeroak emandako erabiltzaile IDetan** — lortu beti IDak iragazkietatik
- **Mantendu iragazkiak txikiak** (balioztatzeko soilik)
- **Jarri negozio logika zerbitzuetan**
- **Erabili sekretu sendoak** tokenetarako (gorde konfigurazioan)
- **Zatitu logika garbi**:
    - Iragazkiak → autentifikazioa / egiaztapena
    - DTOak → sarreraren balidazioa
    - Zerbitzuak → logika
    - Osagaiak → orkestrazioa eta erantzuna

---

# 10. Laburpena

Osumi Framework-en autentifikazio lan-fluxu oso bat normalean Honako hauek barne hartzen ditu:

1. **Saioa hasteko amaierako puntua** tokenak jaulkitzea
2. **LoginFilter** tokenak balioztatzea bide babestuetarako
3. **DTOak** sarrera jasotzea eta balioztatzea
4. **Zerbitzuak** autentifikazio logika burutzea
5. **Bideratzea** iragazkiak osagaien aurretik aplikatzea
6. **Autentifikatutako erabiltzaileen informazioaren hedapen segurua** iragazkien eta DTOen bidez

Arkitektura honek honako hau bermatzen du:

- Ardurak garbi bereiztea
- Amaiera-puntuen artean berrerabiltzea erraza
- Segurtasun berme sendoak
- Eskaera-hodi sinple eta aurreikusgarria
