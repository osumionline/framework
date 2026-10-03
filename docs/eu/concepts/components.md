# Osagaiak

Osumi Framework-eko osagaiak kode-zati berrerabilgarri txikiak dira. Normalean txantiloi bat errendatzen dute, baina **Osumi Framework 9.10** bertsiotik aurrera HTTP erantzun bat streaming bidez ere sor dezakete zuzenean `OStreamResponse` erabiliz.

Osagai tradizional batek honako hauek ditu:

- `OComponent` hedatzen duen PHP klase bat.
- Txantiloi-fitxategi bat (`php`, `html`, `json` edo `xml`, erabileraren arabera).

Streaming hutseko osagai batek txantiloia ez eduki dezake, `run()` metodoak `OStreamResponse` itzulera-mota esplizituki deklaratzen badu.

---

## Osagai baten oinarrizko egitura

```php
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Email\LostPassword;

use Osumi\OsumiFramework\Core\OComponent;

class LostPasswordComponent extends OComponent {
	public ?string $token = null;
}
```

```html
<div>
	Token: {{ token }}
</div>
```

---

## Ezaugarri aurreratuak

### Content-Type goiburu automatikoak

Txantiloia duen osagai bat URL baten ekintza nagusi gisa erabiltzen denean, framework-ak `Content-Type` prestatzen du luzapenaren arabera:

- `.json`: `application/json`.
- `.xml`: `text/xml`.
- `.html` / `.php`: `text/html`.

`OStreamResponse` erantzunek beren HTTP goiburuak definitzen dituzte.

### Osagaien habiaratzea

Osagai tradizionalak habiaratu daitezke berrerabilpena sustatzeko.

`OStreamResponse` bat ezin da osagai habiaratu gisa errendatu eta ezin da string bihurtu.

---

## `run()` metodoa

Ibilbide-ekintza gisa erabiltzen denean, parametro-kontratu hauek onartzen dira:

```php
public function run(): void
```

```php
public function run(ORequest $req): void
```

```php
public function run(MyDTO $dto): void
```

Arauak:

- `run()`-ek ez du eskaera-daturik jasotzen.
- `run(ORequest $req)`-ek uneko eskaera jasotzen du.
- `run(MyDTO $dto)`-k eskaeratik betetako DTO bat jasotzen du; `MyDTO`-k `ODTO` hedatu behar du.
- Ezin da parametro bat baino gehiago erabili.
- Parametroa, baldin badago, ezin da nullable izan.

Metodoak txantiloi baterako propietateak presta ditzake edo `OStreamResponse` bat itzul dezake.

### Middleware testuingurua

`ORequest`-ek exekutatutako Middlewareek argitaratutako testuingurua eskaintzen du:

```php
$login = $req->getMiddleware(
	'Login'
);

$id = $req->getMiddlewareValue(
	'Login',
	'id'
);
```

Ez dagoen testuinguru batek `[]` itzultzen du eta ez dagoen balio batek `null`.

Ikusi `/docs/eu/concepts/middlewares.md`.

---

## Streaming erantzunak `OStreamResponse` bidez

Streaming erantzun batek fitxategi handiak edo progresiboki sortutako edukia bidaltzeko aukera ematen du gorputz osoa memorian materializatu gabe.

```php
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Download;

use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Web\OStreamResponse;

class DownloadComponent extends OComponent {
	/**
	 * Stream a file to the client.
	 *
	 * @return OStreamResponse Streamed HTTP response.
	 */
	public function run(): OStreamResponse {
		$file = '/path/to/file.zip';
		$stream = fopen(
			$file,
			'rb'
		);

		if ($stream === false) {
			throw new \RuntimeException(
				'Could not open file.'
			);
		}

		$size = filesize(
			$file
		);

		if ($size === false) {
			fclose(
				$stream
			);

			throw new \RuntimeException(
				'Could not determine file size.'
			);
		}

		return new OStreamResponse(
			$stream,
			[
				'Content-Type' => 'application/zip',
				'Content-Length' => strval($size),
				'Content-Disposition' => 'attachment; filename="file.zip"'
			]
		);
	}
}
```

`run()` metodoak `OStreamResponse` esplizituki deklaratzen duenez, txantiloia ez da beharrezkoa.

Honako sinadura hauek ere onartzen dira:

```php
public function run(ORequest $req): OStreamResponse
```

```php
public function run(MyDTO $dto): OStreamResponse
```

`OStreamResponse` eraikitzaileak honako hauek jasotzen ditu:

1. stream irakurgarria;
2. HTTP goiburuak;
3. HTTP egoera-kodea, lehenespenez `200`;
4. blokearen tamaina, lehenespenez 1 MiB;
5. framework-ak stream-a itxi behar duen, lehenespenez `true`.

### Streaming bizi-zikloa

```text
before Middlewareak
↓
Osagaia
↓
OStreamResponse
↓
afterRender Middlewareak
↓
afterResponse Middlewareak
↓
Datu-baseko konexioak itxi
↓
HTTP goiburuak
↓
Stream-a blokeka bidali
```

Arauak:

- Ez da layout-ik aplikatzen.
- Ez da stream-eko byterik bidaltzen `afterRender` eta `afterResponse` amaitu arte.
- Middlewareek goiburuak eta HTTP egoera alda ditzakete.
- Middlewareek ezin dute `body` ordezkatu erantzuna streaming den bitartean.
- `$data['is_streaming_response']` `true` da fase horietan.
- Middleware batek emisioa hasi aurretik `stop` egiten badu, stream-a baztertzen da eta ohiko errore-erantzuna sortzen da.
- Framework-aren jabetzako stream-ak amaitzean, baztertzean edo erantzuna ustekabean suntsitzean ixten dira.

---

## Osagaiak errendatzea

Txantiloietan oinarritutako osagaiak string bihur daitezke:

```php
$cmp = new BooksComponent();
echo strval($cmp);
```

`OStreamResponse` itzultzen duen osagai bat framework-aren HTTP pipeline-ak kudeatu behar du eta ezin da string bihurtu.

---

## Praktika onak

- Mantendu txantiloiak sinpleak.
- Erabili `run()` datuak prestatzeko.
- Erabili propietate publiko tipatuak.
- Erabili `OStreamResponse` memoria osoan kargatu behar ez diren gorputz handi edo progresiboetarako.
- Deklaratu `OStreamResponse` esplizituki itzulera-mota gisa osagaiak txantiloirik ez badu.
