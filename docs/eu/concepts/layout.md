# Diseinuak

**Osumi Framework**-en, **diseinua** osagai mota berezi bat da, ekintza nagusiko osagaiaren irteera biltzen duena.

Diseinuak normalean HTML egitura bera hainbat ibilbidetan partekatzeko erabiltzen dira (adibidez: `<head>`, metadatuak, goiburua/oina, script/estilo injekzioa, etab.).

Diseinu bat aplikatzen da ibilbide osagaia exekutatu eta errendatu ondoren, eta errendatutako irteera jasotzen du bere `body` gisa.

---

## 1. Diseinuek nola funtzionatzen duten (Errendatze fluxua)

Ibilbide bat bat datorrenean, Osumi Framework-ek sekuentzia hau exekutatzen du:

1. Ibilbidea ebazten da (Bideratzea).
2. Iragazkiak exekutatzen dira (baldin badaude).
3. Ibilbide osagaia instantziatu eta errendatu egiten da.
4. Ibilbiderako diseinu bat definituta badago, diseinua instantziatu egiten da eta hau jasotzen du:
    - `title`: konfigurazioko izenburu lehenetsia
    - `body`: ibilbide osagaiaren errendatutako irteera
5. Diseinu txantiloia errendatzen da, azken erantzuna sortuz.

Horrek esan nahi du:

- Zure **ekintza osagaiak** **orrialdearen edukia** sortzean zentratzen da.
- Zure **diseinuak** egitura partekatua eskaintzen du eta eduki hori biltzen du.

---

## 2. Diseinu lehenetsia

Osumi Framework proiektu berri bat sortzen duzunean, diseinu lehenetsi bat sortzen da.

### 2.1 Diseinu lehenetsiaren osagaia

`DefaultLayoutComponent` osagai oso sinplea da, bere txantiloiak erabiltzen dituen propietate publikoak soilik definitzen dituena:

- `title`: orrialdearen izenburua
- `body`: ekintza osagaiaren HTML edukia

### 2.2 Diseinu lehenetsiaren txantiloia

Diseinu lehenetsiaren txantiloiak HTML eskeleto estandar bat dauka eta bi leku-marka erabiltzen ditu:

- `{{title}}` → `<title>`-n txertatua
- `{{body}}` → `<body>`-n txertatua

Horrek diseinu lehenetsia zerbitzariak errendatutako orrialde gehienen bilgarri generiko bihurtzen du.

---

## 3. Bideratzean diseinu bat definitzea (GARRANTZITSUA)

Bi modutan esleitu dezakezu diseinu bat bideratzean:

### 3.1 Diseinu Taldea

Erabili `ORoute::layout()` diseinu bat hainbat ibilbidetan aplikatzeko:

```php
use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\App\Layout\MainLayoutComponent;

ORoute::layout(MainLayoutComponent::class, function() {
  ORoute::get('/home', HomeComponent::class);
  ORoute::get('/contact', ContactComponent::class);
});
```

### 3.2 Diseinua + Aurrizki Taldea

Erabili `ORoute::group()` URL aurrizki bat eta diseinu bat konbinatzeko:

```php
use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\App\Layout\AdminLayoutComponent;

ORoute::group('/admin', AdminLayoutComponent::class, function() {
  ORoute::get('/dashboard', DashboardComponent::class);
  ORoute::get('/settings', SettingsComponent::class);
});
```

> Hau da aplikazioaren eremu batean diseinu pertsonalizatu bat modu koherentean aplikatzeko gomendatutako modua.

---

## 4. CSS / JS Injekzioa

Diseinuak dira Osumi Framework-ek CSS eta JS baliabideak injektatzen dituen lekua ere.

Diseinuaren irteerak `</head>` etiketa bat duenean, framework-ak automatikoki txertatzen du:

- Konfiguratutako fitxategietatik CSS lerrokatua (`<style>...</style>`)
- Konfiguratutako fitxategietatik JS lerrokatua (`<script>...</script>`)
- Kanpoko CSS (`<link ...>`) `ext_css_list` konfiguraziotik
- Kanpoko JS (`<script src=...>`) `ext_js_list` konfiguraziotik

Horrek diseinua frontend aktibo globalak muntatzen diren puntu natural bihurtzen du.

---

## 5. Praktika onak

- Mantendu diseinuak egitura hutsez (HTML eskeletoa + UI partekatua).
- Ez jarri negozio logika diseinuetan.
- Erabili atal bakoitzeko diseinu dedikatu bat behar izanez gero (adibidez, `MainLayout`, `AdminLayout`).
- Hobetsi `ORoute::layout()` / `ORoute::group()` koherentzia lortzeko.

---

## 6. Laburpena

- Diseinuek ibilbide-osagaien errendatutako irteera biltzen dute.
- Diseinuek `izenburua` eta `gorputza` jasotzen dituzte.
- Diseinu lehenetsi bat sortzen da proiektu berrietan.
- Bideratzean **diseinu pertsonalizatua** defini dezakezu `ORoute::layout()` edo `ORoute::group()` erabiliz.
- Diseinuak dira CSS/JS injekzio globala gertatzen den lekua.
