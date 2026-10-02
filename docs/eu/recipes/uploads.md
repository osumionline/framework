# Fitxategien igoerak

**Osumi Framework 9.9**-en fitxategien igoerek eskaeren gainerako kudeaketaren egitura bera jarraitzen dute:

- `ORequest`-ek igotako fitxategiak eskaintzen ditu.
- DTOek igoerarekin lotutako datuak baliozkotu ditzakete.
- Osagaiek eskaera orkestratzen dute.
- Zerbitzuak egokiak dira fitxategi-prozesamendu berrerabilgarrirako.
- Middlewareek igoera-ibilbideak babes ditzakete.

---

# 1. Igotako fitxategi bat atzitzea

PHP-k igotako fitxategien informazioa `$_FILES` barruan gordetzen du.

Osumi Framework-ek honela eskaintzen du:

```php
$file = $req->getFile(
	'photo'
);
```

Itzulitako balioak PHPren igoera-egitura estandarra jarraitzen du.

Middleware testuingurua bereizita dago eskuragarri `getMiddleware()` eta `getMiddlewareValue()` bidez.

---

# 2. Igoera DTOa

Adibidea:

```php
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\DTO;

use Osumi\OsumiFramework\DTO\ODTO;
use Osumi\OsumiFramework\Web\ORequest;

class PhotoUploadDTO extends ODTO {
	public ?array $photo = null;

	/**
	 * Build the upload DTO.
	 *
	 * @param ORequest $req Current request.
	 */
	public function __construct(ORequest $req) {
		parent::__construct(
			$req
		);

		$this->photo = $req->getFile(
			'photo'
		);
	}
}
```

Baliozkotu fitxategiaren presentzia, igoera-errorea, MIME mota, luzapena eta tamaina gorde aurretik.

---

# 3. Igoera-osagaia

```php
class UploadPhotoComponent extends OComponent {
	public string $status = 'ok';
	public string $message = '';
	public ?string $filename = null;

	/**
	 * Store the uploaded file.
	 *
	 * @param PhotoUploadDTO $dto Upload DTO.
	 *
	 * @return void
	 */
	public function run(PhotoUploadDTO $dto): void {
		$file = $dto->photo;

		if (
			!is_array($file) ||
			!array_key_exists('error', $file) ||
			$file['error'] !== UPLOAD_ERR_OK ||
			!isset(
				$file['tmp_name'],
				$file['name']
			)
		) {
			$this->status = 'error';
			$this->message = 'Baliozko fitxategi bat behar da.';

			return;
		}

		$new_name = uniqid(
			'photo_',
			true
		)
			. '_'
			. basename(
				(string) $file['name']
			);

		$upload_dir = $this->getConfig()->getDir(
			'uploads'
		);

		$destination = $upload_dir
			. $new_name;

		if (
			!move_uploaded_file(
				(string) $file['tmp_name'],
				$destination
			)
		) {
			$this->status = 'error';
			$this->message = 'Ezin izan da fitxategia gorde.';

			return;
		}

		$this->filename = $new_name;
		$this->message = 'Fitxategia ondo igo da.';
	}
}
```

---

# 4. Igoera-ibilbidea babestea

```php
ORoute::post(
	'/api/upload-photo',
	UploadPhotoComponent::class,
	[
		OMiddleware::PHASE_BEFORE => [
			LoginMiddleware::class
		]
	]
);
```

Igoera-osagaia ez da exekutatuko autentifikazio Middlewareak eskaera gelditzen badu.

Autentifikatutako testuingurua honela irakur daiteke:

```php
$id_user = $req->getMiddlewareValue(
	'Login',
	'id'
);
```

edo zuzenean DTO bati lotu `middleware` eta `middlewareProperty` erabiliz.

---

# 5. Balidazioa

Ohiko egiaztapenak:

- `UPLOAD_ERR_OK`
- Gehienezko tamaina
- MIME mota
- Luzapena
- Fitxategi-izenaren normalizazioa
- Helmugako baimenak

Ez fidatu bezeroak emandako MIME metadatuetan balidazio bakar gisa.

---

# 6. Metadatuak gordetzea

```php
$photo = new Photo();
$photo->filename = $new_name;
$photo->user_id = $id_user;
$photo->save();
```

Biltegiratze-logika berrerabilgarria bada, erabili zerbitzu bat.

---

# 7. Praktika onak

- Mantendu igoera-direktorioak sarbide publikotik kanpo, fitxategiak publikoak izan behar ez badira.
- Sortu fitxategi-izen bakarrak.
- Baliozkotu tamaina, MIME mota eta luzapena.
- Erabili zerbitzuak fitxategi-prozesamenduaren logika handitzen denean.
- Erabili Middlewareak igoerek autentifikazioa edo baimena behar dutenean.
- Erabili Middleware testuingurua bezeroak emandako erabiltzaile-IDen ordez.

---

# 8. Laburpena

Igoera-fluxu kanonikoa:

1. Babestu ibilbidea Middleware bidez beharrezkoa bada.
2. Irakurri fitxategia `ORequest::getFile()` erabiliz.
3. Baliozkotu igoera.
4. Gorde fitxategia modu seguruan.
5. Gorde metadatuak beharrezkoa bada.
6. Erabili konfiantzazko Middleware testuingurua autentifikatutako jabetza lotzeko.
