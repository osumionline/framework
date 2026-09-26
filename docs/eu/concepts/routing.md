# Bideratzea

**Osumi Framework**-en bideratzea `ORoute` klaseak kudeatzen du. Sarrerako HTTP eskaerak (URLak) ekintza gisa jokatzen duten osagai espezifikoetara mapatzen ditu.

Ibilbideak normalean `src/Routes/` direktorioan dauden PHP fitxategietan definitzen dira. Karpeta honetan hainbat fitxategi sor ditzakezu zure ibilbideak logikoki antolatzeko (adibidez, fitxategi bat modulu bakoitzeko).

Erabiltzaile batek URL batera sartzen denean, `ORoute`-k bidea aurkitzen du, iragazkiak exekutatzen ditu, gero osagaia instantziatzen du eta `run()` deitzen du, erabiltzaileak definitutako `DTO` bat edo `ORequest` generiko bat pasatuz.

---

## Ibilbideak definitzea

Ibilbide bat definitzeko, erabili `ORoute`-ren metodo estatikoak HTTP aditzei dagozkienak: `get()`, `post()`, `put()` edo `delete()`.

### Oinarrizko sintaxia

```php
use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\App\Module\Home\Index\IndexComponent;

ORoute::get('/', IndexComponent::class);

```

### Ibilbidearen Parametroak

- **URL (string)**: Erantzun beharreko bidea.
- **Component (string)**: Exekutatzeko osagaiaren FQCN (Fully Qualified Class Name).
- **Filters (array, aukerakoa)**: Osagaia baino lehen exekutatzeko iragazki klaseen zerrenda.
- **Layout (string, aukerakoa)**: Ibilbide honetarako diseinu osagai espezifikoa.

---

## Iragazkiak

Iragazkiak osagai nagusiaren aurretik exekutatutako klaseak dira. Normalean autentifikaziorako (tokenak egiaztatzeko), erregistroetarako edo eskaeren baliozkotzerako erabiltzen dira.

Dokumentuak: /docs/eu/concepts/filters.md

```php
use Osumi\OsumiFramework\App\Filter\LoginFilter;
use Osumi\OsumiFramework\App\Module\User\Profile\ProfileComponent;

ORoute::post('/profile', ProfileComponent::class, [LoginFilter::class]);

```

---

## Ibilbideak Taldekatzea

Osumi Framework-ek hiru modu eskaintzen ditu ezaugarri komunak dituzten ibilbideak taldekatzeko:

### 1. Aurrizkiak

Ibilbide anitzek URLaren hasiera bera partekatzen dutenean erabiltzen dira (adibidez, API batean). Aurrizkiak habiaratu daitezke; habiaratutako aurrizki bakoitza aktibo dagoen aurrizkiari gehitzen zaio.

```php
ORoute::prefix('/api', function(): void {
  ORoute::get('/health', HealthComponent::class);

  ORoute::prefix('/admin', function(): void {
    ORoute::post('/login', LoginComponent::class);
    ORoute::get('/me', MeComponent::class, [AdminAuthFilter::class]);
  });
});

```

Honek `/api/health`, `/api/admin/login` eta `/api/admin/me` erregistratzen ditu.

### 2. Diseinuak

Ibilbide anitzek egitura bisual bera partekatzen dutenean erabiltzen da (goiburua, orri-oina, etab.).

```php
ORoute::layout(MainLayoutComponent::class, function() {
  ORoute::get('/home', HomeComponent::class);
  ORoute::get('/contact', ContactComponent::class);
});

```

### 3. Taldeak (Aurrizkia + Diseinua)

Aurrizkia eta diseinuaren esleipena bloke bakarrean konbinatzen ditu. Taldeak beste talde edo aurrizki batzuekin habiaratu daitezke; haien URL aurrizkiak metatu egiten dira, eta talde bakoitzak bere barruan deklaratutako ibilbideei aplikatzen die bere diseinua.

```php
ORoute::group('/admin', AdminLayoutComponent::class, function(): void {
  ORoute::group('/users', UserLayoutComponent::class, function(): void {
    ORoute::get('/profile', ProfileComponent::class);
  });
});

```

Aurreko ibilbidea `/admin/users/profile` helbidean erregistratzen da eta `UserLayoutComponent` erabiltzen du.

### URLen normalizazioa

`ORoute`-ren metodo estatiko guztiek URLak normalizatzen dituzte. Hasierako barrak bateratu egiten dira, barra errepikatuak bakarrera murrizten dira, eta amaierako barrak kentzen dira, erroko `/` URLan izan ezik. Hau metodo hauei aplikatzen zaie: `get()`, `post()`, `put()`, `delete()`, `view()`, `group()` eta `prefix()`.

Adibidez, barra gehigarriak dituzten aurrizki habiaratuak:

```php
ORoute::prefix('/api/', function(): void {
  ORoute::prefix('//admin///', function(): void {
    ORoute::get('//users/', UsersComponent::class);
  });
});
```

Ibilbidea `/api/admin/users` helbidean erregistratzen da.

---

## Ikuspegi Estatikoak

Ekintza-osagai oso baten logikarik gabeko fitxategi estatiko bat edo txantiloi sinple bat zerbitzatu behar baduzu, erabili `ORoute::view()`.

```php
ORoute::view('/about-us', 'about-us.html');

```

## Ibilbideetako parametroak

URLak parametroak izateko defini daitezke `:izena` sintaxia erabiliz.

```php
ORoute::get('/user/:id', UserComponent::class);
ORoute::get('/location/:name', LocationComponent::class);
```

Osagaiaren `run(ORequest $req)` metodoak parametro horretara sar daiteke `getParamInt('id')` edo `getParamString('name')` bezalako metodoak erabiliz.

---

## `ORoute` metodoen laburpena

| Metodoa    | Deskribapena                                                                     |
| ---------- | -------------------------------------------------------------------------------- |
| `get()`    | GET ibilbide bat erregistratzen du.                                              |
| `post()`   | POST ibilbide bat erregistratzen du.                                             |
| `put()`    | PUT ibilbide bat erregistratzen du.                                              |
| `delete()` | DELETE ibilbide bat erregistratzen du.                                           |
| `view()`   | Fitxategi estatiko bat zuzenean errendatzen duen ibilbide bat erregistratzen du. |
| `prefix()` | Ibilbideak URL aurrizki metatu eta habiaragarri baten pean taldekatzen ditu.     |
| `layout()` | Ibilbideak diseinu osagai komun baten pean taldekatzen ditu.                     |
| `group()`  | Ibilbideak aurrizki habiaragarri batekin eta diseinu batekin taldekatzen ditu.   |

---

## Praktika onak

- **Antolatu fitxategiaren arabera**: Sortu fitxategi desberdinak `src/Routes/`-en zure aplikazioaren modulu edo funtzio-eremu bakoitzerako.
- **Erabili iragazkiak**: Mantendu zure osagaiak garbi autentifikazio eta balidazio logika iragazkietara deskargatuz.
- **Klase konstanteak**: Erabili beti `::class` notazioa osagai eta iragazkietarako IDE autoosatze eta analisi estatikotik etekina ateratzeko.
