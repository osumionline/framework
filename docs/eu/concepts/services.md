# Zerbitzuak

**Osumi Framework**-eko zerbitzuak negozio-logika, partekatutako eragiketak edo osagai, modulu eta zereginetan erabilitako erabilgarritasun-funtzioak kapsulatzen dituzten klase berrerabilgarriak dira.

Zerbitzuek honako hauetan laguntzen dute:

- Logika bikoiztua saihesten.
- Domeinu-portaera antolatzen.
- Modeloekin edo kanpoko APIekin elkarreraginak zentralizatzen.
- Osagaiak orkestrazioan eta aurkezpenean zentratuta mantentzen.

---

## 1. Zer da zerbitzu bat?

Zerbitzu batek `OService` hedatzen du.

Framework-aren logging, konfigurazio eta cache baliabideak erabil ditzake.

---

## 2. Zerbitzu bat sortzea

Aplikazioaren zerbitzuak hemen gordetzen dira:

```text
src/Service/
```

Adibidea:

```php
namespace Osumi\OsumiFramework\App\Service;

use Osumi\OsumiFramework\Core\OService;

class UserService extends OService {
	public function getUserById(int $id): ?User {
		return User::findOne([
			'id' => $id
		]);
	}
}
```

---

## 3. Zerbitzu bat osagai batean injektatzea

Zerbitzuak exekutatzen den kode batetik injektatu behar dira, adibidez eraikitzailetik:

```php
class MyComponent extends OComponent {
	private ?UserService $us = null;

	public function __construct() {
		parent::__construct();

		$this->us = inject(
			UserService::class
		);
	}
}
```

---

## 4. Zerbitzu bat osagai batean erabiltzea

Ohiko fluxua hau da:

1. Eskaeraren edo DTOaren datuak irakurri, beharrezkoa denean Middlewareek argitaratutako konfiantzazko testuingurua barne.
2. Negozio-logika zerbitzu bati delegatu.
3. Osagaiaren irteera prestatu.

```php
public function run(ORequest $req): void {
	$id = $req->getMiddlewareValue(
		'Login',
		'id'
	);

	if (!is_int($id)) {
		return;
	}

	$this->user = $this->us->getUserById(
		$id
	);
}
```

---

## 5. Zerbitzu baten bizi-zikloa

`OService`-k framework-aren hainbat baliabidetarako sarbidea eskaintzen du:

- logging-a
- aplikazioaren konfigurazioa
- cachea

---

## 6. Izenak eta kokapena

Erabili:

```text
src/Service/UserService.php
```

eta klase-izenak:

```text
UserService
OrderService
PaymentService
```

---

## 7. Praktika onak

- Mantendu zerbitzuak egoerarik gabe ahal denean.
- Taldekatu lotutako funtzionaltasuna.
- Saihestu errendatzea edo irteera zuzena.
- Mantendu eskaera/erantzun ardura zeharkakoak Middlewareetan.
- Mantendu sarrera-balidazioa DTOetan.
- Utzi osagaiei eskaera → zerbitzua → modeloa → txantiloia fluxua orkestratzen.

---

## 8. Noiz erabili zerbitzu bat

Erabili zerbitzu bat negozio- edo domeinu-logika berrerabilgarria behar denean.

Ez erabili zerbitzu bat honako kasu hauetan:

- Logika eskaera/erantzun ardura zeharkakoa bada eta Middleware baterako egokiagoa bada.
- Logika aurkezpen edo errendatze-logika espezifikoa bada.
- Logika DTO batekin hobeto adierazten den sarrera-balidazioa bada.

---

## 9. Laburpena

Zerbitzuek negozio-logika berrerabilgarria eskaintzen dute eta osagaiak, Middlewareak eta DTOak beren arduratan zentratuta mantentzen laguntzen dute.
