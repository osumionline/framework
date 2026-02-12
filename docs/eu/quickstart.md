# Hasiberrientzako Gida Azkarra

Gida honek **Osumi Framework** proiektu berri bat sortzen, token plugina instalatzen, CLIarekin ekintzak eta iragazkiak sortzen, eredu bat sortzen, ibilbideak definitzen eta osagaia eta txantiloia aldatzen lagunduko dizu, funtzionalki autentifikatutako API amaiera-puntua eraikitzeko.

Gida honen amaieran hau izango duzu:

- Osumi Framework aplikazio berri bat
- OToken plugina instalatuta
- Funtzionala den LoginFilter bat
- `User` eredu bat
- JSON itzultzen duen `/api/get-users` amaiera-puntua autentifikatuta

---

# 1. Sortu Proiektu Berri Bat

Exekutatu komando hau Osumi Framework proiektu berri bat sortzeko:

```bash
composer create-project osumionline/new myapp
```

Honek karpeta-egitura oso bat sortuko du osagai, ibilbide, eredu eta abarren adibideekin.

---

# 2. Instalatu OToken Plugina

OToken pluginak JWT antzeko tokenak sortu eta balioztatzeko aukera ematen dizu.

Instalatu Composer bidez:

```bash
composer require osumionline/plugin-token
```

Instalazio ondoren, zure aplikazioak hau erabil dezake:

```php
use Osumi\OsumiFramework\Plugins\OToken;
```

---

# 3. Kendu adibide datuak

Proiektu berri guztiek adibide moduluak, osagaiak, ibilbideak eta modeloak dituzte.
Guztiak garbitu ditzakezu honekin:

```bash
php of reset
```

Honek framework egitura mantentzen du baina adibide funtzionalitate guztiak kentzen ditu.

---

# 4. Sortu ekintza berri bat (osagaia)

Erabili CLI API amaierako puntu gisa balioko duen ekintza osagai berri bat sortzeko.

```bash
php of add --option action --name api/getUsers --url /api/get-users --type json
```

Honek sortzen du:

- `/src/App/Module/Api/GetUsers/GetUsersComponent.php`
- `/src/App/Module/Api/GetUsers/GetUsersTemplate.json`
- Ibilbidearen definizio bat routes karpetan (ibilbide automatikoak sortzea desgaitzen ez baduzu behintzat)

---

# 5. Sortu Saioa Hasteko Iragazki bat

Orain sortu iragazki bat CLI erabiliz:

```bash
php of add --option filter --name login
```

Honek sortzen du:

- `/src/App/Filter/LoginFilter.php`

Sortutako fitxategia _zure benetako LoginFilter_ inplementazioarekin ordezkatu behar duzu orain:

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Filter;

use Osumi\OsumiFramework\Plugins\OToken;

class LoginFilter {
  /**
   * Segurtasun iragazkia erabiltzaileentzat
   */
  public static function handle(array $params, array $headers): array {
    global $core;
    $ret = ['status'=>'error', 'id'=>null];

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
- Tokena baliozkotzen du konfiguratutako sekretua erabiliz
- `"status" => "ok"` itzultzen du tokena baliozkoa denean bakarrik
- `id` txertatzen du osagaiek zein erabiltzaile den autentifikatuta jakin dezaten

---

# 6. Sortu `Erabiltzaile` Eredua

Ereduak eskuz sortzen dira.

`src/App/Model/User.php` barruan honelako zerbait sortu:

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Model;

use Osumi\OsumiFramework\ORM\OModel;
use Osumi\OsumiFramework\ORM\OPK;
use Osumi\OsumiFramework\ORM\OField;
use Osumi\OsumiFramework\ORM\OCreatedAt;
use Osumi\OsumiFramework\ORM\OUpdatedAt;

class User extends OModel {
  #[OPK(
    comment: "Erabiltzaile baten ID bakarra"
  )]
  public ?int $id = null;

  #[OField(
    comment: "Erabiltzailearen izena",
    max: 100,
    nullable: false
  )]
  public ?string $name = null;

  #[OField(
    comment: "Erabiltzailearen helbide elektronikoa",
    max: 100,
    nullable: false
  )]
  public ?string $email = null;

  #[OCreatedAt(
    comment: "Erregistroaren sorrera data"
  )]
  public ?string $created_at = null;

  #[OUpdatedAt(
    comment: "Erregistroaren azken eguneratze data"
  )]
  public ?string $updated_at = null;
}
```

Eremuak zure beharren arabera doi ditzakezu.

---

# 7. API Ibilbidea Sortu

Sortu fitxategia (automatikoki sortu ez bada):

    /src/Routes/Api.php

Gehitu aurrizki bat etorkizuneko amaierako puntuak garbi taldekatzeko eta aplikatu LoginFilter API ibilbidea babesteko:

```php
<?php declare(strict_types=1);

use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\App\Filter\LoginFilter;
use Osumi\OsumiFramework\App\Module\Api\GetUsers\GetUsersComponent;

ORoute::prefix('/api', function() {
  ORoute::get('/get-users', GetUsersComponent::class, [LoginFilter::class]);
});
```

Orain `/api/get-users`-erako edozein deik `Authorization` token baliodun bat izan behar du.

# 8. Sortu Eredu Osagai bat

Eredu osagaiak erabiltzaile eredu bat JSON eran irudikatzen duten osagaiak dira. Sortu Eredu Osagai bat Erabiltzaile Eredu klasearentzat:

```bash
php of add --option modelComponent --name se
```

Eredu Osagai bat sortzen denean, 2 osagai sortzen dira:

    /src/App/Component/Model/User/UserComponent.php
    /src/App/Component/Model/User/UserTemplate.php
    /src/App/Component/Model/UserList/UserListComponent.php
    /src/App/Component/Model/UserList/UserListTemplate.php

Osagai hauek erabiliz, datu-basea erabiltzaile bakar bat edo erabiltzaile multzo bat kontsultatu dezakezu eta haien datuak erraz bistara ditzakezu.

Editatu `UserTemplate.php` fitxategia behar duzuna gehitzeko edo kentzeko, adibidez, sortzeko/eguneratzeko datak kendu:

```php
<?php if (is_null($user)): ?>
null
<?php else: ?>
{
	"id": {{ user.id }},
	"name": {{ user.name | string }},
  "email": {{ email.name | string }}
}
<?php endif ?>

```

---

# 9. Sortutako osagaia aldatu

Ireki:

    /src/App/Module/Api/GetUsers/GetUsersComponent.php

Aldatu honela:

1. LoginFilter irteera irakurtzen du
2. Autentifikatutako erabiltzaile IDa erabiltzen du
3. Erabiltzaileen taula kontsultatzen du
4. JSON txantiloira pasatzen ditu

Adibidea:

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Api\GetUsers;

use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Web\ORequest;
use Osumi\OsumiFramework\App\Model\User;
use Osumi\OsumiFramework\App\Component\Model\UserList\UserListComponent;

class GetUsersComponent extends OComponent {
  public string $status = 'ok';
  public ?UserListComponent $list = null;

  public function run(ORequest $req): void {
    $filter = $req->getFilter('Login');
    $this->list = new UserListComponent();

    if (is_null($filter) || !array_key_exists('id', $filter)) {
      $this->status = 'error';
      $this->list->list = [];
      return;
    }

    // Adibidea: erabiltzaile guztiak lortu (edo behar izanez gero, autentifikatutako erabiltzaile IDaren arabera iragazi)
    $this->list->list = User::where([]);
  }
}
```

---

# 9. JSON txantiloia aldatu

Ireki:

    /src/App/Module/Api/GetUsers/GetUsersTemplate.json

Edukia honekin ordezkatu:

```json
{
  "status": "{{ status }}",
  "users": [
    {{ list }}
  ]
}
```

Txantiloia:

- `"egoera"` irteeratzen du
- Erabiltzaileen datuetan zehar begiztatzen du
- Azpi-osagaiak erabiliz haien datuak bistaratzen ditu
- JSON array bat eraikitzen du

---

# 10. Amaiera-puntua probatzen

Zure APIa deitzeko:

1. Sortu token baliozko bat (zure saioa hasteko amaiera-puntua erabiliz edo eskuzko OToken sorrera erabiliz)
2. Bidali eskaera bat:

```bash
curl -X GET http://localhost:8000/api/get-users \
-H "Authorization: ZURE_TOKENA_HEMEN"
```

Tokena baliozkoa bada, hau jasoko duzu:

```json
{
	"status": "ok",
	"users": [
		{ "id": 1, "name": "Alice", "email": "alice@mail.com" },
		{ "id": 2, "name": "Bob", "email": "bob@mail.com" }
	]
}
```

Baldin eta Tokena baliogabea da edo falta da:

```json
{
	"status": "error",
	"users": []
}
```

---

# 11. Laburpena

Hasierako gida honek honako hauek jorratu ditu:

- Osumi proiektu berri bat sortzea
- OToken instalatzea
- Demo datuak kentzea
- API ekintza berri bat sortzea
- LoginFilter bat sortzea
- Erabiltzaile eredu bat idaztea
- Autentifikatutako ibilbide bat gehitzea
- Eredu osagai bat sortzea
- Osagaia eta txantiloia aldatzea

Orain oinarri bat duzu Osumi Framework-en autentifikazio laguntza duten APIak eraikitzeko.
