# Zeregin Arruntak

Dokumentu honek Osumi Framework-eko **ohiko zeregin errealak** eta horiek konpontzeko **gomendatutako (kanonikoa)** modua deskribatzen ditu.

Hainbat ikuspegi posible badira, Osumi Framework-eko irtenbide idiomatikoa bakarrik erakusten da.

Adibide guztiek honako hau suposatzen dute:

- PHP 8.3+
- `declare(strict_types=1);`
- Izen-espazio egokiak

---

# 1. JSON amaiera-puntu sinple bat sortu

## Helburua

JSON `/api/ping`-tik itzuli.

### Ibilbidea

```php
use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\App\Module\Api\Ping\PingComponent;

ORoute::get('/api/ping', PingComponent::class);
```

### Osagaia

```php
class PingComponent extends OComponent {
  public string $status = 'ok';
}
```

### Txantiloia (`PingTemplate.json`)

```json
{
	"status": "{{ status }}"
}
```

---

# 2. Jaso Sarrera DTO bat Erabiliz

## Helburua

Sortu erabiltzaile bat balioztatutako sarrera erabiliz.

### DTO

```php
class CreateUserDTO extends ODTO {
  #[ODTOField(required: true)]
  public ?string $name = null;

  #[ODTOField(required: true)]
  public ?string $email = null;
}
```

### Osagaia

```php
class CreateUserComponent extends OComponent {
  public string $status = 'ok';

  public function run(CreateUserDTO $dto): void {
    if (!$dto->isValid()) {
      $this->status = 'error';
      return;
    }

    $u = new User();
    $u->name = $dto->name;
    $u->email = $dto->email;
    $u->save();
  }
}
```

---

# 3. Babestu amaiera-puntua autentifikazioarekin

## Helburua

Autentifikatutako erabiltzaileek bakarrik sar daitezke `/api/profile`-ra.

### Ibilbidea

```php
ORoute::get('/api/profile', ProfileComponent::class, [LoginFilter::class]);
```

### Sarbide Iragazki Datuak

```php
public function run(ORequest $req): void {
  $login = $req->getFilter('Login');
  $user_id = $login['id'];
}
```

---

# 4. URL Parametro bat Irakurri

## Helburua

Sarbide `/user/:id`.

### Ibilbidea

```php
ORoute::get('/user/:id', UserComponent::class);
```

### Osagaia

```php
public function run(ORequest $req): void {
  $id = $req->getParamInt('id');
  $this->user = User::findOne(['id' => $id]);
}
```

---

# 5. Zerbitzu bat Erabili Osagai Baten Barruan

## Helburua

Negozio logika osagaitik kanpora eraman.

### Zerbitzua

```php
class UserService extends OService {
  public function getAll(): array {
    return User::where([]);
  }
}
```

### Osagaia

```php
class UsersComponent extends OComponent {
  private ?UserService $us = null;
  public array $users = [];

  public function __construct() {
    parent::__construct();
    $this->us = inject(UserService::class);
  }

  public function run(): void {
    $this->users = $this->us->getAll();
  }
}
```

---

# 6. Eredu bat gorde edo eguneratu

## Helburua

Txertatu edo eguneratu automatikoki `save()` erabiliz.

```php
$user = new User();
$user->name = 'Alice';
$user->email = 'alice@mail.com';
$user->save(); // TXERTATU

$user = User::findOne(['id' => 1]);
$user->izena = 'Eguneratutako izena';
$user->save(); // EGUNERATU
```

---

# 7. Itzuli Modeloen Zerrenda bat (JSON)

## Helburua

Erabiltzaileak Modelo Osagai bat erabiliz itzuli.

### Osagaiaren Barruan

```php
public ?UserListComponent $list = null;

public function run(): void {
  $this->list = new UserListComponent();
  $this->list->list = User::where([]);
}
```

### Txantiloia

```json
{
  "users": [
    {{ list }}
  ]
}
```

---

# 8. Fitxategien Igoera Kudeatu

## Helburua

Fitxategi bat modu seguruan igo.

### DTO

```php
class UploadDTO extends ODTO {
  #[ODTOField(required: true)]
  public ?array $file = null;

  public function __construct(ORequest $req) {
    parent::__construct($req);
    $this->file = $req->getFile('file');
  }
}
```

### Osagaia

```php
public function run(UploadDTO $dto): void {
  if (!$dto->isValid()) return;

  $file = $dto->file;
  $dest = $this->getConfig()->getDir('uploads') . basename($file['name']);
  move_uploaded_file($file['tmp_name'], $dest);
}
```

---

# 9. Erabili diseinu pertsonalizatua

## Helburua

Ibilbide talde bati diseinu bat aplikatu.

```php
ORoute::layout(MainLayoutComponent::class, function() {
  ORoute::get('/home', HomeComponent::class);
});
```

Edo konbinatu aurrizkia + diseinua:

```php
ORoute::group('/admin', AdminLayoutComponent::class, function() {
  ORoute::get('/dashboard', DashboardComponent::class);
});
```

Diseinuek errendatutako osagaiaren irteera biltzen dute eta hau jasotzen dute:

- `title`
- `body`

---

# 10. Balidazio erroreak behar bezala kudeatu

## DTO balidazioa

```php
if (!$dto->isValid()) {
  $this->status = 'error';
  $this->errors = $dto->getValidationErrors();
  return;
}
```

## Modeloa Ez Da Aurkitu

```php
$user = User::findOne(['id' => $id]);
if (is_null($user)) {
  $this->status = 'error';
  return;
}
```

---

# Laburpena

Errezeta hauek Osumi Framework-en ohiko zereginak egiteko **modu kanonikoa** erakusten dute:

- Erabili DTOak sarrera baliozkotzeko
- Erabili Iragazkiak autentifikaziorako
- Erabili Zerbitzuak negozio logikarako
- Mantendu Osagaiak meheak
- Erabili Modelo Osagaiak JSON irudikapenerako
- Aplikatu Diseinuak bideratze bidez

Jarraitu eredu hauek aplikazio koherente eta aurreikusgarriak lortzeko.
