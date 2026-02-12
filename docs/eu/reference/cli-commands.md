# Osumi Framework CLI Komandoak

Osumi Framework-ek aplikazioen garapenarekin eta mantentzearekin lotutako hainbat eragiketa egiteko aukera ematen duten CLI ataza multzo bat dauka. Jarraian, eskuragarri dauden komandoen deskribapena dago:

## Eskuragarri dauden komandoak

### `add`

**Deskribapena:** Ekintza, zerbitzu, ataza, modelo osagai, osagai edo iragazki berriak sortzeko aukera ematen du.

**Erabilera:**

```bash
php of add [mota] [izena]
```

- **mota:** Sortuko den elementu mota (`action`, `service`, `task`, `modelComponent`, `component`, `filter`).
- **izena:** Sortuko den elementuaren izena.

**Adibidea:**

```bash
php of add --option action --name MyAction
```

---

### `backupAll`

**Deskribapena:** Aplikazioaren babeskopia fitxategi oso bat sortzen du, datu-basea eta kodea barne.

**Erabilera:**

```bash
php of backupAll
```

**Oharrak:** Komando honek barne-deiak egiten ditu `backupDB` eta `extractor` atazei.

---

### `backupDB`

**Deskribapena:** Datu-basearen babeskopia bat sortzen du `mysqldump` tresna erabiliz.

**Erabilera:**

```bash
php of backupDB [aukerak]
```

- **aukerak:**
- `silent`: Sartuta badago, komandoak ez ditu mezuak bistaratuko kontsolan.

**Adibidea:**

```bash
php of backupDB silent
```

---

### `extractor`

**Deskribapena:** Aplikazio osoa PHP fitxategi auto-ateragarri bakar batera esportatzen du.

**Erabilera:**

```bash
php of extractor
```

**Oharrak:** Aplikazio osoa PHP fitxategi auto-ateragarri bakar batera esportatzen du.

---

### `generateModel`

**Deskribapena:** Erabiltzaileak definitutako ereduetan oinarritutako datu-baseko taula guztiak sortzeko SQL fitxategi bat sortzen du.

**Erabilera:**

```bash
php of generateModel
```

**Oharrak:** SQL fitxategia esportazio direktorioan sortzen da.

---

### `generateModelFrom`

**Deskribapena:** Emandako JSON fitxategi batetik sortzen ditu modelo guztiak.

**Erabilera:**

```bash
php of generateModelFrom [fitxategia]
```

- **fitxategia:** Modeloen definizioak dituen JSON fitxategirako bidea.

**Adibidea:**

```bash
php of generateModelFrom models.json
```

---

### `generateModelFromDB`

**Deskribapena:** Dagoeneko definitutako datu-baseko konexio batetik sortzen ditu modelo guztiak.

**Erabilera:**

```bash
php of generateModelFromDB
```

**Oharrak:** Konfiguratutako datu-basera konektatzen da eta dagokien modeloak sortzen ditu.

---

### `reset`

**Deskribapena:** Framework-ekoak ez diren datu guztiak garbitzen ditu, instalazio berrietarako erabilgarria.

**Erabilera:**

```bash
php of reset
```

**Oharrak:** Erabiltzaileak sortutako karpetak eta fitxategiak ezabatzen ditu eta konfigurazio eta egitura lehenetsiak leheneratzen ditu.

---

### `version`

**Deskribapena:** Framework-aren uneko bertsioari buruzko informazioa erakusten du.

**Erabilera:**

```bash
php of version
```

**Oharrak:** Biltegi ofizialerako eta proiektuaren X (lehen Twitter) konturako estekak barne hartzen ditu.

---

## Ohar gehigarriak

- Komando guztiak proiektuaren errotik exekutatu behar dira.
- Ziurtatu beharrezko konfigurazioak `Config.json` fitxategian definituta daudela datu-baseari edo esportazioei lotutako komandoak exekutatu aurretik.
