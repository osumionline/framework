# CLI (Komando-lerroko interfazea)

Osumi Framework-ek komando-lerroko tresnak eskaintzen ditu aplikazioen garapenerako, mantentzerako eta framework-aren migrazioetarako.

Aplikazioaren CLIaren sarrera-puntua proiektuaren erroko `of` fitxategia da.

---

## Aplikazioaren CLIa

```bash
php of <aukera> [argumentuak]
```

Argumenturik gabe, CLIak framework-aren eta aplikazioaren zeregin eskuragarriak erakusten ditu.

### Oinarrizko komandoak

Framework-ak honako zereginak eskaintzen ditu, besteak beste:

- `add`: ekintzak, zerbitzuak, zereginak, modelo-osagaiak, osagaiak edo Middlewareak sortzen ditu.
- `generateModel`
- `generateModelFrom`
- `generateModelFromDB`
- `backupAll`
- `backupDB`
- `extractor`
- `reset`
- `version`

Middleware bat sortzeko adibidea:

```bash
php of add --option middleware --name Login
```

Horrek hau sortzen du:

```text
src/Middleware/LoginMiddleware.php
```

`add`-en `filter` aukera zaharra ez dago 9.9 bertsioan erabilgarri.

---

## Framework migrazioen CLIa

Framework-aren migrazioak Composer-ek instalatutako binarioaren bidez ere erabil daitezke:

```bash
php vendor/bin/ofw-migrate --help
```

Komando hori 9.8.5 → 9.9.0 migrazio-gidan dokumentatzen da xehetasunez.

---

## Zeregin pertsonalizatuak

Aplikazioaren zereginak `src/Task/` direktorioan kokatzen dira eta `OTask` hedatzen dute.

Adibidea:

```php
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Task;

use Osumi\OsumiFramework\Core\OTask;

class AddUserTask extends OTask {
	public function __toString(): string {
		return 'addUser: Erabiltzaile berriak sortzeko zeregina';
	}

	/**
	 * Exekutatu zeregina.
	 *
	 * @param array<string, string|false> $options Zereginaren aukerak.
	 *
	 * @return void
	 */
	public function run(array $options = []): void {
		$name = $options['name'] ?? null;

		if (!is_string($name)) {
			echo "Errorea: Izena beharrezkoa da.\n";
			return;
		}

		echo "Erabiltzailea {$name} ondo sortu da.\n";
	}
}
```

---

## Parametro izendatuak

```bash
php of addUser --name "John Doe"
```

CLI zereginen aukerak beti array gisa ematen dira.

---

## Zereginen ezaugarriak

`OTask` hedatzen duten klaseek honako hauetarako sarbidea dute:

- `getConfig()`
- `getColors()`
- aplikazioaren modeloak eta zerbitzuak, dagokionean

---

## Praktika onak

- Balidatu zereginen argumentuak.
- Mantendu zereginen metodoak ahalik eta gehien tipatuta.
- Dokumentatu zereginen metodoak PHPDoc bidez.
- Erabili `getColors()` CLI irteera irakurgarriago egiteko.
- Mantendu aplikazioaren zereginak `Osumi\OsumiFramework\App\Task` namespace-aren barruan.
