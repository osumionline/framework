# Datu Transferentzia Objektuak (DTOak)

**Osumi Framework**-eko DTOak HTTP eskaera batetik datozen sarrera-datuak jasotzeko, normalizatzeko eta baliozkotzeko erabiltzen diren klase sinpleak dira.

Osagaiek eskaera-balio tipatu eta balioztatuak, HTTP goiburuak edo Middleware testuingurua modu egituratu eta seguruan atzitzeko aukera ematen dute.

DTO batek `ODTO` hedatu behar du eta bere eremuak `#[ODTOField]` atributuarekin definitu.

---

## 1. DTO baten helburua

DTOak honetarako diseinatuta daude:

- Eskaera-datuak bildu eta motaz aldatzeko.
- Middleware testuingurutik konfiantzazko balioak irakurtzeko.
- HTTP goiburuetatik balioak irakurtzeko.
- Balidazio-arauak osagaiaren logika exekutatu aurretik aplikatzeko.
- Eskaera-prozesamendua framework osoan koherente mantentzeko.
- Osagaien barruan `$req->getParam...()` dei errepikatuak saihesteko.

Osagai batek hau definitzen duenean:

```php
public function run(MovieDTO $dto): void
```

framework-ak automatikoki:

1. `MovieDTO` instantziatzen du.
2. Bere balioak kargatzen ditu.
3. Balidazio-arauak aplikatzen ditu.
4. `run()` metodoan injektatzen du.

---

## 2. Oinarrizko klasea: `ODTO`

`ODTO`-k reflection erabiltzen du DTOaren propietate publikoak eta haien `#[ODTOField]` definizioak aztertzeko.

### 2.1 Datu-iturburuak

Eremu batek iturburu esplizitu bat erabil dezake.

#### Middleware testuingurua

`middleware` eta `middlewareProperty` definituta badaude, balioa honela lortzen da:

```php
$req->getMiddlewareValue(
	$middlewareName,
	$middlewareProperty
);
```

Middleware testuingurua iturburu esplizitua da. Testuinguru-balioa ez badago, DTOaren balioa `null` izaten jarraitzen du; ez da bezeroaren sarrerara itzultzen.

#### Goiburua

`header` definituta badago, balioa dagokion HTTP goiburutik hartzen da.

Eremu batek ezin ditu Middleware iturburua eta goiburu-iturburua aldi berean definitu.

#### Eskaera-parametroak

Iturburu espliziturik ez dagoenean, balioa eskaera-parametroetatik kargatzen da propietate motaren arabera:

- `int` → `getParamInt()`
- `float` → `getParamFloat()`
- `bool` → `getParamBool()`
- `string` → `getParamString()`
- `array` → `getParam()`

### 2.2 Balidazioa

Balioak kargatu ondoren, ODTOk honako hauek ebaluatzen ditu:

- `required`
- `requiredIf`

Balidazio-erroreak honela lor daitezke:

```php
$dto->getValidationErrors();
```

DTOa baliozkoa den egiaztatzeko:

```php
$dto->isValid();
```

---

## 3. `ODTOField`

```php
#[ODTOField(
	required: false,
	requiredIf: null,
	middleware: null,
	middlewareProperty: null,
	header: null
)]
```

| Atributua | Deskribapena |
| --------- | ------------ |
| `required` | Eremuak balio bat izan behar du. |
| `requiredIf` | Eremua derrigorrezkoa da beste DTO eremu batek balioa duenean. |
| `middleware` | Eremuaren iturburu gisa erabiltzen den Middlewarearen izen publikoa. |
| `middlewareProperty` | Middleware horretatik irakurtzen den testuinguru-propietatea. |
| `header` | Eremuaren iturburu gisa erabiltzen den HTTP goiburua. |

Arauak:

- `middleware` eta `middlewareProperty` batera definitu behar dira.
- Middleware izen edo propietate hutsak ez dira baliozkoak.
- Eremu batek ezin ditu Middleware testuingurua eta goiburua batera erabili iturburu esplizitu gisa.
- `requiredIf`-ek beste DTO eremu bati egin behar dio erreferentzia eta ezin dio bere buruari erreferentziarik egin.

---

## 4. DTO adibidea

```php
class MovieDTO extends ODTO {
	#[ODTOField(required: true)]
	public ?int $idCinema = null;

	#[ODTOField(required: true)]
	public ?string $name = null;

	#[ODTOField(
		required: true,
		middleware: 'Login',
		middlewareProperty: 'id'
	)]
	public ?int $idUser = null;
}
```

Adibide honetan, `idUser` `LoginMiddleware`-k argitaratutako testuingurutik lortzen da, ez bezeroak bidalitako datuetatik.

---

## 5. DTO bat osagai batean erabiltzea

```php
class AddMovieComponent extends OComponent {
	public function run(MovieDTO $dto): void {
		if (!$dto->isValid()) {
			$this->errors = $dto->getValidationErrors();
			return;
		}

		$movie = new Movie();
		$movie->name = $dto->name;
		$movie->idUser = $dto->idUser;
		$movie->save();
	}
}
```

---

## 6. Praktika onak

- Erabili propietate mota zorrotzak.
- Hobetsi balio nullable-ak eremua hasieran falta daitekeenean.
- Erabili DTOak eskaera-sarrera egituratuetarako.
- Erabili `requiredIf` eremuen arteko mendekotasunetarako.
- Erabili Middleware testuingurua zerbitzariaren konfiantzazko balioetarako, hala nola autentifikatutako erabiltzailearen IDa.
- Ez sartu negozio-logikarik DTOetan.
- Egiaztatu `isValid()` balioztatutako sarrera erabili aurretik.
