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

| Parametroa | Mota     | Lehenetsia | Deskribapena                                              |
| :--------- | :------- | :--------- | --------------------------------------------------------- |
| `type`     | `string` | `null`     | Datu mota (Derrigorrezkoa).                               |
| `nullable` | `bool`   | `true`     | Eremuak `null` balioak gorde ditzakeen ala ez.            |
| `default`  | `mixed`  | `null`     | Zutabearen balio lehenetsia.                              |
| `max`      | `int`    | `50`       | Eremuaren gehienezko tamaina/luzera.                      |
| `comment`  | `string` | `''`       | Datu-baseko zutabearen iruzkina.                          |
| `visible`  | `bool`   | `true`     | Eremua eredua serializatzean sartzen den ala ez.          |
| `ref`      | `string` | `''`       | Atzerriko gako erreferentzia (formatua: `'table.field'`). |

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

## Datu Motak (`OField` Konstanteak)

`#[OPK]` edo `#[OField]`-n `type` definitzerakoan, erabili `Osumi\OsumiFramework\ORM\OField` klaseko konstante hauek:

- `OField::NUMBER`: Balio osoak.
- `OField::TEXT`: Kate laburrak (normalean `VARCHAR`-era mapatzen dira).
- `OField::LONGTEXT`: Testu bloke handiak (normalean `LONGTEXT`-era mapatzen dira).
- `OField::FLOAT`: Koma mugikorreko zenbakiak.
- `OField::BOOL`: Balio boolearrak.
- `OField::DATE`: Data/Ordu kateak.

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
