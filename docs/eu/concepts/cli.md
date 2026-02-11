# CLI (Komando-lerroko interfazea)

Osumi Framework-ek komando-lerroko interfaze indartsua eskaintzen du garapen-atazak automatizatzeko, datu-basea kudeatzeko eta script pertsonalizatuak exekutatzeko. CLI komando guztien sarrera-puntua zure proiektuaren erroan dagoen `of` fitxategia da.

---

## Erabilera

Komandoak PHP erabiliz exekutatzen dira terminaletik:

```bash
php of <aukera> [parametroak]

```

Fitxategia parametrorik gabe exekutatzen bada, eskuragarri dauden aukeren zerrenda erakusten da (Framework-eko aukerak eta erabiltzaileak sortutakoak).

### Oinarrizko komandoak

Framework-ak hainbat atazak eskaintzen ditu:

- **`add`**: Ekintza, zerbitzu, ataza, modelo, osagai edo iragazki berriak sortu.
- **`generateModel`**: SQL datu-basearen eskema sortu zure modelo klaseetatik.
- **`generateModelFrom` / `generateModelFromDB`**: Alderantzizko ingeniaritza modeloak sortzeko.
- **`backupAll` / `backupDB`**: Fitxategien eta/edo datu-basearen segurtasun-kopiak sortu.
- **`extractor`**: Aplikazio osoa auto-ateragarri den fitxategi bakar batean esportatu.
- **`reset`**: Framework-ekoak ez diren datu guztiak garbitu instalazio berri bat egiteko.
- **`version`**: Framework-aren uneko bertsioa erakutsi.

---

## Ataza pertsonalizatuak

CLIa zabaldu dezakezu zure atazak sortuz. `src/Task/`-n kokatutako eta `OTask` hedatzen duen edozein klase automatikoki agertuko da `of` komandoan aukera erabilgarri gisa.

### Ataza bat sortzea

Ataza batek bi elementu nagusi behar ditu:

1. **`__toString()`**: Atazaren deskribapen laburra itzultzen du (laguntza-menuan bistaratzen da).
2. **`run(array $options)`**: Exekutatuko den logika.

### Adibidea: `AddUserTask.php`

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Task;

use Osumi\OsumiFramework\Core\OTask;
use Osumi\OsumiFramework\App\Model\User;

class AddUserTask extends OTask {
  public function __toString() {
    return "addUser: Erabiltzaile berriak sortzeko ataza";
 }

  public function run(array $options=[]): void {
    $name = $options['name'] ?? $options[0] ?? null;

    if (is_null($name)) {
      echo "Errorea: Izena beharrezkoa da.\n";
      return;
    }

    $u = new User();
    $u->name = $name;
    $u->save();

    echo "Erabiltzailea " . $izena . " arrakastaz sortu da.\n";
  }
}

```

---

## Parametroak Kudeatzea

`run` metodoak bi sarrera estilo onartzen dituen `$options` array bat jasotzen du:

### 1. Parametro Posizionalak

Komandoaren izenaren ondoren zuzenean pasatzen dira.

```bash
php of addUser "John Doe"
# $options = [0 => "John Doe"]

```

### 2. Parametro Izendatuak

`--key value` sintaxia erabiliz. Horren ondorioz, array asoziatibo bat sortzen da.

```bash
php of addUser --izena "John Doe"
# $options = ["izena" => "John Doe"]

```

> **Oharra `$options$`-ri buruz**: `$options` parametroa beti array bat da, DTOak ezin dira erabili Zereginetan.

---

## Zereginen Ezaugarriak

`OTask` hedatzen duten klaseek hainbat utilitate integraturako sarbidea dute:

- **`$this->getConfig()`**: Aplikazioaren konfiguraziora sartzeko.
- **`$this->getColors()`**: Erabili `OColors` utilitatea testu koloreztatua kontsolara bidaltzeko.
- **ORM Sarbidea**: Edozein Modelo klase erabil dezakezu datu-baseko eragiketak egiteko, Osagai batean egingo zenukeen bezala.
- **Exekuzio Programatikoa**: Zereginak instantziatu eta kodearen beste atal batzuetatik exekutatu daitezke, ez bakarrik terminaletik.

---

## Praktika Onak

- **Laguntza Mezuak**: Erabili `run` metodoa beharrezko argumentuak dauden egiaztatzeko eta erabilera-adibide bat bistaratzeko, falta badira.
- **Kolore Kodeketa**: Erabili `$this->getColors()->getColoredString()` erroreak gorriz edo arrakasta mezuak berdez nabarmentzeko, UX hobea lortzeko.
- **Izen-espazioa**: Ziurtatu zure zeregin pertsonalizatuak `Osumi\OsumiFramework\App\Task` izen-espazioaren barruan daudela.
