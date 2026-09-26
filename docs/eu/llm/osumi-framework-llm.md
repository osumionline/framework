# Osumi Framework – LLM Testuingurua (Guztia Batean)

**Helburua**  
Dokumentu honek testuinguru trinkoa, osoa eta autoritarioa eskaintzen du edozein LLMk **Osumi Framework** behar bezala ulertu, azaldu eta kodea sortzeko.

## 0. Framework-aren identitatea

- **Izena:** Osumi Framework
- **Bertsioa:** 9.8.5
- **Hizkuntza:** PHP
- **Gutxieneko PHP bertsioa:** 8.3+
- **Tipatzea:** `declare(strict_types=1)` derrigorrezkoa da
- **Estiloa:** esplizitua, aurreikusgarria, magia ezkuturik gabe

## 1. Filosofia

Osumi Framework-ek kode esplizitua, aurreikusgarritasuna, arduren bereizketa argia eta konposizioa lehenesten ditu.

## 2. Eskaeraren bizi-zikloa

```text
Bezeroaren eskaera
↓
Bideratzea (ORoute)
↓
Iragazkiak (0..n)
↓
DTO hidratazioa eta baliozkotzea (aukerakoa)
↓
Component::run()
↓
Txantiloiaren errendatzea
↓
Layout-a (aukerakoa)
↓
HTTP erantzuna
```

- Iragazkiak osagaien aurretik exekutatzen dira.
- DTOak `run()` parametro gisa deklaratzen direnean bakarrik sortzen dira.
- Txantiloiek osagaiaren propietate publikoak errendatzen dituzte.

## 3. Eraikuntza-bloke nagusiak

### 3.1 Bideratzea (ORoute)

Bideek URLak osagaiekin lotzen dituzte. GET, POST, PUT eta DELETE onartzen dira. Aurrizkiak eta taldeak habiaratu daitezke eta URLak normalizatzen dira.

### 3.2 Iragazkiak

Iragazkiek autentifikazioa/baimena, eskaeren blokeoa eta testuinguruaren karga kudeatzen dituzte. Irteera `ORequest->getFilter('Name')` bidez dago eskuragarri.

### 3.3 DTOak (ODTO)

- DTOek `ODTO` hedatzen dute.
- Eremuek `#[ODTOField]` erabiltzen dute.
- Framework-ak DTOak instantziatu, bete, baliozkotu eta `run()` metodoan injektatzen ditu.
- DTOak `ODTO`-ren herentziaren bidez detektatzen dira, ez namespace-aren bidez.
- DTOek ez lukete negozio-logikarik izan behar.

### 3.4 Osagaiak (OComponent)

**Helburua:** eskaera antolatzea → zerbitzuak/ereduak → irteera prestatzea → errendatzea.

- Mota publikoko propietateak txantiloietan agertzen dira.
- `run()` aukerakoa da.
- `run()` metodoak honako sinadura hauetako bat izan behar du:
  - `run(): void`
  - `run(ORequest $req): void`
  - `run(MyDTO $dto): void`, non `MyDTO` klaseak `ODTO` hedatzen duen
- DTOak `ODTO`-ren herentziaren bidez detektatzen dira, ez namespace-aren bidez.
- Ez da beste parametro motarik edo sinadurarik onartzen.

Mantendu osagaiak arinak; eraman negozio-logika Zerbitzuetara.

### 3.5 Txantiloiak

Txantiloi-fitxategiak `.php`, `.html`, `.json` edo `.xml` izan daitezke. Txantiloi estatikoek `{{ variable }}` eta pipeak erabiltzen dituzte.

### 3.6 Layout-ak

Layout-ek bide-osagai baten errendatutako irteera egitura partekatu batekin biltzen dute.

### 3.7 Pipeak

Txantiloiek Angular estiloko pipeak onartzen dituzte:

- `date` → `Y-m-d H:i:s` formatua maskara batera (lehenetsia `d/m/Y H:i:s`)
- `number` → `number_format` estiloko formatua
- `string` → `urlencode` irteera komatxo artean
- `plain` → JSONerako kate segurua komatxo artean, URL kodetzerik gabe
- `bool` → `true | false | null`

### 3.8 Zerbitzuak (OService)

Zerbitzuek `OService` hedatzen dute eta negozio-logika berrerabilgarria daukate.

## 4. ORM (OModel)

Modeloek PHP atributuak erabiltzen dituzte. Modelo guztiek gutxienez `#[OPK]` bat, `#[OCreatedAt]` bat eta `#[OUpdatedAt]` bat definitu behar dituzte.

## 5. CLI (OTask)

Task-ek `OTask` hedatzen dute eta `public function run(array $options = []): void` erabiltzen dute.

## 6. Pluginak

Pluginak Composer bidez instalatzen dira `Osumi\OsumiFramework\Plugins` namespace-aren azpian.

## 7. Konfigurazioa

JSON konfigurazio-fitxategiak `src/Config/` direktorioan daude.

## 8. Konbentzio zorrotzak

- Beti `declare(strict_types=1)`
- Klaseak: PascalCase
- Fitxategiak: PascalCase.php
- Taulak: snake_case
- Propietateak/eremuak: snake_case
- Hobetsi propietate tipatuak eta itzulera mota esplizituak.

## 9. Ez suposatu

Ez suposatu propietateen DI automatikoa, erlazioen karga automatikoa, middleware pipeline-ak, serializazio ezkutua edo dokumentatu gabeko helper-ak.

## 10. LLMentzako gida

Erabili dokumentu hau framework-aren testuinguru autoritario gisa. Hobetsi kode esplizitu eta tipatua eta ez asmatu dokumentatu gabeko portaerarik.
