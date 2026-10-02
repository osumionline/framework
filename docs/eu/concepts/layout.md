# Layout-ak

**Osumi Framework**-en, layout bat ibilbideko osagai nagusiaren irteera inguratzen duen osagai berezi bat da.

Layout-ak normalean hainbat ibilbidetan HTML egitura bera partekatzeko erabiltzen dira.

Layout-a ibilbideko osagaiaren eta `afterRender` Middleware fasearen ondoren aplikatzen da. Uneko osagaiaren irteera `body` gisa jasotzen du.

---

## 1. Errendatze-fluxua

Bat datorren ibilbide normal batean, fluxua hau da:

```text
Bideratzea
↓
before Middlewareak
↓
Osagaia
↓
afterRender Middlewareak
↓
Layout-a
↓
afterResponse Middlewareak
↓
HTTP erantzuna
```

Horrek esan nahi du:

- Ekintza-osagaiak orriaren edukia sortzen du.
- `afterRender` Middlewareek edukia layout-a aplikatu aurretik ikuskatu edo ordezka dezakete.
- Layout-ak emaitzazko edukia inguratzen du.
- `afterResponse` Middlewareak azken gorputza sortu ondoren exekutatzen dira.

`before` Middleware batek exekuzioa gelditzen badu, osagaia eta layout-a ez dira exekutatzen.

`afterRender` Middleware batek exekuzioa gelditzen badu, layout-a ez da exekutatzen.

Bi kasuetan, `afterResponse` exekutatzen jarraitzen da erantzuna bidali aurretik.

---

## 2. Layout lehenetsia

Proiektu berriek layout lehenetsi bat dute.

`DefaultLayoutComponent`-ek normalean honako propietateak eskaintzen ditu:

- `title`
- `body`

Txantiloi lehenetsiak hauek erabiltzen ditu:

- `{{title}}`
- `{{body}}`

---

## 3. Layout-ak bideratzean definitzea

### Layout taldea

```php
ORoute::layout(
	MainLayoutComponent::class,
	static function (): void {
		ORoute::get('/home', HomeComponent::class);
		ORoute::get('/contact', ContactComponent::class);
	}
);
```

`ORoute::layout()`-ek Middleware definizioak dituen hirugarren argumentu aukerakoa onartzen du.

### Layout + aurrizki taldea

```php
ORoute::group(
	'/admin',
	AdminLayoutComponent::class,
	static function (): void {
		ORoute::get('/dashboard', DashboardComponent::class);
		ORoute::get('/settings', SettingsComponent::class);
	}
);
```

`ORoute::group()`-ek Middleware definizioak dituen laugarren argumentu aukerakoa onartzen du.

Ikusi `/docs/eu/concepts/middlewares.md`.

---

## 4. CSS / JS injekzioa

Layout-ak Osumi Framework-ek konfiguratutako frontend baliabideak `</head>` duten dokumentuetan injektatzeko puntua dira.

---

## 5. Praktika onak

- Mantendu layout-ak egituran zentratuta.
- Ez sartu negozio-logikarik layout-etan.
- Erabili layout desberdinak aplikazioaren eremu desberdinetarako beharrezkoa denean.
- Hobetsi `ORoute::layout()` eta `ORoute::group()` konfigurazio koherentea mantentzeko.
- Erabili `afterRender` Middlewareak irteera layout-a aplikatu aurretik aldatu behar denean.

---

## 6. Laburpena

- Layout-ek ibilbideko osagaiaren irteera inguratzen dute.
- `afterRender` layout-a baino lehen exekutatzen da.
- `afterResponse` azken gorputza sortu ondoren exekutatzen da.
- `before` edo `afterRender` faseetako `stop` batek layout-aren errendatzea saihesten du.
- Ibilbide-taldeek layout-ak eta Middlewareak konbina ditzakete.
