# Datu Transferentzia Objektuak (DTOak)

**Osumi Framework**-eko DTOak (Data Transfer Objects) HTTP eskaera batetik datozen sarrera datuak jasotzeko, normalizatzeko eta balioztatzeko erabiltzen diren klase sinpleak dira.

Osagaiek idatzitako eta balioztatutako eskaera balioak, goiburuak edo iragazki irteerak atzitzeko modu egituratu eta segurua eskaintzen dute.

DTO batek **`ODTO` hedatu behar du** eta bere eremuak definitu **`#[ODTOField]`** atributua erabiliz.

---

# 1. DTO baten helburua

DTOak honetarako diseinatuta daude:

- Eskaera datuak bildu eta motaz aldatu (URL parametroak, JSON gorputza, formulario eremuak, kontsulta kateak, goiburuak, iragazkiak).
- Balidazio arauak aplikatu osagaien logika exekutatu _aurretik_.
- Parametroen kudeaketa koherentea izan dadin framework osoan.
- `$req->getParam…()`-rako eskuzko deiak saihestea osagaien barruan.
- Balio ez-seguruak edo ustekabekoak negozio logikara iristea saihestea.

Osagai batek definitzen duenean:

```php
public function run(MovieDTO $dto): void
```

...framework-ak automatikoki:

1. `MovieDTO` instantziatzen du.
2. Eskaeraren datuak kargatzen ditu.
3. Bere atributuetan definitutako balidazio arauak aplikatzen ditu.
4. DTO osagaiaren `run()` metodoan txertatzen du.

---

# 2. Oinarrizko klasea: `ODTO`

`ODTO` klaseak **islapena** erabiltzen du DTOko propietate publiko guztiak ikuskatzeko, haien `#[ODTOField]` definizioak irakurtzeko eta datuak horren arabera kargatzeko.

### 2.1 Datuak kargatzeko prozesua

ODTOk balioak lehentasun-ordena honetan kargatzen ditu:

1. **Iragazkiaren emaitza**
   Eremu batek `filter` eta `filterProperty` definitzen baditu, balioa hemendik hartzen da:

    ```php
    $req->getFilter($filterName)[$filterProperty]
    ```

2. **Goiburuaren balioa**
   Eremu batek `header: 'X-Header'` definitzen badu, balioa hemendik hartzen da:

    ```php
    $req->getHeader('X-Header')
    ```

3. **Eskaera-parametroak**
   Balioa propietate motaren arabera aldatzen da:

- `int` → `$req->getParamInt()`
- `float` → `$req->getParamFloat()`
- `bool` → `$req->getParamBool()`
- `string` → `$req->getParamString()`
- `array` → `$req->getParam()`
- defektuz → `null`

### 2.2 Balidazioa

Balio guztiak kargatu ondoren, ODTOk automatikoki egiaztatzen du:

- **required**
  `required = true` bada eta balioa falta bada, balidazio-errore bat gehitzen da.

- **requiredIf**
  Beste eremu batek balio bat badu, eta eremu honek ez, balidazio-errore bat gehitzen da.

Erroreak barnean gordetzen dira eta honen bidez berreskura daitezke:

```php
$dto->getValidationErrors();
```

DTO baliozkoa den egiaztatu dezakezu honekin:

```php
$dto->isValid();
```

---

# 3. `ODTOField` atributua

`ODTOField` atributua DTO propietate bakoitza konfiguratzeko erabiltzen da:

```php
#[ODTOField(
  required: false,
  requiredIf: null,
  filter: null,
  filterProperty: null,
  header: null
)]
```

### Atributuen aukerak

| Atributua          | Deskribapena                                                     |
| ------------------ | ---------------------------------------------------------------- |
| **required**       | Eremuak balio bat izan behar du.                                 |
| **requiredIf**     | Eremua beharrezkoa da beste eremu batek balio bat badu bakarrik. |
| **filter**         | Eremua betetzeko irteera erabili behar den iragazkiaren izena.   |
| **filterProperty** | Erabili beharreko iragazkiaren irteera-matrizearen gakoa.        |
| **header**         | Balioa irakurtzeko HTTP goiburuaren izena.                       |

Aukera hauek konbinazio indartsuak ahalbidetzen dituzte, hala nola erabiltzaile-IDak iragazkietatik lortzea, API tokenak goiburuetatik ateratzea edo DTO eremuen arteko baldintzapeko mendekotasunak betearaztea.

---

# 4. DTO adibidea

Zure proiektuko adibidea:

```php
class MovieDTO extends ODTO {
  #[ODTOField(required: true)]
  public ?int $idCinema = null;

  #[ODTOField(required: true)]
  public ?string $name = null;

  #[ODTOField(required: true)]
  public ?string $cover = null;

  #[ODTOField(required: true)]
  public ?int $coverStatus = null;

  #[ODTOField(required: true)]
  public ?string $ticket = null;

  #[ODTOField(required: true)]
  public ?string $imdbUrl = null;

  #[ODTOField(required: true)]
  public ?string $date = null;

  #[ODTOField(required: true)]
  public ?array $companions = null;

  #[ODTOField(required: true, filter: 'Login', filterProperty: 'id')]
  public ?int $idUser = null;
}
```

DTO honek:

- Eskaera-parametroetatik idatzitako balioak kargatzen ditu (`int`, `string`, `array`).
- Beharrezko eremuak betearazten ditu.
- `idUser` kargatzen du **Login iragazkitik** bezeroaren eskaeraren ordez.

---

# 5. DTO bat osagai baten barruan erabiltzea

```php
class AddMovieComponent extends OComponent {
  public function run(MovieDTO $dto): void {
    if (!$dto->isValid()) {
      $this->errors = $dto->getValidationErrors();
      return;
    }

    $movie = new Movie();
    $movie->name = $dto->name;
    $movie->date = $dto->date;
    $movie->idUser = $dto->idUser;
    $movie->save();
  }
}
```

### Oharrak:

- Osagaiak **ez** du eskaera-balioak eskuz irakurri beharrik.
- Osagaira pasatako balioak idatzita eta balioztatuta daude dagoeneko.
- Erroreen kudeaketa errazagoa bihurtzen da.

---

# 6. Praktika onak

- Erabili `?type = null` DTO propietate guztietarako, hasieratu gabeko motatutako propietateen erroreak saihesteko.
- Hobetsi DTOak datu egituratuak jasotzen dituzunean (API amaiera-puntuak, inprimakiak, JSON).
- Erabili `requiredIf` eremuen arteko mendekotasun logikoak adierazteko.
- Erabili `filter` eremuak ID sentikorrak bezeroari agerian ez jartzeko.
- Mantendu DTOak sinpleak; ez lukete negozio-logikarik izan behar.
- Egiaztatu beti `$dto->isValid()` erabili aurretik.

---

# 7. Noiz erabili DTOak

DTOak aproposak dira honetarako:

- Sarrera konplexuak jasotzen dituzten API amaiera-puntuak.
- Eremu asko dituzten inprimakien bidalketak.
- Iragazkiek injektatutako autentifikazio-datuak behar dituzten amaiera-puntuak.
- Berrerabilgarriak diren datu-egitura hainbat osagaitan erabiliak.
- Errepikatutako eskaera-analisi logika ordezkatuz.
