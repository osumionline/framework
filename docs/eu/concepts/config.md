# Konfigurazioa

Osumi Framework-en konfigurazioa `src/Config/` barruko JSON fitxategien bidez kudeatzen da.

---

## Konfigurazio-fitxategiak

1. `Config.json`: konfigurazio nagusia.
2. `Config_{environment}.json`: ingurunearen araberako aukerako gainidazketak.

---

## Oinarrizko konfigurazio blokeak

### Aplikazioaren konfigurazioa

```json
{
	"name": "Nire aplikazioa",
	"lang": "eu",
	"use-session": true,
	"allow-cross-origin": true,
	"base_url": "https://example.com",
	"css_list": [],
	"js_list": [],
	"head_elements": []
}
```

### Datu-basea (`db`)

```json
{
	"db": {
		"driver": "mysql",
		"host": "localhost",
		"user": "root",
		"pass": "secret",
		"name": "my_database",
		"charset": "utf8mb4",
		"collate": "utf8mb4_unicode_ci"
	}
}
```

### Logging-a (`log`)

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

### Direktorio pertsonalizatuak (`dir`)

```json
{
	"dir": {
		"uploads": "{{base}}public/uploads/",
		"exports": "{{ofw_export}}my_reports/"
	}
}
```

### Konfigurazio gehigarria (`extra`)

```json
{
	"extra": {
		"api_key": "12345-abcde",
		"items_per_page": 20
	}
}
```

---

## Baliabideak eta `head` elementuak

`css_list` eta `js_list` arrayek `public` azpian ebazten diren izenak onartzen dituzte.

`head_elements`-ek `item` bat eta `attributes` objektu bat dituzten sarrerak onartzen ditu.

---

## Konfigurazioa kodetik atzitzea

```php
$apiKey = $this->getConfig()->getExtra('api_key');
$uploadPath = $this->getConfig()->getDir('uploads');
$dbName = $this->getConfig()->getDB('name');
```

---

## Inguruneko gainidazketak

Balio bat `Config.json`-en eta hautatutako inguruneko fitxategian agertzen bada, inguruneko balioak nagusia gainidazten du.

---

## Aplikazioaren bideak

`OConfig`-ek honako bide lehenetsi hauek definitzen ditu:

| Giltza | Bidea | Deskribapena |
| ------ | ----- | ------------ |
| `base` | `/` | Aplikazioaren oinarrizko bidea |
| `app` | `/src/` | Aplikazioaren kodea |
| `app_component` | `/src/Component/` | Osagai berrerabilgarriak |
| `app_config` | `/src/Config/` | Konfigurazio-fitxategiak |
| `app_dto` | `/src/DTO/` | DTO klaseak |
| `app_layout` | `/src/Layout/` | Layout osagaiak |
| `app_middleware` | `/src/Middleware/` | Middleware klaseak eta Middleware globalen konfigurazioa |
| `app_model` | `/src/Model/` | Datu-baseko modelo-fitxategiak |
| `app_routes` | `/src/Routes/` | Aplikazioaren ibilbideak |
| `app_service` | `/src/Service/` | Zerbitzu berrerabilgarriak |
| `app_task` | `/src/Task/` | Aplikazioaren CLI zereginak |
| `app_utils` | `/src/Utils/` | Erabilgarritasun-klaseak |
| `ofw` | `/ofw/` | Framework-ak sortutako fitxategiak |
| `ofw_cache` | `/ofw/cache/` | Cache fitxategiak |
| `ofw_export` | `/ofw/export/` | Esportatutako fitxategiak |
| `ofw_tmp` | `/ofw/tmp/` | Aldi baterako fitxategiak |
| `ofw_logs` | `/ofw/logs/` | Log fitxategiak |
| `ofw_base` | `/vendor/osumionline/framework/` | Framework-aren oinarrizko bidea |
| `ofw_vendor` | `/vendor/osumionline/framework/src/` | Framework-aren kodea |
| `ofw_assets` | `/vendor/osumionline/framework/src/Assets/` | Framework-aren baliabideak |
| `ofw_locale` | `/vendor/osumionline/framework/src/Assets/locale/` | Framework-aren hizkuntza-fitxategiak |
| `ofw_template` | `/vendor/osumionline/framework/src/Assets/template/` | Framework-aren txantiloiak |
| `ofw_task` | `/vendor/osumionline/framework/src/Task/` | Framework-aren CLI zereginak |
| `ofw_tools` | `/vendor/osumionline/framework/src/Tools/` | Framework-aren tresnak |
| `public` | `/public/` | Aplikazioaren document root-a |

---

## Praktika onak

- Ez igo sekreturik biltegira.
- Erabili ingurunearen araberako konfigurazioa beharrezkoa denean.
- Balidatu getter generikoek itzulitako balioak haien mota garrantzitsua denean.
- Mantendu JSON fitxategiak sintaktikoki zuzen.
