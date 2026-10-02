# Ohiko zereginak

Dokumentu honek **Osumi Framework 9.9**-en ohiko zereginak konpontzeko modu kanonikoa erakusten du.

Adibide guztiek honako hau erabiltzen dute:

- PHP 8.5+
- `declare(strict_types=1);`
- Tipatze zorrotza ahal den guztietan

---

# 1. JSON amaiera-puntu sinple bat sortu

```php
ORoute::get(
	'/api/ping',
	PingComponent::class
);
```

```php
class PingComponent extends OComponent {
	public string $status = 'ok';
}
```

```json
{
	"status": {{ status | plain }}
}
```

---

# 2. Sarrera DTO baten bidez jaso

```php
class CreateUserDTO extends ODTO {
	#[ODTOField(required: true)]
	public ?string $name = null;

	#[ODTOField(required: true)]
	public ?string $email = null;
}
```

```php
class CreateUserComponent extends OComponent {
	public string $status = 'ok';

	/**
	 * Create a user from validated input.
	 *
	 * @param CreateUserDTO $dto Request DTO.
	 *
	 * @return void
	 */
	public function run(CreateUserDTO $dto): void {
		if (!$dto->isValid()) {
			$this->status = 'error';
			return;
		}

		$user = new User();
		$user->name = $dto->name;
		$user->email = $dto->email;
		$user->save();
	}
}
```

---

# 3. Amaiera-puntu bat autentifikazioarekin babestu

```php
ORoute::get(
	'/api/profile',
	ProfileComponent::class,
	[
		OMiddleware::PHASE_BEFORE => [
			LoginMiddleware::class
		]
	]
);
```

Argitaratutako balio bat irakurtzeko:

```php
$id_user = $req->getMiddlewareValue(
	'Login',
	'id'
);
```

Edo zuzenean DTO eremu bati esleitzeko:

```php
#[ODTOField(
	required: true,
	middleware: 'Login',
	middlewareProperty: 'id'
)]
public ?int $idUser = null;
```

---

# 4. URL parametro bat irakurri

```php
ORoute::get(
	'/user/:id',
	UserComponent::class
);
```

```php
$id = $req->getParamInt(
	'id'
);
```

---

# 5. Zerbitzu bat osagai batean erabili

```php
class UserService extends OService {
	/**
	 * Return all users.
	 *
	 * @return array<int, User> Users.
	 */
	public function getAll(): array {
		return User::where([]);
	}
}
```

---

# 6. Modelo bat gorde edo eguneratu

```php
$user = new User();
$user->name = 'Alice';
$user->email = 'alice@mail.com';
$user->save();

$user = User::findOne([
	'id' => 1
]);

if ($user !== null) {
	$user->name = 'Eguneratutako izena';
	$user->save();
}
```

---

# 7. Modelo-zerrenda bat itzuli

```php
public ?UserListComponent $list = null;
```

```php
/**
 * Load users.
 *
 * @return void
 */
public function run(): void {
	$this->list = new UserListComponent();
	$this->list->list = User::where([]);
}
```

---

# 8. Fitxategien igoerak kudeatu

Erabili `ORequest::getFile()` igotako fitxategia lortzeko eta baliozkotu mugitu aurretik.

Ikusi `recipes/uploads.md` adibide osoetarako.

---

# 9. Layout pertsonalizatu bat erabili

```php
ORoute::layout(
	MainLayoutComponent::class,
	static function (): void {
		ORoute::get(
			'/home',
			HomeComponent::class
		);
	}
);
```

Middlewareak `layout()`, `prefix()` eta `group()` bidez sortutako ibilbide-taldeei ere aplika dakizkieke.

---

# 10. Balidazio-erroreak kudeatu

```php
if (!$dto->isValid()) {
	$this->status = 'error';
	$this->errors = $dto->getValidationErrors();

	return;
}
```

---

# Laburpena

9.9ko eredu kanonikoak:

- DTOak sarrera tipatu eta balioztaturako.
- Middlewareak eskaera/erantzun logika zeharkakorako.
- Middleware testuingurua zerbitzariaren konfiantzazko balioetarako.
- Zerbitzuak negozio-logikarako.
- Osagai arinak orkestraziorako.
- Modelo-osagaiak irudikapenerako.
- Routing bidez konfiguratutako layout-ak.
