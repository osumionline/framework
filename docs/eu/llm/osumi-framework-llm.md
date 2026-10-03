# Osumi Framework – LLM Testuingurua (Guztia batean)

**Helburua**

Dokumentu honek **Osumi Framework 9.10** azaltzen edo harentzat kodea sortzen duten IA sistemetarako testuinguru trinko baina autoritarioa eskaintzen du.

Framework-aren portaera bat hemen dokumentatuta ez badago, ez asmatu.

## 0. Framework-aren identitatea

- **Izena:** Osumi Framework
- **Bertsioa:** 9.10.0
- **Hizkuntza:** PHP
- **Gutxieneko PHP bertsioa:** 8.5+
- **Tipatzea:** erabili `declare(strict_types=1);`

## 1. Filosofia

Framework-ak kode esplizitua, bizi-ziklo aurreikusgarria, arduren bereizketa, osagai txikiak, zerbitzu berrerabilgarriak, DTO tipatuak eta Middleware fase esplizituak lehenesten ditu.

## 2. Eskaeraren bizi-zikloa

Erantzun tradizionala:

```text
Eskaera → Routing → before → Osagaia → afterRender → Layout → afterResponse → HTTP
```

Streaming erantzuna:

```text
Eskaera → Routing → before → Osagaia/OStreamResponse → afterRender → afterResponse → DB konexioak itxi → Stream emisioa
```

Streaming erantzunek ez dute layout-ik erabiltzen eta ez dute byterik bidaltzen `afterRender` eta `afterResponse` amaitu aurretik.

## 3. Routing (`ORoute`)

Metodoak: `get()`, `post()`, `put()`, `delete()`, `view()`. Taldekatzea: `prefix()`, `layout()`, `group()`.

## 4. Middlewareak (`OMiddleware`)

Faseak: `PHASE_BEFORE`, `PHASE_AFTER_RENDER`, `PHASE_AFTER_RESPONSE`.

Emaitza-gakoak: `context`, `body`, `headers`, `status_code`, `stop`, `message`.

Streaming erantzunetan `body` itzultzea baliogabea da. Faseko datuek `$data['is_streaming_response']` eskaintzen dute.

## 5. `ORequest` eta DTOak

`ORequest`-ek parametro, goiburu, fitxategi eta Middleware testuingurua modu tipatuan eskaintzen du. DTOek `ODTO` hedatzen dute eta `#[ODTOField]` erabiltzen dute.

## 6. Osagaiak (`OComponent`)

`run()`-ek 0 parametro edo `ORequest`/`ODTO` parametro bakar ez-nullable bat izan dezake.

Txantiloi-erantzunek `void` erabil dezakete; streaming erantzunek `OStreamResponse` itzul dezakete. Txantiloirik gabeko osagai batek `OStreamResponse` esplizituki deklaratu behar du.

## 7. `OStreamResponse`

Stream irakurgarria, HTTP goiburuak, egoera-kodea, blokearen tamaina eta stream-aren jabetza biltzen ditu. Lehenetsitako blokearen tamaina 1 MiB da eta framework-ak stream-a automatikoki ixten du lehenespenez.

## 8. Txantiloiak, layout-ak eta zerbitzuak

Txantiloiak: `.php`, `.html`, `.json`, `.xml`. Pipeak: `date`, `number`, `string`, `plain`, `bool`.

Layout-ak ez dira `OStreamResponse` erantzunetan aplikatzen.

`OService` negozio/domeinu logika berrerabilgarrirako erabiltzen da.

## 9. ORM

Modeloek `OModel` hedatzen dute eta `#[OPK]`, `#[OField]`, `#[OCreatedAt]`, `#[OUpdatedAt]` bezalako atributuak erabiltzen dituzte.

## 10. CLI eta migrazioak

Aplikazio CLIa: `php of <zeregina>`.

Migrazio CLIa: `php vendor/bin/ofw-migrate --help`.

Egoera `ofw/tmp/state.json` fitxategian gordetzen da.

## 11. Filter legacy-ak

Filterrak 9.9 aurreko kontzeptuak dira eta ez dira egungo runtime APIaren parte. Kode berriak Middlewareak erabili behar ditu.

## 12. Konbentzioak

Erabili `declare(strict_types=1);`, mota esplizituak, propietate tipatuak, PHPDoc osoa metodoetan, `::class`, `OMiddleware::PHASE_*` eta `null` kudeaketa esplizitua.
