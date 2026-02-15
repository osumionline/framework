# Konfigurazio Erreferentzia

Dokumentu honek esparruan eskuragarri dauden konfigurazio aukera guztien zerrenda osoa eskaintzen du. Aukera bakoitza bere balio lehenetsiarekin eta azalpen labur batekin deskribatzen da.

| Aukeraren Izena      | Balio Lehenetsia     | Deskribapena                                                                         |
| -------------------- | -------------------- | ------------------------------------------------------------------------------------ |
| `name`               | `Osumi`              | Aplikazioaren izena.                                                                 |
| `environment`        | (kate hutsa)         | Ingurunearen izena (adibidez, `development`, `production`).                          |
| `log.level`          | `DEBUG`              | Erregistro maila (adibidez, `DEBUG`, `INFO`, `WARN`, `ERROR`).                       |
| `log.max_file_size`  | `50`                 | Erregistro fitxategien gehienezko tamaina (MB-tan).                                  |
| `log.max_num_files`  | `3`                  | Gorde beharreko erregistro fitxategi kopuru maximoa.                                 |
| `use_session`        | `false`              | Saioak erabili ala ez.                                                               |
| `allow_cross_origin` | `true`               | Jatorri Anitzeko Baliabideen Partekatzea (CORS) baimendu ala ez.                     |
| `db.driver`          | `mysql`              | Datu-basearen kontrolatzailea (adibidez, `mysql`, `pgsql`).                          |
| `db.user`            | (kate hutsa)         | Datu-basearen erabiltzaile-izena.                                                    |
| `db.pass`            | (kate hutsa)         | Datu-basearen pasahitza.                                                             |
| `db.host`            | (kate hutsa)         | Datu-basearen ostalaria.                                                             |
| `db.name`            | (kate hutsa)         | Datu-basearen izena.                                                                 |
| `db.charset`         | `utf8mb4`            | Datu-basearen karaktere-multzoa.                                                     |
| `db.collate`         | `utf8mb4_unicode_ci` | Datu-basearen ordenazioa.                                                            |
| `urls.base`          | (kate hutsa)         | Aplikazioaren oinarrizko URLa.                                                       |
| `cookie_prefix`      | (kate hutsa)         | Cookien aurrizkia.                                                                   |
| `cookie_url`         | (kate hutsa)         | Cookien URLa.                                                                        |
| `error_pages.403`    | `null`               | 403 errore orriaren URLa.                                                            |
| `error_pages.404`    | `null`               | 404 errore orriaren URLa.                                                            |
| `error_pages.500`    | `null`               | 500 errore orriaren URLa.                                                            |
| `default_title`      | (kate hutsa)         | Aplikazioaren izenburu lehenetsia.                                                   |
| `admin_email`        | (kate hutsa)         | Administratzailearen helbide elektronikoa.                                           |
| `mailing_from`       | (kate hutsa)         | Mezu elektronikoak bidaltzeko erabilitako helbide elektronikoa.                      |
| `lang`               | `es`                 | Aplikazioaren hizkuntza lehenetsia.                                                  |
| `css_list`           | `[]`                 | Sartu beharreko CSS fitxategien zerrenda.                                            |
| `js_list`            | `[]`                 | Sartu beharreko JavaScript fitxategien zerrenda.                                     |
| `head_elements`      | `[]`                 | HTML elementuen zerrenda dokumentuaren <head>-ean injektatzeko (meta, link, script). |
| `libs`               | `[]`                 | Sartu beharreko liburutegien zerrenda.                                               |
| `extras`             | `[]`                 | Konfigurazio aukera gehigarriak.                                                     |
