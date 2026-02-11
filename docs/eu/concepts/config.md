# Konfigurazioa

Osumi Framework-aren konfigurazioa `src/Config/`-n dauden JSON fitxategien bidez kudeatzen da. Ezarpen hauek aplikazioaren portaera, datu-basearen konexioak, ingurune-aldagai espezifikoak eta gehiago kontrolatzen dituzte.

---

## Konfigurazio Fitxategiak

Framework-ak kargatzeko eredu hierarkiko bat jarraitzen du:

1. **`Config.json`**: Balio lehenetsiak dituen konfigurazio fitxategi nagusia.
2. **`Config_{environment}.json`**: Fitxategi nagusiko balioak gainidazten dituen ingurune-fitxategi espezifiko aukerakoa (adibidez, `Config_prod.json`).

---

## Oinarrizko Konfigurazio Blokeak

### Aplikazioaren Ezarpenak

Aplikazioaren oinarrizko portaeraren parametro globalak.

```json
{
	"izena": "Nire aplikazio bikaina",
	"lang": "eu",
	"use-session": true,
	"allow-cross-origin": true,
	"base_url": "https://adibidea.com"
}
```

### Datu-basea (`db`)

PDO konexioaren konfigurazioa.

```json
{
	"db": {
		"driver": "mysql",
		"host": "localhost",
		"user": "root",
		"pass": "secret",
		"izena": "nire_datu-basea",
		"charset": "utf8mb4",
		"collate": "utf8mb4_unicode_ci"
	}
}
```

### Erregistroa (`log`)

Aplikazioen erregistroen ezarpenak.

```json
{
	"log_level": "DEBUG",
	"log": {
		"name": "app_log",
		"max_file_size": 50,
		"max_num_files": 3
	}
}
```

### Direktorio Pertsonalizatuak (`dir`)

Bide pertsonalizatuak defini ditzakezu dauden direktorioetako leku-markak erabiliz.

```json
{
	"dir": {
		"uploads": "{{base}}public/uploads/",
		"exports": "{{ofw_export}}my_reports/"
	}
}
```

### Ezarpen Gehigarriak (`extra`)

Zure aplikazioak behar dituen datu pertsonalizatuentzako gako-balio biltegi bat (API gakoak, sekretuak, etab.).

```json
{
	"extra": {
		"api_key": "12345-abcde",
		"items_per_page": 20
	}
}
```

---

## Kodean Konfiguraziora Sartzea

`OConfig` objektua normalean framework-aren oinarrizko klaseetan dago eskuragarri (Osagaiak edo Atazak bezala).

```php
// Adibidea: "Extra" balio batera sartzea
$apiKey = $this->getConfig()->getExtra('api_key');

// Adibidea: Direktorio bide batera sartzea
$uploadPath = $this->getConfig()->getDir('uploads');

// Adibidea: DB informazioa sartzea
$dbName = $this->getConfig()->getDB('name');

```

## Balioak gainidazten

Giltza bat `Config.json` fitxategian dagoenean, baina ingurune espezifikoko fitxategian ere definituta dagoenean, azkena aplikatzen da. Hautatutako ingurunea `env` gakoa erabiliz definitzen da:

```json
// Config.json fitxategia
{
	"log_level": "DEBUG",
	"env": "prod"
}

// Config_prod.json fitxategia
{
	"log_level": "ERROR"
}
```

Kasu honetan, "ERROR" izango litzateke `log_level`-ren balioa, gakoa fitxategi globaleko gako gisa eta ingurune espezifikoko gisa definituta baitago.

---

## Aplikazioen bideak

Aplikazioa kargatzen denean, bide lehenetsi multzo bat kargatzen da `OConfig`-en:

| Giltza          | Bidea                                              | Deskribapena                                                               |
| --------------- | -------------------------------------------------- | -------------------------------------------------------------------------- |
| `base`          | /                                                  | Aplikazioen oinarrizko bidea                                               |
| `app`           | /src/                                              | Erabiltzaile kodea                                                         |
| `app_component` | /src/Component/                                    | Berrerabilgarriak diren osagaiak                                           |
| `app_config`    | /src/Config/                                       | Konfigurazio fitxategiak                                                   |
| `app_dto`       | /src/DTO/                                          | Ekintzetan erabilitako DTOak                                               |
| `app_filter`    | /src/Filter/                                       | Ekintzetan erabilitako iragazkiak                                          |
| `app_layout`    | /src/Layout/                                       | Berrerabilgarriak diren diseinuak                                          |
| `app_mode`      | /src/Model/                                        | Datu-basearen eredu fitxategiak                                            |
| `app_routes`    | /src/Routes/                                       | Erabiltzaileak definitutako URLak                                          |
| `app_service`   | /src/Service/                                      | Berrerabilgarriak diren zerbitzu fitxategiak                               |
| `app_task`      | /src/Task/                                         | Erabiltzaileak definitutako zereginak CLIrako                              |
| `app_utils`     | /src/Utils/                                        | Erabilgarritasun klase generikoak                                          |
| `ofw`           | /ofw/                                              | Aplikazioak sortutako fitxategien kokapena (erregistroak, esportazioak...) |
| `ofw_cache`     | /ofw/cache/                                        | Aplikazioaren cache fitxategien bidea                                      |
| `ofw_export`    | /ofw/export/                                       | Esportatutako fitxategien bidea model.sql gisa                             |
| `ofw_tmp`       | /ofw/tmp/                                          | tmp fitxategien bidea                                                      |
| `ofw_logs`      | /ofw/logs/                                         | Sortutako erregistro fitxategien bidea                                     |
| `ofw_base`      | /vendor/osumionline/framework/                     | Framework-aren oinarrizko bidea                                            |
| `ofw_vendor`    | /vendor/osumionline/framework/src/                 | Framework-aren kodea                                                       |
| `ofw_assets`    | /vendor/osumionline/framework/src/Assets/          | Framework-aren aktiboak (locale-ak, txantiloiak)                           |
| `ofw_locale`    | /vendor/osumionline/framework/src/Assets/locale/   | Framerowk-en lokalizazio fitxategiak (en, es, eu)                          |
| `ofw_template`  | /vendor/osumionline/framework/src/Assets/template/ | Fitxategi berriak sortzeko framework txantiloiak                           |
| `ofw_task`      | /vendor/osumionline/framework/src/Task/            | Framework CLI zereginak                                                    |
| `ofw_tools`     | /vendor/osumionline/framework/src/Tools/           | Framework barne tresnak                                                    |
| `public`        | /public/                                           | Aplikazioaren DocumentRoot                                                 |

---

## Konfigurazio-giltzen laburpena

| Giltza        | Mota      | Deskribapena                                         |
| ------------- | --------- | ---------------------------------------------------- |
| `name`        | Katea     | Aplikazioaren izena.                                 |
| `lang`        | Katea     | Hizkuntza lehenetsia (adibidez, "en", "es").         |
| `use-session` | Boolearra | PHP saioak gaitu ala ez.                             |
| `db`          | Objektua  | Datu-basearen konexioaren xehetasunak.               |
| `dir`         | Objektua  | Direktorio pertsonalizatuen definizioak.             |
| `extra`       | Objektua  | Giltza-balio bikote pertsonalizatuak.                |
| `error_pages` | Objektua  | 403, 404 edo 500 erroreetarako URL pertsonalizatuak. |
| `libs`        | Matrizea  | Kargatzeko hirugarrenen liburutegien zerrenda.       |

---

## Praktika onak

- **Segurtasuna**: Ez bidali inoiz informazio sentikorra (pasahitzak, API giltzak) `Config.json` fitxategian. Erabili bertsio-kontroletik kanpo dauden ingurune-fitxategi espezifikoak.
- **Ingurune aldagaia**: Ziurtatu `environment` gakoa zure `Config.json` nagusian ezarrita dagoela bigarren mailako konfigurazio fitxategien karga abiarazteko.
- **Idatzitako gehigarriak**: Gogoratu `getExtra()`-k hainbat mota itzul ditzakeela; beharrezkoa bada, balioztatu itzazu.
- **Formatu zorrotza**: Konfigurazio fitxategiak JSON formatuarekin zorrotz bateragarriak izan behar dira. Edozein errore, koma gehigarri edo antzekoek aplikazioaren errore bat eragingo lukete, ezingo baititu kargatu.
