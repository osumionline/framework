# Hasiera azkarreko gida

Gida honek **Osumi Framework 9.9** proiektu berri bat sortzen, token plugina instalatzen, CLI bidez ekintza bat eta Middleware bat sortzen, modelo bat definitzen, babestutako ibilbide bat konfiguratzen eta JSON erantzuna sortzen erakusten du.

Amaitzean honako hau izango duzu:

- Osumi Framework aplikazio berri bat
- OToken plugina instalatuta
- Funtzionatzen duen `LoginMiddleware`
- `User` modelo bat
- JSON itzultzen duen `/api/get-users` amaiera-puntu autentifikatu bat

Adibide guztiek PHP 8.5+ eta `declare(strict_types=1);` erabiltzen dituzte.

---

# 1. Proiektu berri bat sortu

```bash
composer create-project osumionline/new myapp
```

---

# 2. OToken plugina instalatu

```bash
composer require osumionline/plugin-token
```

Ondoren aplikazioak hau erabil dezake:

```php
use Osumi\OsumiFramework\Plugins\OToken;
```

---

# 3. Adibide-datuak ezabatu

```bash
php of reset
```

Honek aplikazioaren egitura estandarra mantentzen du eta adibide-funtzionalitatea ezabatzen du.

---

# 4. Ekintza berri bat sortu

```bash
php of add --option action --name api/getUsers --url /api/get-users --type json
```

Honek honako direktorio honen barruan fitxategiak sortzen ditu:

```text
src/Api/GetUsers/
```

besteak beste:

```text
src/Api/GetUsers/GetUsersComponent.php
src/Api/GetUsers/GetUsersTemplate.json
```

eta, desgaitzen ez bada, dagokion ibilbide-sarrera.

Sortutako namespace-a hau da:

```php
namespace Osumi\OsumiFramework\App\Api\GetUsers;
```

---

# 5. Login Middleware bat sortu

Sortu Middlewarea:

```bash
php of add --option middleware --name Login
```

Honek hau sortzen du:

```text
src/Middleware/LoginMiddleware.php
```

Ordeztu edukia zure aplikazioaren autentifikazio-logikarekin.

Adibidea:

```php
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Middleware;

use Osumi\OsumiFramework\Core\OCore;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Plugins\OToken;

final class LoginMiddleware {
	/**
	 * Validate the Authorization token and publish the authenticated user ID.
	 *
	 * @param string $phase Current middleware phase.
	 * @param array<string, mixed> $data Current middleware pipeline data.
	 *
	 * @return array<string, mixed> Middleware result.
	 */
	public static function handle(
		string $phase,
		array $data
	): array {
		if ($phase !== OMiddleware::PHASE_BEFORE) {
			return [];
		}

		global $core;

		if (
			!($core instanceof OCore) ||
			$core->config === null
		) {
			return [
				'stop' => true,
				'status_code' => 500,
				'message' => 'Framework configuration is not available.'
			];
		}

		$headers = $data['headers'] ?? [];

		if (!is_array($headers)) {
			return [
				'stop' => true,
				'status_code' => 401,
				'message' => 'Unauthorized'
			];
		}

		$normalized_headers = array_change_key_case(
			$headers,
			CASE_LOWER
		);

		$authorization = $normalized_headers['authorization']
			?? null;

		if (
			!is_string($authorization) ||
			$authorization === ''
		) {
			return [
				'stop' => true,
				'status_code' => 401,
				'message' => 'Unauthorized'
			];
		}

		$secret = $core->config->getExtra(
			'secret'
		);

		if (
			!is_string($secret) ||
			$secret === ''
		) {
			return [
				'stop' => true,
				'status_code' => 500,
				'message' => 'Token secret is not configured.'
			];
		}

		$token = new OToken(
			$secret
		);

		if (!$token->checkToken($authorization)) {
			return [
				'stop' => true,
				'status_code' => 401,
				'message' => 'Unauthorized'
			];
		}

		return [
			'context' => [
				'id' => (int) $token->getParam('id')
			]
		];
	}
}
```

Middleware honek:

- `before` fasean exekutatzen da.
- `Authorization` goiburua irakurtzen du.
- Tokena baliozkotzen du.
- Autentifikazioak huts egiten badu eskaera HTTP 401 egoerarekin gelditzen du.
- Autentifikatutako erabiltzailearen IDa `Login.id` gisa argitaratzen du.

---

# 6. `User` modeloa sortu

Sortu:

```text
src/Model/User.php
```

Adibidea:

```php
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Model;

use Osumi\OsumiFramework\ORM\OCreatedAt;
use Osumi\OsumiFramework\ORM\OField;
use Osumi\OsumiFramework\ORM\OModel;
use Osumi\OsumiFramework\ORM\OPK;
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
		comment: "Erabiltzailearen emaila",
		max: 100,
		nullable: false
	)]
	public ?string $email = null;

	#[OCreatedAt(
		comment: "Erregistroaren sorrera-data"
	)]
	public ?string $created_at = null;

	#[OUpdatedAt(
		comment: "Erregistroaren eguneratze-data"
	)]
	public ?string $updated_at = null;
}
```

---

# 7. API ibilbidea babestu

Sortu edo editatu:

```text
src/Routes/Api.php
```

Adibidea:

```php
<?php

declare(strict_types=1);

use Osumi\OsumiFramework\App\Api\GetUsers\GetUsersComponent;
use Osumi\OsumiFramework\App\Middleware\LoginMiddleware;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Routing\ORoute;

ORoute::get(
	'/api/get-users',
	GetUsersComponent::class,
	[
		OMiddleware::PHASE_BEFORE => [
			LoginMiddleware::class
		]
	]
);
```

Osagaia Middlewareak `stop` egin gabe amaitzen denean bakarrik exekutatzen da.

---

# 8. Modelo-osagai bat sortu

```bash
php of add --option modelComponent --name User
```

Honek honako hauek sortzen ditu:

```text
src/Component/Model/User/UserComponent.php
src/Component/Model/User/UserTemplate.php
src/Component/Model/UserList/UserListComponent.php
src/Component/Model/UserList/UserListTemplate.php
```

---

# 9. Sortutako ekintza aldatu

Ireki:

```text
src/Api/GetUsers/GetUsersComponent.php
```

Adibidea:

```php
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Api\GetUsers;

use Osumi\OsumiFramework\App\Component\Model\UserList\UserListComponent;
use Osumi\OsumiFramework\App\Model\User;
use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Web\ORequest;

class GetUsersComponent extends OComponent {
	public string $status = 'ok';
	public ?UserListComponent $list = null;

	/**
	 * Load users for an authenticated request.
	 *
	 * @param ORequest $req Current request.
	 *
	 * @return void
	 */
	public function run(ORequest $req): void {
		$id_user = $req->getMiddlewareValue(
			'Login',
			'id'
		);

		$this->list = new UserListComponent();

		if (!is_int($id_user)) {
			$this->status = 'error';
			$this->list->list = [];

			return;
		}

		$this->list->list = User::where([]);
	}
}
```

`LoginMiddleware`-k argitaratutako balioa honela eskuratzen da:

```php
$req->getMiddlewareValue(
	'Login',
	'id'
);
```

---

# 10. JSON txantiloia aldatu

Ireki:

```text
src/Api/GetUsers/GetUsersTemplate.json
```

Adibidea:

```json
{
	"status": {{ status | plain }},
	"users": [
		{{ list }}
	]
}
```

Erabili `plain` pipe-a jatorrizko katea URL bidez kodetu gabe mantendu nahi denean.

---

# 11. Amaiera-puntua probatu

```bash
curl -X GET http://localhost:8000/api/get-users \
	-H "Authorization: ZURE_TOKENA_HEMEN"
```

Tokena baliozkoa bada, babestutako osagaia exekutatzen da.

Tokena falta bada edo baliogabea bada, `LoginMiddleware`-k eskaera gelditzen du eta framework-ak HTTP 401 egoerarekin JSON errore-erantzun tipatua itzultzen du.

---

# 12. Laburpena

Gida honetan honako hauek landu dira:

- Proiektu bat sortzea.
- OToken instalatzea.
- Adibide-datuak ezabatzea.
- API ekintza bat sortzea.
- Middleware natibo bat sortzea.
- Autentifikazio-testuingurua argitaratzea.
- Ibilbide bat `before` bidez babestea.
- Middleware testuingurua `ORequest` bidez irakurtzea.
- Modelo bat eta modelo-osagai bat sortzea.
- JSON itzultzea.

Hau da 9.9ko autentifikazio-fluxuaren oinarria, Middlewareak erabiliz Filter zaharren ordez.
