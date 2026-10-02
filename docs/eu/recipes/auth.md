# Autentifikazioa — Errezetak eta praktika onak

**Osumi Framework 9.9**-en autentifikazioa normalean honako hauekin eraikitzen da:

- Kredentzialak baliozkotu eta token bat sortzen duen login amaiera-puntu bat.
- Babestutako ibilbideetan tokena baliozkotzen duen `before` Middleware bat.
- Autentifikatutako konfiantzazko balioetarako Middleware testuingurua.
- Sarrera balioztatzeko eta Middlewaretik datozen konfiantzazko eremuetarako DTOak.
- Autentifikazio- eta baimen-logika berrerabilgarrirako zerbitzuak.

---

# 1. Ibilbideak Middleware bidez babestea

Adibidea:

```php
ORoute::get(
	'/profile',
	ProfileComponent::class,
	[
		OMiddleware::PHASE_BEFORE => [
			LoginMiddleware::class
		]
	]
);
```

Ibilbidea bat datorrenean:

1. `before` Middlewareak exekutatzen dira.
2. `LoginMiddleware`-k eskaera baliozkotzen du.
3. `stop => true` itzultzen badu, gainerako `before` Middlewareak eta osagaia ez dira exekutatzen.
4. `afterResponse` exekutatzen jarraitzen da.
5. Framework-ak errore-erantzun tipatua bidaltzen du.

Autentifikazio-hutsegiteek normalean HTTP 401 erabili behar dute.

---

# 2. Login Middleware

Login Middleware batek konfiantzazko testuingurua argitaratu behar du baliozkotzea zuzena denean.

```php
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Middleware;

use Osumi\OsumiFramework\Core\OMiddleware;

final class LoginMiddleware {
	/**
	 * Validate authentication and publish user context.
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

		// Authorization tokena hemen baliozkotu.

		$is_valid = true;
		$id_user = 42;
		$role = 'admin';

		if (!$is_valid) {
			return [
				'stop' => true,
				'status_code' => 401,
				'message' => 'Unauthorized'
			];
		}

		return [
			'context' => [
				'id' => $id_user,
				'role' => $role
			]
		];
	}
}
```

`LoginMiddleware` publikoki `Login` gisa azaltzen da.

---

# 3. Login amaiera-puntua sortzea

Login amaiera-puntu batek normalean:

1. Kredentzialak DTO baten bidez jasotzen ditu.
2. Zerbitzu baten bidez baliozkotzen ditu.
3. Token bat sortzen du.
4. Tokena bezeroari itzultzen dio.
5. Bezeroak tokena `Authorization` bidez bidaltzen du.

DTO adibidea:

```php
class LoginDTO extends ODTO {
	#[ODTOField(required: true)]
	public ?string $email = null;

	#[ODTOField(required: true)]
	public ?string $password = null;
}
```

Autentifikazio-zerbitzua:

```php
class AuthService extends OService {
	/**
	 * Validate credentials and return a token.
	 *
	 * @param string $email User email.
	 * @param string $password Plain-text password.
	 *
	 * @return array{token: string}|null Token data or null on failure.
	 */
	public function login(
		string $email,
		string $password
	): ?array {
		$user = User::findOne([
			'email' => $email
		]);

		if (
			$user === null ||
			!password_verify(
				$password,
				$user->password
			)
		) {
			return null;
		}

		$token = new OToken(
			$this->getConfig()->getExtra('secret')
		);

		$token->addParam(
			'id',
			$user->id
		);

		return [
			'token' => $token->getToken()
		];
	}
}
```

---

# 4. Middleware testuingurua osagaietan irakurtzea

Testuinguru osoa lortzeko:

```php
$login = $req->getMiddleware(
	'Login'
);
```

Balio bakarra lortzeko:

```php
$id_user = $req->getMiddlewareValue(
	'Login',
	'id'
);
```

Balio bakarra behar denean `getMiddlewareValue()` erabiltzea gomendatzen da.

---

# 5. Middleware testuingurua DTOetan erabiltzea

DTOek Middleware testuingurua iturburu esplizitu gisa erabil dezakete:

```php
#[ODTOField(
	required: true,
	middleware: 'Login',
	middlewareProperty: 'id'
)]
public ?int $idUser = null;
```

Horrek bezeroak autentifikatutako erabiltzailearen IDa faltsutzea saihesten du.

Middleware testuinguruko balioa falta bada, DTOaren balioa `null` izaten jarraitzen du; ez da bezeroaren sarrerara itzultzen.

---

# 6. DTO bat erabiltzen duen babestutako amaiera-puntua

Ibilbidea:

```php
ORoute::get(
	'/my-cinemas',
	GetCinemasComponent::class,
	[
		OMiddleware::PHASE_BEFORE => [
			LoginMiddleware::class
		]
	]
);
```

DTOa:

```php
class GetCinemasDTO extends ODTO {
	#[ODTOField(
		required: true,
		middleware: 'Login',
		middlewareProperty: 'id'
	)]
	public ?int $idUser = null;
}
```

Osagaia:

```php
class GetCinemasComponent extends OComponent {
	private ?CinemaService $cinema_service = null;

	/**
	 * Prepare the component.
	 */
	public function __construct() {
		parent::__construct();

		$this->cinema_service = inject(
			CinemaService::class
		);
	}

	/**
	 * Load cinemas for the authenticated user.
	 *
	 * @param GetCinemasDTO $dto Authenticated request DTO.
	 *
	 * @return void
	 */
	public function run(GetCinemasDTO $dto): void {
		if (
			!$dto->isValid() ||
			$dto->idUser === null
		) {
			return;
		}

		$this->list = $this->cinema_service->getCinemas(
			$dto->idUser
		);
	}
}
```

---

# 7. Baimenak

Middleware batek rol edo baimen-informazioa argitara dezake:

```php
return [
	'context' => [
		'id' => 42,
		'role' => 'admin'
	]
];
```

Osagai batek informazio hori kontsulta dezake:

```php
$role = $req->getMiddlewareValue(
	'Login',
	'role'
);
```

Ibilbide askok partekatzen dituzten baimen-arauetarako, hobe da baimenetarako Middleware bereizi bat erabiltzea osagaietan egiaztapenak errepikatzea baino.

---

# 8. Saioa ixtea

Tokenetan oinarritutako autentifikazio stateless batean, saioa ixtea normalean bezeroan tokena ezabatzea da.

Zerbitzarian baliogabetzea behar bada, gorde baliogabetutako token-identifikatzaileak zerbitzu edo cache batean eta egin autentifikazio Middlewareak horiek baztertzea.

---

# 9. Praktika onak

- Erabili `before` Middlewareak autentifikaziorako.
- Itzuli HTTP 401 autentifikazioak huts egiten duenean.
- Argitaratu behar den konfiantzazko testuingurua bakarrik.
- Ez fidatu inoiz bezeroak bidalitako autentifikatutako erabiltzaile-IDez.
- Erabili Middleware iturburuak DTOetan autentifikatutako balioetarako.
- Mantendu negozio-logika zerbitzuetan.
- Erabili baimenetarako Middleware bereizi bat baimenak hainbat ibilbidetan partekatzen direnean.
- Mantendu token sekretuak konfigurazioan.

---

# 10. Laburpena

Ohiko autentifikazio-fluxu batek honako hauek ditu:

1. Login amaiera-puntua.
2. Tokenak sortzen dituen zerbitzua.
3. `LoginMiddleware` babestutako ibilbideetan.
4. Konfiantzazko Middleware testuingurua.
5. Testuinguru hori erabiltzen duten DTOak eta/edo `ORequest`.
6. Negozio-logika duten zerbitzuak.

Hau da 9.9ko ordezko kanonikoa Filters zaharretan oinarritutako autentifikazio-fluxuarentzat.
