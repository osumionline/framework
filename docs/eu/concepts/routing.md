# Bideratzea

Osumi Framework-en bideratzea `ORoute` klaseak kudeatzen du. Klase horrek sarrerako HTTP eskaerak (URLak) ekintza gisa jarduten duten osagai espezifikoekin lotzen ditu.

Ibilbideak normalean `src/Routes/` direktorioko PHP fitxategietan definitzen dira. Karpeta horretan hainbat fitxategi sor daitezke ibilbideak modu logikoan antolatzeko, adibidez modulu bakoitzeko fitxategi bat.

Erabiltzaile batek URL batera sartzen denean, `ORoute`-k ibilbidea aurkitzen du, Middleware pipeline eraginkorra ebazten du, osagaia instantziatzen du eta `run()` deitzen du, erabiltzaileak definitutako `DTO` bat, `ORequest` generiko bat edo parametrorik gabe, osagaiaren sinaduraren arabera.

---

## Ibilbideak definitzea

Ibilbide bat definitzeko, erabili HTTP aditzei dagozkien `ORoute` metodo estatikoak: `get()`, `post()`, `put()` edo `delete()`.

### Oinarrizko sintaxia

```php
use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\App\Module\Home\Index\IndexComponent;

ORoute::get('/', IndexComponent::class);
```

### Ibilbide-parametroak

`get()`, `post()`, `put()` eta `delete()` bezalako metodoek honako parametro hauek onartzen dituzte:

- **URL (string)**: Erantzun beharreko bidea.
- **Component (string)**: Exekutatuko den osagaiaren FQCN.
- **Middlewares (array, aukerakoa)**: Exekuzio-fasearen arabera taldekatutako Middleware klaseak.
- **Layout (string|null, aukerakoa)**: Ibilbiderako layout osagai espezifikoa.

---

## Middlewareak

Middlewareek eskaeraren bizi-zikloan parte hartzen dute hiru fasetan:

- `before`
- `afterRender`
- `afterResponse`

Ikusi `/docs/eu/concepts/middlewares.md` Middlewareen bizi-ziklo osoa eta emaitza-formatua ezagutzeko.

Adibidea:

```php
use Osumi\OsumiFramework\App\Middleware\AuditMiddleware;
use Osumi\OsumiFramework\App\Middleware\LoginMiddleware;
use Osumi\OsumiFramework\App\Module\User\Profile\ProfileComponent;
use Osumi\OsumiFramework\Core\OMiddleware;
use Osumi\OsumiFramework\Routing\ORoute;

ORoute::post(
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

## Ibilbideak taldekatzea

Osumi Framework-ek ezaugarri komunak dituzten ibilbideak taldekatzea ahalbidetzen du.

Ibilbide-taldeei esleitutako Middleware definizioak habiaratutako taldeekin eta ibilbideko Middleware espezifikoekin metatzen dira.

Fase bakoitzean exekuzio-ordena hau da:

```text
globala
↓
kanpoko taldea
↓
barneko taldea
↓
ibilbidea
```

### 1. Aurrizkiak

Aurrizkiak hainbat ibilbidek URL hasiera bera partekatzen dutenean erabiltzen dira. Habiaratu daitezke eta aurrizki bakoitza aktibo dagoenari gehitzen zaio.

```php
use Osumi\OsumiFramework\App\Middleware\AdminAuthMiddleware;
use Osumi\OsumiFramework\Core\OMiddleware;

ORoute::prefix(
	'/api',
	static function (): void {
		ORoute::get('/health', HealthComponent::class);

		ORoute::prefix(
			'/admin',
			static function (): void {
				ORoute::post('/login', LoginComponent::class);
				ORoute::get('/me', MeComponent::class);
			},
			[
				OMiddleware::PHASE_BEFORE => [
					AdminAuthMiddleware::class
				]
			]
		);
	}
);
```

Horrek `/api/health`, `/api/admin/login` eta `/api/admin/me` erregistratzen ditu.

### 2. Layout-ak

Erabili `ORoute::layout()` hainbat ibilbidek egitura bisual bera partekatzen dutenean.

```php
ORoute::layout(
	MainLayoutComponent::class,
	static function (): void {
		ORoute::get('/home', HomeComponent::class);
		ORoute::get('/contact', ContactComponent::class);
	}
);
```

`layout()` metodoak Middleware definizioak ere jaso ditzake hirugarren argumentu gisa.

### 3. Taldeak (aurrizkia + layout-a)

`ORoute::group()`-ek aurrizki bat eta layout bat konbinatzen ditu. Taldeak beste talde edo aurrizki batzuekin habiaratu daitezke.

```php
ORoute::group(
	'/admin',
	AdminLayoutComponent::class,
	static function (): void {
		ORoute::group(
			'/users',
			UserLayoutComponent::class,
			static function (): void {
				ORoute::get('/profile', ProfileComponent::class);
			}
		);
	}
);
```

Aurreko ibilbidea `/admin/users/profile` helbidean erregistratzen da eta `UserLayoutComponent` erabiltzen du.

`group()` metodoak Middleware definizioak ere jaso ditzake laugarren argumentu gisa.

### URL normalizazioa

`ORoute`-ren metodo estatiko guztiek URLak normalizatzen dituzte. Hasierako barrak bateratzen dira, barra errepikatuak bakarrera murrizten dira eta amaierako barrak kentzen dira, erroko `/` URLan izan ezik.

Adibidez:

```php
ORoute::prefix('/api/', static function (): void {
	ORoute::prefix('//admin///', static function (): void {
		ORoute::get('//users/', UsersComponent::class);
	});
});
```

Horrek `/api/admin/users` erregistratzen du.

---

## Ikuspegi estatikoak

Erabili `ORoute::view()` fitxategi estatiko bat edo txantiloi sinple bat zerbitzatzeko ekintza-osagai oso bat behar izan gabe.

```php
ORoute::view('/about-us', 'about-us.html');
```

Ikuspegi estatikoen ibilbideek Middleware definizioak ere jaso ditzakete.

---

## Ibilbideetako parametroak

URLek parametroak defini ditzakete `:name` sintaxia erabiliz.

```php
ORoute::get('/user/:id', UserComponent::class);
ORoute::get('/location/:name', LocationComponent::class);
```

`run(ORequest $req)` erabiltzen duen osagai batek `getParamInt('id')` edo `getParamString('name')` bezalako metodoekin atzitu ditzake.

---

## `ORoute` metodoen laburpena

| Metodoa | Deskribapena |
| ------- | ------------ |
| `get()` | GET ibilbide bat erregistratzen du. |
| `post()` | POST ibilbide bat erregistratzen du. |
| `put()` | PUT ibilbide bat erregistratzen du. |
| `delete()` | DELETE ibilbide bat erregistratzen du. |
| `view()` | Fitxategi estatiko bat zuzenean errendatzen duen ibilbidea erregistratzen du. |
| `prefix()` | Ibilbideak aurrizki metagarri eta habiaragarri baten eta aukerako Middlewareen azpian taldekatzen ditu. |
| `layout()` | Ibilbideak layout komun baten eta aukerako Middlewareen azpian taldekatzen ditu. |
| `group()` | Ibilbideak aurrizki habiaragarri, layout eta aukerako Middlewareekin taldekatzen ditu. |

---

## Praktika onak

- **Fitxategika antolatu**: Sortu `src/Routes/` barruan fitxategi desberdinak modulu edo funtzio-eremu bakoitzerako.
- **Erabili Middlewareak**: Mantendu osagaiak garbi eskaera/erantzun logika zeharkakoa Middlewareetara eramanez.
- **Erabili fase-konstanteak**: Hobetsi `OMiddleware::PHASE_*` konstanteak.
- **Klase-konstanteak**: Erabili `::class` osagai, layout eta Middlewareetarako.
