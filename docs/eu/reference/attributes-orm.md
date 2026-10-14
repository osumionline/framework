# ORM Atributuen Erreferentzia

Osumi Framework-en, datu-baseen mapaketak PHP Atributuak erabiliz definitzen dira modelo klaseko propietateetan. Dokumentu honek eskuragarri dauden atributu guztiak, haien parametroak eta espero diren portaerak zehazten ditu.

---

## `#[OPK]`

Lehen Mailako Gako eremu bat definitzen du. Modelo guztiek gutxienez bat izan behar dute.

| Parametroa | Mota     | Lehenetsia       | Deskribapena                                              |
| :--------- | :------- | :--------------- | --------------------------------------------------------- |
| `type`     | `string` | `OField::NUMBER` | Datu mota (ikus beheko Motak).                            |
| `incr`     | `bool`   | `true`           | Eremua auto-inkrementala den ala ez.                      |
| `comment`  | `string` | `''`             | Datu-baseko zutabearen iruzkina.                          |
| `ref`      | `string` | `''`             | Atzerriko gako erreferentzia (formatua: `'table.field'`). |
| `nullable` | `bool`   | `true`           | Eremuak `null` balioak gorde ditzakeen ala ez.            |
| `default`  | `mixed`  | `null`           | Zutabearen balio lehenetsia.                              |

---

## `#[OField]`

Datu-baseko zutabe estandar bat definitzen du.

| Parametroa  | Mota     | Lehenetsia | Deskribapena                                                                                                                                  |
| :---------- | :------- | :--------- | --------------------------------------------------------------------------------------------------------------------------------------------- |
| `type`      | `string` | `null`     | Datu mota (Derrigorrezkoa).                                                                                                                   |
| `nullable`  | `bool`   | `true`     | Eremuak `null` balioak gorde ditzakeen ala ez.                                                                                                |
| `default`   | `mixed`  | `null`     | Zutabearen balio lehenetsia.                                                                                                                  |
| `max`       | `int`    | `50`       | Eremuaren gehienezko tamaina/luzera.                                                                                                          |
| `comment`   | `string` | `''`       | Datu-baseko zutabearen iruzkina.                                                                                                              |
| `visible`   | `bool`   | `true`     | Eremua eredua serializatzean sartzen den ala ez.                                                                                              |
| `ref`       | `string` | `''`       | Atzerriko gako erreferentzia (formatua: `'table.field'`).                                                                                     |
| `precision` | `?int`   | `null`     | `DECIMAL` eremu baten guztizko zehaztasuna. `OField::DECIMAL` motarekin soilik da baliozkoa; zehazten ez bada, `10` erabiltzen da.            |
| `scale`     | `?int`   | `null`     | `DECIMAL` eremu baten zati hamartarreko digitu kopurua. `OField::DECIMAL` motarekin soilik da baliozkoa; zehazten ez bada, `2` erabiltzen da. |

---

## Denborazko Atributuak

Atributu hauek denbora-zigilu automatikoa kudeatzen dute. Eredu bakoitzak `#[OCreatedAt]` bat eta `#[OUpdatedAt]` bat izan behar ditu.

### `#[OCreatedAt]`

Erregistro bat lehen aldiz sortzen denean automatikoki ezartzen da (INSERT).

- **Parametroa**: `comment` (string) - Zutabearen iruzkina.

### `#[OUpdatedAt]`

Erregistroa aldatzen den bakoitzean automatikoki eguneratzen da (UPDATE).

- **Parametroa**: `comment` (string) - Zutabearen iruzkina.

### `#[ODeletedAt]`

Ezabatze leunaren funtzionalitaterako erabiltzen da (erregistro bat noiz "kendu" den jarraipena egitea).

- **Parametroa**: `comment` (katea) - Zutabearen iruzkina.

---

## Datu motak (`OField` konstanteak)

`#[OPK]` edo `#[OField]` atributuetan `type` definitzean, erabili `Osumi\OsumiFramework\ORM\OField` klaseko konstante hauek:

- `OField::NUMBER`: Balio osoak (`?int`).
- `OField::TEXT`: Kate laburrak (`?string`, normalean `VARCHAR` gisa mapatuak).
- `OField::LONGTEXT`: Testu-bloke handiak (`?string`, normalean `LONGTEXT` gisa mapatuak).
- `OField::FLOAT`: Puntu mugikorreko zenbakiak (`?float`).
- `OField::BOOL`: Balio boolearrak (`?bool`).
- `OField::DATE`: Data/ordu kateak (`?string`).
- `OField::DECIMAL`: Balio hamartar zehatzak (`?string`, `DECIMAL(precision,scale)` sortzen du).

### DECIMAL eremuak

`OField::DECIMAL` motak beti `?string` propietate bat erabili behar du. ORM-ak ez ditu `float` edo `int` balioak onartzen eremu hauetarako, bitarteko puntu mugikorreko bihurketak saihesteko.

```php
#[OField(
	type: OField::DECIMAL,
	precision: 12,
	scale: 2,
	nullable: false
)]
public ?string $amount = null;
```

Lehenetsitako precision `10` da eta lehenetsitako scale `2`. Precision baliozkoak `1` eta `65` artekoak dira; scale baliozkoak `0` eta `30` artekoak dira, eta scale ezin da inoiz precision baino handiagoa izan.

`.` eta `,` onartzen dira sarrerako bereizle hamartar gisa:

```text
"12"    → "12.00"
"12.5"  → "12.50"
"12,5"  → "12.50"
"-0,50" → "-0.50"
```

Barneko irudikapenak beti `.` erabiltzen du eta `scale`-k definitutako zati hamartarreko digitu kopuru zehatza mantentzen du.

Ez dira notazio zientifikoa, milakoen bereizleak edo biribiltzea behar duten balioak onartzen. Onartutakoa baino zati hamartar gehiago duen balio batek errorea sortzen du.

`precision` eta `scale` `OField::DECIMAL` motarekin soilik erabil daitezke. Beste edozein motarekin erabiltzeak modelo-definizioaren errorea sortzen du.

`OField::DECIMAL` ezin da `#[OPK]` atributuan erabili.

---

## Erabilera Adibidea

```php
use Osumi\OsumiFramework\ORM\OModel;
use Osumi\OsumiFramework\ORM\OPK;
use Osumi\OsumiFramework\ORM\OField;
use Osumi\OsumiFramework\ORM\OCreatedAt;
use Osumi\OsumiFramework\ORM\OUpdatedAt;

class Product extends OModel {
    #[OPK(comment: 'Produktuaren ID bakarra')]
    public ?int $id = null;

    #[OField(type: OField::TEXT, max: 150, nullable: false, comment: 'Produktuaren izena')]
    public ?string $name = null;

    #[OField(type: OField::NUMBER, ref: 'category.id', comment: 'Kategoriara esteka')]
    public ?int $id_category = null;

    #[OCreatedAt(comment: 'Sorkuntzaren denbora-zigilua')]
    public ?string $created_at = null;

    #[OUpdatedAt(comment: 'Azken eguneratzearen denbora-zigilua')]
    public ?string $updated_at = null;
}

```

---

## Balidazio Logika

`OModel` oinarrizko klaseak atributu hauek erabiltzen ditu `save()` prozesuan:

1. **Luzeraren egiaztapena**: Propietate-kate batek `max` balioa gainditzen badu, salbuespen bat botatzen da.
2. **Nullability**: Propietate bat `null` bada baina `nullable` `false` baliora ezartzen bada, salbuespen bat botatzen da.
3. **Lehen mailako giltza**: Gutxienez `#[OPK]` bat definituta dagoela ziurtatzen du jarraitu aurretik.
