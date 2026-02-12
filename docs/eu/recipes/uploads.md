# Fitxategien igoerak

**Osumi Framework**-en fitxategien igoerak kudeatzeak sistemaren gainerako diseinu-printzipio berdinak jarraitzen ditu:

- **DTOek** sarrerako datuak kudeatu eta balioztatzen dituzte
- **Osagaiek** eragiketa antolatzen dute
- Kargatutako fitxategia **`ORequest`** bidez eskuragarri dago
- Zuk erabakitzen duzu non eta nola gorde behar diren fitxategiak

Errezeta honek erakusten du nola onartu kargatutako fitxategi bat (adibidez, HTML `<input type="file" name="photo">` batetik), nola prozesatu osagai batean eta nola gorde behar den behar bezala.

---

# 1. Kargatzeen funtzionamenduaren ikuspegi orokorra

Erabiltzaile batek fitxategi batekin formulario bat bidaltzen duenean, PHP-k kargatutako fitxategiaren informazioa `$_FILES` barruan jartzen du.

Osumi Framework-ek eskaera-datuak (parametroak, goiburuak, iragazkiak, fitxategiak) **`ORequest`** instantzia batean batzen ditu.\
Zure osagaiaren `run()` metodoaren barrutik, hauetara sar zaitezke:

```php
$req->getFile("photo");
```

Honek PHP igoera array estandarra itzultzen du:

```php
[
  'name'     => 'adibidea.png',
  'type'     => 'image/png',
  'tmp_name' => '/tmp/phpXYZ123',
  'error'    => 0,
  'size'     => 123456
]
```

Framework-aren gainerakoarekin koherentzia mantentzeko, normalean sarbide hau **DTO** baten barruan biltzen duzu, balidazioa eta egitura garbi mantentzeko.

---

# 2. Fitxategien igoeretarako DTO bat sortzea

DTOek edozein eskaera parametro onar dezakete, fitxategiak barne.
Igotako fitxategiak ez direnez goiburuetatik edo iragazkietatik datoz, zuzenean irakurtzen dituzu DTO eraikitzailearen barruko `ORequest`-etik.

DTO adibidea:

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\DTO;

use Osumi\OsumiFramework\DTO\ODTO;
use Osumi\OsumiFramework\DTO\ODTOField;
use Osumi\OsumiFramework\Web\ORequest;

class PhotoUploadDTO extends ODTO {
  #[ODTOField(required: true)]
  public ?array $photo = null;

  public function __construct(ORequest $req) {
    parent::__construct($req);

    // Kargatu igotako fitxategia
    $this->photo = $req->getFile('photo');

    // Balioztatu fitxategiaren presentzia
    if ($this->photo === null || $this->photo['error'] !== 0) {
      $this->validation_errors[] = "A valid file is required.";
    }
  }
}
```

### Oharrak:

- `required: true`-k DTO baliogabea izango dela ziurtatzen du fitxategia falta bada
- `getFile('photo')`-k igoera berreskuratzen du
- Balidazioa luzatu dezakezu (fitxategiaren tamaina, mime mota, etab.)

---

# 3. Igoera Osagaia Sortzea

Osagaiak DTO jasotzen du eta igotako fitxategia prozesatzen du.

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Api\UploadPhoto;

use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\App\DTO\PhotoUploadDTO;

class UploadPhotoComponent extends OComponent {
  public string $status = 'ok';
  public string $message = '';
  public ?string $filename = null;

  public function run(PhotoUploadDTO $dto): void {
    if (!$dto->isValid()) {
      $this->status = 'error';
      $this->message = implode(", ", $dto->getValidationErrors());
      return;
    }

    // Igotako fitxategiaren datuak atzitu
    $file = $dto->photo;

    // Helmugako fitxategiaren bide bat sortu
    $new_name = uniqid("photo_") . "_" . basename($file['name']);
    $upload_dir = $this->getConfig()->getDir('uploads');
    $dest = $upload_dir . $new_name;

    // Igotako fitxategia mugitu
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
      $this->status = 'error';
      $this->message = 'Fitxategia gordetzea huts egin du.';
      return;
    }

    $this->filename = $new_name;
    $this->message = 'Fitxategia arrakastaz igo da.';
  }
}
```

### Puntu garrantzitsuak:

- `run()`-k `PhotoUploadDTO` bat jasotzen du
- DTO-k baliozkotasuna bermatzen du
- `move_uploaded_file()`-k fitxategia segurtasunez gordetzen du
- Igoerak konfiguratutako direktorio batean gorde behar dituzu (`uploads` edo antzekoa)
- Osagaiak garbi eta irakurgarri mantentzen dira

---

# 4. HTML formularioaren adibidea

Web orrialde batetik amaierako puntu hau kontsumitzean:

### Garrantzitsua:

`enctype="multipart/form-data"` beharrezkoa da PHP-k `$_FILES` betetzeko.

---

# 5. Ibilbidea definitzea

Gehitu POST eskaera bat jasotzen duen eta zure igoera osagaiarekin mapatzen duen ibilbide bat:

```php
use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\App\Module\Api\UploadPhoto\UploadPhotoComponent;

ORoute::post('/api/upload-photo', UploadPhotoComponent::class);
```

Kargatzeak autentifikazioa behar badu, gehitu zure iragazkia:

```php
ORoute::post('/api/upload-photo', UploadPhotoComponent::class, [LoginFilter::class]);
```

---

# 6. JSON Erantzun Txantiloiaren Adibidea

Zure osagaiak `.json` txantiloi bat erabiltzen badu, irteera hau izan daiteke:

```json
{
	"status": "{{ status }}",
	"message": "{{ message }}",
	"filename": "{{ filename }}"
}
```

---

# 7. Baliozkotzea Hedatzea

Baliozkotze eredu ohikoenak hauek dira:

### Onartutako luzapenak:

```php
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['png','jpg','jpeg'])) {
  $this->validation_errors[] = "Fitxategi-luzapen baliogabea.";
}
```

### Fitxategiaren gehienezko tamaina:

```php
if ($file['size'] > 2 * 1024 * 1024) { // 2MB
$this->validation_errors[] = "Fitxategia handiegia da.";
}
```

### MIME mota baliodunak:

```php
$allowed = ['image/png','image/jpeg'];
if (!in_array($file['type'], $allowed)) {
  $this->validation_errors[] = "Fitxategi mota baliogabea.";
}
```

Arauak zerbitzu dedikatu batean gorde ditzakezu DTOak garbi mantentzeko.

---

# 8. Fitxategien metadatuak modeloetan gordetzea

Kargatzeko informazioa datu-base batean gorde nahi baduzu:

```php
$photo = new Photo();
$photo->filename = $new_name;
$photo->user_id = $userId; // LoginFilter erabiltzen bada
$photo->save();
```

Hau normalean zerbitzu baten barruan egiten da, osagai batean zuzenean baino.

---

# 9. Praktika Onenak

- **Erabili DTOak** igotako fitxategiak balioztatzeko
- **Erabili zerbitzuak** fitxategiekin lotutako logikarentzat hazten bada (irudien tamaina aldatzea, miniaturak sortzea, etab.)
- **Ez fidatu inoiz bezeroak emandako metadatuetan** (beti ikuskatu MIME mota, luzapena, tamaina)
- **Mantendu igotzeko direktorioak sarbide publikotik kanpo** fitxategiak agerian egon behar ez badira
- **Eman izena fitxategiei modu bakarrean** erabiltzaileen fitxategiak gainidatzi ez daitezen
- **Erabili iragazkiak** autentifikatutako erabiltzaileek soilik fitxategiak igo ditzakete

---

# 10. Laburpena

Igoerak Osumi Framework-en inplementatzeko:

1. Sortu **DTO** bat fitxategia jasotzeko eta balioztatzeko
2. Sortu **osagai** bat prozesatu eta gordetzeko
3. Gehitu **ibilbide** bat osagaira seinalatzen duena
4. Erabili `ORequest->getFile(izena)` igotako fitxategietara sartzeko
5. Gorde itzazu `move_uploaded_file()` erabiliz
6. Gehitu autentifikazio iragazkiak beharrezkoa bada

Ikuspegi honek zure igotzeko logika framework-aren diseinuarekin koherentea mantentzen du: garbia, modularra eta aurreikusgarria.
