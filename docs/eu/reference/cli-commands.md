# Osumi Framework CLI komandoak

Osumi Framework-ek aplikazioaren CLI zereginak eta framework migrazioetarako binario dedikatu bat eskaintzen ditu.

## Aplikazioaren CLIa

Aplikazioaren zereginak proiektuaren errotik exekutatzen dira:

```bash
php of <zeregina> [aukerak]
```

### `add`

Framework aplikazioko elementuak sortzen ditu.

```bash
php of add --option <mota> --name <izena>
```

Onartutako motak:

- `action`
- `service`
- `task`
- `modelComponent`
- `component`
- `middleware`

Adibidea:

```bash
php of add --option middleware --name Login
```

`filter` aukera zaharra ez dago 9.9 bertsioan erabilgarri.

### `backupAll`

```bash
php of backupAll
```

Aplikazioaren kopia oso bat sortzen du framework-aren dagozkion backup/esportazio zereginak erabiliz.

### `backupDB`

```bash
php of backupDB
```

Datu-basearen kopia bat sortzen du konfiguratutako datu-basea eta sistemako tresnak erabiliz.

### `extractor`

```bash
php of extractor
```

Aplikazioa framework-aren extractor bidez esportatzen du.

### `generateModel`

```bash
php of generateModel
```

SQL eskema sortzen du aplikazioaren modelo-klaseetatik.

### `generateModelFrom`

```bash
php of generateModelFrom <fitxategia>
```

Modelo-definizio fitxategi batetik modeloak sortzen ditu.

### `generateModelFromDB`

```bash
php of generateModelFromDB
```

Konfiguratutako datu-basetik modelo-klaseak sortzen ditu.

### `reset`

```bash
php of reset
```

Aplikazio-egitura garbi bat birsortzeko babestutako reset fluxua hasten du. Sortutako egiturak `src/Middleware/` eta `src/Middleware/Middlewares.php` barne hartzen ditu.

### `version`

```bash
php of version
```

Framework-aren bertsioari buruzko informazioa erakusten du.

---

## Framework migrazioen CLIa

Composer paketeak honako binario hau eskaintzen du:

```bash
php vendor/bin/ofw-migrate --help
```

Onartutako migrazio-aukerak:

```text
--from
--to
--dry-run
--force
--verbose
--no-interaction
--help
```

Framework migrazioak bertsionatuak eta idempotenteak dira eta beren egoera `ofw/tmp/state.json` fitxategian gordetzen dute.

9.8.5 → 9.9.0 migrazio-gidak migrazio-fluxua eta Composer-ekin integrazio automatikoa xehetasunez dokumentatzen ditu.

---

## Oharrak

- Exekutatu aplikazioaren CLI komandoak proiektuaren errotik.
- Exekutatu migrazio-komandoak proiektuaren errotik runner-ak aplikazio egokian jardun dezan.
- Datu-basearekin lotutako zereginek konfigurazio balioduna behar dute.
