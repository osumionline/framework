# Osagaiak

Osumi Framework-eko osagaiak txantiloi bat errendatzen duten kode-zati berrerabilgarriak dira. Osagai batek honako hauek ditu:

- `OComponent` hedatzen duen PHP klase bat.
- Txantiloi-fitxategi bat (`php`, `html`, `json` edo `xml`, erabileraren arabera).

Osagaiaren instantzia sortu, propietateak esleitu eta ondoren errendatzen da.

---

## Osagai baten oinarrizko egitura

### Osagai-klasea

```php
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Email\LostPassword;

use Osumi\OsumiFramework\Core\OComponent;

class LostPasswordComponent extends OComponent {
	public ?string $token = null;
}
```

### Txantiloi-fitxategia

```html
<div>
	Token: {{ token }}
</div>
```

---

## Ezaugarri aurreratuak

### Content-Type goiburu automatikoak

Osagai bat URL baten ekintza nagusi gisa erabiltzen denean, framework-ak automatikoki bidaltzen du dagokion `Content-Type`, txantiloiaren luzapenaren arabera:

- `.json`: `application/json`.
- `.xml`: `application/xml`.
- `.html` / `.php`: `text/html`.

### Osagaien habiaratzea

Osagaiak habiaratu daitezke berrerabilpena sustatzeko.

```php
<?php

declare(strict_types=1);

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

### Txantiloi-sintaxia

1. **PHP txantiloiek (`.php`)** PHP exekuta dezakete eta propietate publikoak aldagai gisa atzitu.
2. **Txantiloi estatiko/egituratuek (`.html`, `.json`, `.xml`)** `{{ variable_name }}` erabiltzen dute.

---

## `run()` metodoa

Osagai batek aukerako `run()` metodoa defini dezake.

Ibilbide-ekintza gisa erabiltzen denean, honako sinadura hauek bakarrik onartzen dira:

```php
public function run(): void
```

```php
public function run(ORequest $req): void
```

```php
public function run(MyDTO $dto): void
```

Portaera:

- `run()`-ek ez du eskaera-daturik jasotzen.
- `run(ORequest $req)`-ek uneko eskaera jasotzen du.
- `run(MyDTO $dto)`-k uneko eskaeratik betetako DTO bat jasotzen du. `MyDTO`-k `ODTO` hedatu behar du.
- DTOak `ODTO`-ren herentziaren bidez identifikatzen dira, ez namespace-aren bidez.
- Ez da beste sinadurarik onartzen.
- Parametro bakarra, baldin badago, ezin da nullable izan.

`ORequest`-ek parametro tipatuetarako metodoak eskaintzen ditu:

- `getParamString('name')`
- `getParamInt('name')`
- `getParamFloat('name')`
- `getParamBool('name')`

### Middleware testuingurua

`ORequest`-ek exekutatutako Middlewareek argitaratutako testuingurua ere eskaintzen du.

Middleware batek argitaratutako testuinguru osoa lortzeko:

```php
public function run(ORequest $req): void {
	$login = $req->getMiddleware(
		'Login'
	);
}
```

Balio bakar bat lortzeko:

```php
public function run(ORequest $req): void {
	$id = $req->getMiddlewareValue(
		'Login',
		'id'
	);
}
```

`LoginMiddleware` `Login` izen publikoaren bidez azaltzen da.

Ez dagoen Middleware testuinguru batek array hutsa itzultzen du; ez dagoen testuinguru-balio batek `null`.

Ikusi `/docs/eu/concepts/middlewares.md`.

---

## Aukera globaletara sartzea

Osagaiek framework-eko zerbitzuetara sarbidea dute:

- `getConfig()`
- `getLog()`
- `getSession()`

---

## Osagaiak errendatzea

```php
$cmp = new BooksComponent();
echo strval($cmp);
```

---

# Txantiloi-pipeak

Osumi Framework-eko txantiloiek Angular estiloko pipeak onartzen dituzte.

## `date`

Datak formateatzen ditu.

## `number`

`number_format()` erabiltzen du.

## `string`

`urlencode()` aplikatzen du eta komatxo arteko kate bat itzultzen du.

## `plain`

Kate bat JSONerako modu seguruan kodetzen du, URL kodetzea aplikatu gabe.

```text
John Doe → "John Doe"
```

Unicode karaktereak mantentzen dira eta JSON karaktere bereziak behar bezala ihes egiten dira.

## `bool`

Honako balio hauek sortzen ditu:

```text
true
false
null
```

---

## Praktika onak

- Mantendu txantiloiak sinpleak.
- Erabili `run()` datuak prestatzeko.
- Erabili propietate publiko tipatuak.
- Hobetsi `?type = null` bezalako balio nullable-ak egokia denean.
