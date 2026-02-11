# ORM (Objektu-Erlazio Mapeatzea)

Osumi Framework-ak ORM arin bat dauka, PHP klaseak datu-baseko tauletara PHP Atributuak erabiliz mapatzen dituena. Modelo guztiek `OModel` klasea zabaldu behar dute.

---

## Modelo bat definitzea

Modeloak `src/Model/`-n gordetzen dira. Framework-ak automatikoki ondorioztatzen du taularen izena klase-izenetik snake_case erabiliz (adibidez, `ProductCategory` klasea `product_category` taulara mapatzen da).

### Nahitaezko eremuak

Modelo bat baliozkoa izateko, honako atributu hauek **definitu behar ditu**:

- **Gutxienez `#[OPK]`** bat: Giltza nagusia definitzen du (giltza konposatuak onartzen ditu).
- **Zehazki `#[OCreatedAt]`** bat: Sortze-denbora-zigilua automatikoki gordetzen du.
- **Zehazki `#[OUpdatedAt]`** bat: Aldaketa bakoitzean automatikoki eguneratzen da.

### Ereduaren Adibidea

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Model;

use Osumi\OsumiFramework\ORM\OModel;
use Osumi\OsumiFramework\ORM\OPK;
use Osumi\OsumiFramework\ORM\OField;
use Osumi\OsumiFramework\ORM\OCreatedAt;
use Osumi\OsumiFramework\ORM\OUpdatedAt;

class User extends OModel {
  #[OPK(comment: 'ID Bakarra')]
  public ?int $id = null;

  #[OField(max: 100, nullable: false)]
  public ?string $name = null;

  #[OCreatedAt(comment: 'Sortze data')]
  public ?string $created_at = null;

  #[OUpdatedAt(comment: 'Azken eguneraketa')]
  public ?string $updated_at = null;
}

```

---

## Oinarrizko Atributuak

| Atributua       | Deskribapena                                                                                                                                             |
| --------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `#[OPK]`        | Lehen mailako giltza. Gako konposatuetarako hainbat eremutan erabil daiteke. Berez, automatikoki gehitzen da, bestela gehitu `incr` balioa `false` gisa. |
| `#[OField]`     | Zutabe estandarra. `max`, `nullable` eta `ref` onartzen ditu.                                                                                            |
| `#[OCreatedAt]` | Erregistroen sorreraren jarraipenerako nahitaezko eremua.                                                                                                |
| `#[OUpdatedAt]` | Erregistroen eguneratzeen jarraipenerako nahitaezko eremua.                                                                                              |

> **Oharra `ref**`-ri buruz: `ref`parametroa (adibidez,`ref: 'user.id'`) sortutako SQL-an (CREATE TABLE) kanpoko gakoak definitzeko erabiltzen da, baina ez du objektuen kargatze automatikoa abiarazten.

---

## Datuekin lan egitea

### Erregistroak aurkitzea

Datuak metodo estatikoak erabiliz berreskuratzea:

- **`where(array $criteria)`**: Irizpideekin bat datozen objektuen array bat itzultzen du.
- **`findOne(array $criteria)`**: Objektu bakarra edo `null` itzultzen du.

```php
  $user = User::where(['id' => 1]);
  if (!is_null($user)) {
    echo "Erabiltzailea ".$user->name." aurkitu da";
  }
```

### Gordetzea eta eguneratzea

`save()` metodoak automatikoki detektatzen du erregistro bat berria edo lehendik dagoen PK-an oinarrituta.

```php
$user = new User();
$user->name = 'Erabiltzaile Berria';
$user->save(); // INSERT bat egiten du

$user = User::findOne(['id' => 1]);
$user->name = 'Izen berria';
$user->save(); // UPDATE bat egiten du

```

### Balidazioa

Framework-ak `validate()` deitzen du `save()` prozesuan. Balio batek `max` luzera gainditzen badu edo `null` bada baimenduta ez dagoenean, **Salbuespen** bat sortzen da.

---

## CLI Integrazioa

- **`generateModel`**: SQL egitura (CREATE TABLE) sortzen du zure modeloaren definizioetan oinarrituta, `ref`-n definitutako indizeak eta kanpoko gakoak barne.

---

## Praktika onak

- **Motatutako propietateak**: Erabili balio null publikoak dituzten motatutako propietateak (adibidez, `public ?string $name = null`) hasieratze garbi bat lortzeko.
- **Balio lehenetsiak**: Hasieratu beti propietateak `null` baliora "hasi gabeko propietatea" erroreak saihesteko.
