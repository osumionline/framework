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

### DECIMAL eremu zehatzak

Puntu mugikorreko irudikapenetik igaro behar ez duten balioetarako, hala nola diru-kopuruak, ehunekoak edo zehaztasun finkoko kantitateak, erabili `OField::DECIMAL`.

```php
#[OField(
	type: OField::DECIMAL,
	precision: 12,
	scale: 2,
	nullable: false,
	comment: 'Zenbatekoa'
)]
public ?string $amount = null;
```

`DECIMAL` eremuak beti `?string` bidez irudikatzen dira, inoiz ez `?float` bidez.

ORM-ak `.` eta `,` onartzen ditu sarrerako bereizle hamartar gisa, eta balioa `.` erabiliz eta scale finkoarekin normalizatzen du:

```php
$model->amount = '12,5';
$model->save();

// $model->amount === '12.50'
```

Ez da biribiltze automatikorik egiten. Balio batek konfiguratutako precision edo scale gainditzen badu, ORM-ak errorea sortzen du.

Kontsulten baldintzek arau berak jarraitzen dituzte:

```php
Payment::where([
	'amount' => '12,5'
]);
```

barnean `"12.50"` baliora normalizatzen da; aldiz:

```php
Payment::where([
	'amount' => 12.5
]);
```

ez da baliozkoa, `float` balio bat ezin baita `DECIMAL` eremu baterako erabili.

`DECIMAL` eremuak ezin dira lehen mailako gako gisa erabili.

---

### DATE eta DATETIME eremuak

Osumi Framework 9.12 bertsiotik aurrera, SQL `DATE` eta `DATETIME` motak bereizita irudikatzen dira.

SQL `DATE` eremu batek `OField::DATE` eta PHP `?string` propietate bat erabiltzen ditu:

```php
#[OField(
	type: OField::DATE,
	nullable: false
)]
public ?string $event_date = null;
```

Bere formatu kanonikoa hau da:

```text
Y-m-d
```

adibidez:

```text
2026-10-14
```

SQL `DATETIME` eremu batek `OField::DATETIME` erabiltzen du:

```php
#[OField(
	type: OField::DATETIME,
	nullable: false
)]
public ?string $processed_at = null;
```

Bere formatu kanonikoa hau da:

```text
Y-m-d H:i:s
```

adibidez:

```text
2026-10-14 15:30:45
```

ORM-ak denbora-balioak modu zorrotzean balidatzen ditu. `2026-02-30` bezalako existitzen ez diren datak edo baliogabeko orduak baztertzen dira.

`#[OCreatedAt]`, `#[OUpdatedAt]` eta `#[ODeletedAt]` atributu automatikoek `DATETIME` erabiltzen dute barnean.

Osumi Framework 9.12.1 bertsiotik aurrera, `DATETIME` eremuek `OField::CURRENT_TIMESTAMP` erabil dezakete balio lehenetsi dinamiko gisa:

```php
#[OField(
	type: OField::DATETIME,
	nullable: false,
	default: OField::CURRENT_TIMESTAMP
)]
public ?string $joined_at = null;
```

SQL eskema sortzean, definizio horrek hau sortzen du:

```sql
`joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
```

Modelo berrietan, ORM-ak `OField::CURRENT_TIMESTAMP` uneko timestamp-era bihurtzen du `Y-m-d H:i:s` formatuan balio lehenetsiak aplikatzen direnean.

`OField::CURRENT_TIMESTAMP` `OField::DATETIME` eremuekin soilik erabil daiteke. `CURRENT_TIMESTAMP` edukia duen testu literal bat testu-balio arrunta izaten jarraitzen du eta ez da SQL adierazpen gisa interpretatzen.

`generateModelFromDB` tresnak `CURRENT_TIMESTAMP` eta `CURRENT_TIMESTAMP()` ezagutzen ditu `DATETIME` zutabeetan eta `default: OField::CURRENT_TIMESTAMP` sortzen du. Beste SQL adierazpen dinamikoek ez dute euskarririk eta esplizituki baztertzen dira.

`get()` metodoak bi denbora-motei formatu-maskara bat aplika diezaieke:

```php
$model->get(
	'event_date',
	'd/m/Y'
);

$model->get(
	'processed_at',
	'd/m/Y H:i'
);
```

Txantiloietako `date` pipe-ak bi formatuak onartzen ditu. Maskara espliziturik gabe:

- `DATE` eremuak `d/m/Y` erabiltzen du;
- `DATETIME` eremuak `d/m/Y H:i:s` erabiltzen du.

---

### BIGINT eremuak

SQL `BIGINT` sinatutako zutabeek `OField::BIGINT` eta PHP `?int` propietateak erabiltzen dituzte:

```php
#[OField(
	type: OField::BIGINT
)]
public ?int $external_id = null;
```

Lehen mailako gako gisa ere erabil daitezke:

```php
#[OPK(
	type: OField::BIGINT
)]
public ?int $id = null;
```

`BIGINT` erabiltzeko 64 biteko PHP ingurunea behar da.

ORM-ak ez ditu `BIGINT UNSIGNED` zutabeak edo `ZEROFILL` modifikatzaileak onartzen.

---

## CLI integrazioa

- **`generateModel`**: Modelo-definizioetatik SQL egitura sortzen du, `DECIMAL(precision,scale)`, `DATE`, `DATETIME` eta `BIGINT` barne.
- **`generateModelFrom`**: JSON definizio batetik modeloak sortzen ditu eta `OField::DECIMAL`, `OField::DATE`, `OField::DATETIME` eta `OField::BIGINT` onartzen ditu.
- **`generateModelFromDB`**: SQL `DECIMAL(p,s)`, `DATE`, `DATETIME` eta `BIGINT` zutabeak interpretatzen ditu, ORM-ak onartzen dituen motak eta metadatuak mantenduz.

---

## Praktika onak

- **Motatutako propietateak**: Erabili balio null publikoak dituzten motatutako propietateak (adibidez, `public ?string $name = null`) hasieratze garbi bat lortzeko.
- **Balio lehenetsiak**: Hasieratu beti propietateak `null` baliora "hasi gabeko propietatea" erroreak saihesteko.
