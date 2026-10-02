# Osumi Framework – LLM Testuingurua (Guztia batean)

**Helburua**

Dokumentu honek **Osumi Framework 9.9** azaldu edo harentzat kodea sortzen duten IA sistemetarako testuinguru trinko baina autoritarioa eskaintzen du.

Framework-aren portaera bat hemen dokumentatuta ez badago, ez asmatu.

## 0. Framework-aren identitatea

- **Izena:** Osumi Framework
- **Bertsioa:** 9.9.0
- **Hizkuntza:** PHP
- **Gutxieneko PHP bertsioa:** 8.5+
- **Tipatzea:** erabili `declare(strict_types=1);`
- **Estiloa:** esplizitua, aurreikusgarria eta ahal den guztietan sendo tipatua

## 1. Filosofia

Osumi Framework-ek honako hauek lehenesten ditu:

- kode esplizitua
- bizi-ziklo aurreikusgarria
- arduren bereizketa argia
- osagai txikiak
- zerbitzu berrerabilgarriak
- DTO tipatuak
- Middleware fase esplizituak
- ezkutuko portaera minimoa

## 2. Eskaeraren bizi-ziklo nagusia

Normalean bat datorren ibilbide baterako:

```text
Bezeroaren eskaera
↓
Bideratzea (ORoute)
↓
before Middlewareak
↓
Osagaia / ORequest / DTO
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

Garrantzitsua:

- DTOak osagai batek `ODTO` azpiklase bat `run()` parametro gisa deklaratzen duenean bakarrik instantziatzen dira.
- `before` osagaiaren aurretik exekutatzen da.
- `afterRender` osagaiaren gorputza sortu ondoren eta layout-a baino lehen exekutatzen da.
- `afterResponse` azken gorputza sortu ondoren exekutatzen da.
- `afterResponse` `before` edo `afterRender` faseetako `stop` baten ondoren ere exekutatzen da.
- 404, 405 eta OPTIONS tratamendua bat datozen ibilbideen Middleware pipeline honetatik kanpo dago.

## 3. Bideratzea (`ORoute`)

Ibilbideek HTTP metodoak eta URLak osagai edo ikuspegi estatikoekin lotzen dituzte.

Ibilbide-metodoak:

- `get()`
- `post()`
- `put()`
- `delete()`
- `view()`

Taldekatze-metodoak:

- `prefix()`
- `layout()`
- `group()`

Aurrizkiak metagarriak eta habiaragarriak dira. URLak normalizatzen dira.

Ibilbide eta taldeetako Middleware definizioak fasearen arabera taldekatutako arrayak dira.

Adibidea:

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

Habiaratutako taldeek Middlewareak metatzen dituzte.

Fase bakoitzean ordena hau da:

```text
globala
↓
kanpoko taldea
↓
barneko taldea
↓
ibilbidea
```

## 4. Middlewareak (`OMiddleware`)

Middlewareak 9.9ko eskaera/erantzun interzepzio-mekanismoa dira.

### 4.1 Faseak

```php
OMiddleware::PHASE_BEFORE
OMiddleware::PHASE_AFTER_RENDER
OMiddleware::PHASE_AFTER_RESPONSE
```

### 4.2 Middleware klase baten kontratua

```php
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

### 4.3 Emaitza-gakoak

Middleware batek honako hauek itzul ditzake:

- `context`: `array<string, mixed>`
- `body`: string
- `headers`: `array<string, string>`
- `status_code`: 100 eta 599 arteko zenbaki osoa
- `stop`: boolean
- `message`: exekuzioa gelditzean erabiltzen den mezua

Array huts batek pipeline-an aldaketarik ez dagoela esan nahi du.

### 4.4 Testuingurua

Testuingurua Middlewarearen izen publikoarekin argitaratzen da.

`LoginMiddleware` honela bihurtzen da:

```text
Login
```

Adibidea:

```php
return [
	'context' => [
		'id' => 42,
		'role' => 'admin'
	]
];
```

### 4.5 `body`

`body` honako fase hauetan aplikatzen da:

- `afterRender`: osagaiaren gorputza ordezkatzen du layout-a baino lehen.
- `afterResponse`: azken erantzunaren gorputza ordezkatzen du.

### 4.6 `stop`

Adibidea:

```php
return [
	'stop' => true,
	'status_code' => 401,
	'message' => 'Unauthorized'
];
```

Semantika:

- `stop` `before` fasean:
  - gainerako `before` Middlewareak ez dira exekutatzen
  - osagaia ez da exekutatzen
  - layout-a ez da exekutatzen
  - errore-gorputz tipatua sortzen da
  - `afterResponse` exekutatzen jarraitzen da

- `stop` `afterRender` fasean:
  - gainerako `afterRender` Middlewareak ez dira exekutatzen
  - layout-a ez da exekutatzen
  - errore-gorputz tipatua sortzen da
  - `afterResponse` exekutatzen jarraitzen da

- `stop` `afterResponse` fasean:
  - gainerako `afterResponse` Middlewareak ez dira exekutatzen
  - bere errore-gorputz tipatuak azken gorputza ordezkatzen du
  - `afterResponse` ez da berriro modu errekurtsiboan exekutatzen

`stop` egitean honako hauek adierazten ez badira:

- `status_code` → `500`
- `message` → `Middleware stopped execution.`

### 4.7 `afterResponse`-n eskuragarri dagoen errore-egoera

Aurreko `stop` baten ondoren faseko datuek honako hauek dituzte:

```php
$data['is_error']
$data['error_phase']
$data['error_status_code']
$data['error_message']
```

Horri esker auditoria edo logging Middlewareek eskaera nola amaitu den ikus dezakete.

### 4.8 Middleware globalak

Hemen konfiguratzen dira:

```text
src/Middleware/Middlewares.php
```

Adibidea:

```php
OMiddleware::setGlobal([
	OMiddleware::PHASE_BEFORE => [],
	OMiddleware::PHASE_AFTER_RENDER => [],
	OMiddleware::PHASE_AFTER_RESPONSE => []
]);
```

## 5. Eskaera (`ORequest`)

`ORequest`-ek parametroak, goiburuak, fitxategiak eta Middleware testuingurua modu tipatuan atzitzeko aukera ematen du.

Middleware testuingurua:

```php
$login = $req->getMiddleware(
	'Login'
);

$id = $req->getMiddlewareValue(
	'Login',
	'id'
);
```

Portaera:

- ez dagoen Middleware testuingurua → `[]`
- ez dagoen testuinguru-propietatea → `null`

## 6. DTOak (`ODTO`)

DTOek `ODTO` hedatzen dute eta `#[ODTOField]` erabiltzen dute.

Framework-ak:

1. DTOa instantziatzen du
2. eremuak kargatzen ditu
3. baliozkotzen du
4. `run()` metodoan injektatzen du

DTOak `ODTO`-ren herentziaren bidez detektatzen dira, ez namespace-aren bidez.

### 6.1 Eskaera-parametroak

Iturburu espliziturik ez dagoenean, balioak eskaeratik lortzen dira deklaratutako motaren arabera.

### 6.2 Goiburu-iturburua

```php
#[ODTOField(
	header: 'Authorization'
)]
public ?string $authorization = null;
```

### 6.3 Middleware iturburua

```php
#[ODTOField(
	required: true,
	middleware: 'Login',
	middlewareProperty: 'id'
)]
public ?int $idUser = null;
```

Arauak:

- `middleware` eta `middlewareProperty` batera definitu behar dira.
- Eremu berean ezin dira Middleware iturburua eta goiburu-iturburua konbinatu.
- Middleware testuinguru esplizitua falta bada, ez da bezeroaren sarrerara itzultzen.
- Erabili Middleware testuingurua autentifikatutako erabiltzailearen IDa bezalako konfiantzazko balioetarako.

### 6.4 Balidazioa

DTO balidazioak honako hauek onartzen ditu:

- `required`
- `requiredIf`

Erabili:

```php
$dto->isValid();
$dto->getValidationErrors();
```

## 7. Osagaiak (`OComponent`)

Osagaiek honako fluxua orkestratzen dute:

```text
eskaera → DTO/ORequest → zerbitzuak/modeloak → propietate publikoak → txantiloia
```

Mantendu negozio-logika zerbitzuetan ahal denean.

Ibilbideko osagai batek `run()` sinadura hauetako bat bakarrik defini dezake:

```php
public function run(): void
```

```php
public function run(ORequest $req): void
```

```php
public function run(MyDTO $dto): void
```

Baldintzak:

- `MyDTO`-k `ODTO` hedatu behar du.
- Parametro bakarra, baldin badago, ezin da nullable izan.
- Beste parametro mota edo sinadurak baliogabeak dira.

## 8. Txantiloiak eta pipeak

Txantiloiak honakoak izan daitezke:

- `.php`
- `.html`
- `.json`
- `.xml`

Txantiloi estatikoek hau erabiltzen dute:

```text
{{ variable }}
```

Onartutako pipeak:

- `date`
- `number`
- `string`
- `plain`
- `bool`

`string`-ek URL kodetzea aplikatzen du.

`plain`-ek JSONerako kate segurua itzultzen du komatxo artean, URL kodetzerik gabe eta Unicode mantenduz.

## 9. Layout-ak

Layout-ek osagaiaren irteera `afterRender` ondoren inguratzen dute.

Ordena:

```text
osagaiaren gorputza
↓
afterRender
↓
layout-a
↓
afterResponse
```

`before` edo `afterRender` faseek `stop` egiten badute, layout-a ez da exekutatzen.

## 10. Zerbitzuak (`OService`)

Zerbitzuek negozio/domeinu logika berrerabilgarria dute.

Erabili honetarako:

- modeloen eragiketa berrerabilgarriak
- urrats anitzeko domeinu-logika
- kanpoko integrazioak
- aplikazio-portaera berrerabilgarria

Ez erabili zerbitzuak eskaera/erantzun Middlewareen edo DTO balidazioaren ordezko gisa.

## 11. ORM (`OModel`)

Modeloek `OModel` hedatzen dute eta PHP atributuak erabiltzen dituzte.

Ohiko atributuak:

- `#[OPK]`
- `#[OField]`
- `#[OCreatedAt]`
- `#[OUpdatedAt]`

Erabili propietate mota esplizituak.

## 12. CLI (`OTask`)

Aplikazioaren zereginek `OTask` hedatzen dute.

Aplikazioaren CLIa:

```bash
php of <zeregina>
```

Middleware bat sortzeko:

```bash
php of add --option middleware --name Login
```

`filter` sortzeko aukera zaharra ez dago 9.9 bertsioan erabilgarri.

## 13. Framework migrazioak

Composer paketeak hau eskaintzen du:

```bash
php vendor/bin/ofw-migrate --help
```

Onartutako aukerak:

```text
--from
--to
--dry-run
--force
--verbose
--no-interaction
--help
```

Migrazio-egoera hemen gordetzen da:

```text
ofw/tmp/state.json
```

Migrazioak bertsionatuak eta idempotenteak dira.

Composer updater integrazioak framework-a eguneratzean zain dauden migrazioak automatikoki exekuta ditzake.

## 14. Filter zaharren migrazioa

Filterrak 9.9 aurreko kontzeptu legacy bat dira.

9.9ko kode berrirako:

- ez sortu Filter klaserik
- ez erabili `getFilter()`
- ez erabili `getFilters()`
- ez erabili `filter` / `filterProperty`
- erabili Middleware natiboak eta Middleware testuingurua

9.9ko migrazio-pausoak Filter zaharren negozio-logika gorde dezake Middleware adapterrak sortuz.

Beraz, migratutako proiektu batek aldi baterako Filter klase zaharrak izan ditzake sortutako Middleware adapterren atzean. Hau migrazio-bateragarritasuna da, ez 9.9ko aplikazio berrientzako gomendatutako arkitektura.

## 15. Konbentzio zorrotzak

Hobetsi:

- `declare(strict_types=1);`
- parametro eta itzulera mota esplizituak
- propietate tipatuak
- PHPDoc osoa metodoetan
- PascalCase klase eta fitxategiak
- `::class` erreferentziak
- `OMiddleware::PHASE_*` konstanteak
- `null` kudeaketa esplizitua
- klase txiki eta fokatuak

## 16. Ez suposatu

Ez asmatu:

- dokumentatu gabeko helper-ak
- propietateen mendekotasun-injekzio automatikoa
- erlazioen karga automatikoa
- serializatzaile ezkutuak
- klase-izenaren arauaz bestelako Middleware testuinguru-izen inplizituak
- Filter API legacy-ak kode berrian
- dokumentatutako kontratuetatik kanpoko `run()` sinadurak

Xehetasun bat hemen ez badago, erabili egungo dokumentazioa iturri autoritario gisa.
