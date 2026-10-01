# Middlewareak

**Osumi Framework**-eko Middlewareak HTTP eskaera baten bizi-zikloan parte hartzen duten klase berrerabilgarriak dira.

Hiru fasetan exekuta daitezke:

- `before`: ibilbideko osagaia exekutatu aurretik.
- `afterRender`: osagaia errendatu ondoren eta layout-a aplikatu aurretik.
- `afterResponse`: erantzunaren azken gorputza sortu ondoren eta bidali aurretik.

Ohiko erabilerak hauek dira:

- Autentifikazioa eta baimena.
- API gakoen edo tokenen baliozkotzea.
- Eskaeren aurreprozesamendua.
- Baimen-egiaztapenak.
- Erabiltzailea, tenant-a edo hizkuntza bezalako testuinguru-datuak kargatzea.
- Osagaiaren emaitza errendatua aldatzea.
- Erantzunaren azken gorputza aldatzea.
- HTTP goiburuak gehitzea edo ordezkatzea.
- HTTP egoera-kodea aldatzea.
- Eskaera baten azken emaitzaren auditoria edo logging-a egitea.

---

## 1. Middleware baten egitura

Aplikazioko Middlewareak normalean hemen gordetzen dira:

```text
src/Middleware/
```

Middleware klase batek `handle()` metodo estatiko publiko bat izan behar du:

```php
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Middleware;

final class ExampleMiddleware {
	/**
	 * Handle a middleware execution phase.
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
		return [];
	}
}
```

Middleware klase bera fase batean edo gehiagotan erregistratu daiteke.

`$phase` argumentuak une horretan exekutatzen ari den fasea adierazten du.

`$data` argumentuak eskaeraren informazioa eta Middleware pipeline-aren uneko egoera biltzen ditu.

---

## 2. Middleware faseak

Osumi Framework-ek hiru fase definitzen ditu `OMiddleware` bidez:

```php
OMiddleware::PHASE_BEFORE
OMiddleware::PHASE_AFTER_RENDER
OMiddleware::PHASE_AFTER_RESPONSE
```

Exekuzio-ordena hau da:

```text
Bideratzea
↓
before
↓
Osagaia
↓
afterRender
↓
Layout-a
↓
afterResponse
↓
HTTP erantzuna
```

### `before`

Osagaia instantziatu aurretik exekutatzen da.

Ohiko erabilerak:

- Autentifikazioa.
- Baimena.
- Tokenen baliozkotzea.
- Eskaerak blokeatzea.
- Aplikazioaren testuingurua kargatzea.

`before` Middleware batek pipeline normala geldiaraz dezake osagaia exekutatu aurretik.

### `afterRender`

Osagaiaren txantiloia errendatu ondoren eta layout-a aplikatu aurretik exekutatzen da.

Ohiko erabilerak:

- Osagaiaren irteera ikuskatzea.
- Errendatutako osagaiaren gorputza ordezkatzea edo aldatzea.
- Erantzun-goiburuak gehitzea.
- Erantzunaren egoera-kodea aldatzea.

`afterRender` Middleware batek exekuzioa gelditzen badu, layout-a ez da errendatzen.

### `afterResponse`

Erantzunaren azken gorputza sortu ondoren exekutatzen da.

Ohiko erabilerak:

- Auditoria.
- Logging-a.
- Goiburuen azken aldaketak.
- Erantzunaren azken eraldaketak.

`afterResponse` ere exekutatzen da aurreko `before` edo `afterRender` Middleware batek pipeline normala geldiarazi badu. Kasu horretan Middleware errorearen egoera ikuska dezake.

`afterResponse` Middleware batek berak exekuzioa gelditzen badu, fase horretako gainerako Middlewareak ez dira exekutatzen eta bere errore-erantzuna zuzenean bidaltzen da.

---

## 3. Middleware baten emaitza

`handle()` metodoak beti array bat itzuli behar du.

Array huts batek esan nahi du Middlewareak ez duela pipeline-a aldatzen:

```php
return [];
```

Middleware batek honako gako hauek itzul ditzake.

### `context`

Ondorengo Middlewareek, `ORequest`-ek edo DTOek erabil ditzaketen datuak argitaratzen ditu:

```php
return [
	'context' => [
		'id' => 42,
		'role' => 'admin'
	]
];
```

Testuingurua Middlewarearen izen publikoarekin gordetzen da.

Adibidez:

```text
LoginMiddleware
```

honela azaltzen da:

```text
Login
```

### `body`

Errendatutako gorputz bat ordezkatzen du:

```php
return [
	'body' => 'Modified response'
];
```

Eragina fasearen araberakoa da:

- `afterRender` fasean, osagaiaren gorputz errendatua ordezkatzen du.
- `afterResponse` fasean, erantzunaren azken gorputza ordezkatzen du.

### `headers`

HTTP erantzun-goiburuak gehitzen edo ordezkatzen ditu:

```php
return [
	'headers' => [
		'X-Request-Id' => 'abc123'
	]
];
```

Goiburuen izenak maiuskulak eta minuskulak bereizi gabe kudeatzen dira.

### `status_code`

HTTP egoera-kodea aldatzen du:

```php
return [
	'status_code' => 201
];
```

Baliozko egoera-kodeak `100` eta `599` artean daude.

### `stop`

Uneko Middleware pipeline-a geldiarazten du:

```php
return [
	'stop' => true,
	'status_code' => 403,
	'message' => 'Forbidden'
];
```

`stop` `true` denean:

- Uneko faseko gainerako Middlewareak ez dira exekutatzen.
- Eskaera Middleware errore-egoeran sartzen da.
- `status_code` HTTP egoera-kode gisa erabiltzen da.
- `message` framework-aren errore-erantzuna sortzeko erabiltzen da.

Ez badira adierazten:

- `status_code`-ren balio lehenetsia `500` da.
- `message`-ren balio lehenetsia `Middleware stopped execution.` da.

---

## 4. Autentifikazio Middleware baten adibidea

```php
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Middleware;

use Osumi\OsumiFramework\Core\OMiddleware;

final class LoginMiddleware {
	/**
	 * Validate the request and publish authenticated user context.
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

		$headers = $data['headers'];

		if (
			!is_array($headers) ||
			!array_key_exists('Authorization', $headers)
		) {
			return [
				'stop' => true,
				'status_code' => 401,
				'message' => 'Unauthorized'
			];
		}

		return [
			'context' => [
				'id' => 42,
				'role' => 'admin'
			]
		];
	}
}
```

Middlewareak:

```php
[
	'id' => 42,
	'role' => 'admin'
]
```

balioak `Login` testuinguru gisa argitaratzen ditu.

---

## 5. Middleware globalak

Proiektu osoko Middlewareak hemen konfiguratzen dira:

```text
src/Middleware/Middlewares.php
```

Adibidez:

```php
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Middleware;

use Osumi\OsumiFramework\Core\OMiddleware;

OMiddleware::setGlobal([
	OMiddleware::PHASE_BEFORE => [
		RequestMiddleware::class
	],
	OMiddleware::PHASE_AFTER_RENDER => [],
	OMiddleware::PHASE_AFTER_RESPONSE => [
		AuditMiddleware::class
	]
]);
```

Middleware globalak bat datorren ibilbide guztiei aplikatzen zaizkie.

---

## 6. Ibilbideko Middlewareak

Middlewareak zuzenean ibilbide bati eslei dakizkioke:

```php
ORoute::get(
	'/profile',
	ProfileComponent::class,
	[
		OMiddleware::PHASE_BEFORE => [
			LoginMiddleware::class
		],
		OMiddleware::PHASE_AFTER_RESPONSE => [
			AuditMiddleware::class
		]
	]
);
```

---

## 7. Taldeko Middlewareak

`prefix()`, `layout()` eta `group()` metodoek Middleware definizioak jaso ditzakete.

Adibidez:

```php
ORoute::prefix(
	'/api',
	static function (): void {
		ORoute::get(
			'/profile',
			ProfileComponent::class
		);
	},
	[
		OMiddleware::PHASE_BEFORE => [
			ApiMiddleware::class
		]
	]
);
```

Habiaratutako taldeek beren Middlewareak metatzen dituzte.

Fase bakoitzean exekuzio-ordena hau da:

```text
global
↓
kanpoko taldea
↓
barneko taldea
↓
ibilbidea
```

Ordena hori modu independentean mantentzen da Middleware fase bakoitzean.

---

## 8. Middleware testuingurua ORequest-etik atzitzea

Middleware baten testuinguru osoa lortzeko:

```php
$login = $req->getMiddleware(
	'Login'
);
```

Balio bakar bat lortzeko:

```php
$id = $req->getMiddlewareValue(
	'Login',
	'id'
);
```

Ez dagoen Middleware testuinguru batek array hutsa itzultzen du.

Ez dagoen testuinguru-balio batek `null` itzultzen du.

---

## 9. Middleware testuingurua DTOetan erabiltzea

DTO eremuek Middleware testuingurua datu-iturburu esplizitu gisa erabil dezakete:

```php
#[ODTOField(
	required: true,
	middleware: 'Login',
	middlewareProperty: 'id'
)]
public ?int $idUser = null;
```

`middleware` eta `middlewareProperty` beti batera definitu behar dira.

Balioa Middlewarearen testuingurutik lortzen da, bezeroak bidalitako datuetatik hartu beharrean.

Horri esker, bezero batek ezin ditu gainidatzi autentifikatutako erabiltzailearen IDa bezalako konfiantzazko balioak.

---

## 10. Errore-egoera afterResponse fasean

`before` edo `afterRender` faseek pipeline-a gelditzen dutenean, `afterResponse` exekutatzen jarraitzen da.

Bere `$data` array-ak honako balio hauek ditu:

```php
$data['is_error']
$data['error_phase']
$data['error_status_code']
$data['error_message']
```

Horri esker, auditoria edo logging Middleware batek eskaera nola amaitu den ikuska dezake.

Adibidez:

```php
if (
	$phase === OMiddleware::PHASE_AFTER_RESPONSE &&
	$data['is_error'] === true
) {
	// Middleware errorea erregistratu.
}
```

---

## 11. Praktika onak

- Erabili `before` autentifikaziorako, baimenerako eta eskaeraren testuingururako.
- Erabili `afterRender` osagaiaren irteera layout-a aplikatu aurretik behar duzunean bakarrik.
- Erabili `afterResponse` azken eraldaketetarako, auditoriarako eta logging-erako.
- Mantendu Middleware klaseak txiki eta ardura bakarreko.
- Eraman negozio-logika konplexua zerbitzuetara.
- Argitaratu ondorengo kodeak benetan behar duen testuingurua bakarrik.
- Erabili `XxxMiddleware` formatuko klase-izenak.
- Erabili `OMiddleware::PHASE_*` konstanteak ahal den guztietan.
- Erabili Middleware testuingurutik datozen DTO eremuak zerbitzariaren konfiantzazko balioetarako, hala nola autentifikatutako erabiltzailearen IDa.

---

## 12. Eskaeraren fluxu osoa

```text
Bezeroaren eskaera
↓
Bideratzea
↓
before Middleware globalak
↓
Taldeetako before Middlewareak
↓
Ibilbideko before Middlewareak
↓
Osagaia / DTO / ORequest
↓
Osagaiaren errendatzea
↓
afterRender Middlewareak
↓
Layout-aren errendatzea
↓
afterResponse Middlewareak
↓
HTTP erantzuna
```

`before` edo `afterRender` faseko `stop` batek gainerako prozesamendu normala saihesten du, baina `afterResponse` fasera iristen da hala ere.

`afterResponse` barruko `stop` batek azken fase hori amaitzen du eta bere errore-erantzuna zuzenean bidaltzen du.
