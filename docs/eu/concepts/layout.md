# Layout-ak

**Osumi Framework**-en, layout bat ibilbideko osagai nagusiaren irteera inguratzen duen osagai berezi bat da.

`OStreamResponse` erantzunak salbuespena dira: ez dute inguratu daitekeen gorputz materializaturik eta **ez dute layout-ik erabiltzen**.

---

## 1. Errendatze-fluxua

Erantzun tradizionala:

```text
Bideratzea
↓
before Middlewareak
↓
Osagaia
↓
afterRender Middlewareak
↓
Layout-a
↓
afterResponse Middlewareak
↓
HTTP erantzuna
```

Streaming erantzuna:

```text
Bideratzea
↓
before Middlewareak
↓
Osagaia → OStreamResponse
↓
afterRender Middlewareak
↓
afterResponse Middlewareak
↓
Stream emisioa
```

Streaming erantzunetan layout-a ez da exekutatzen eta `afterResponse` lehen bytea bidali aurretik amaitzen da.

---

## 2. Layout lehenetsia

Proiektu berriek layout lehenetsi bat dute. `DefaultLayoutComponent`-ek normalean `title` eta `body` propietateak eskaintzen ditu.

---

## 3. Layout-ak bideratzean definitzea

`ORoute::layout()` eta `ORoute::group()` erabil daitezke layout-ak ibilbide-taldeei esleitzeko.

Ibilbide bat layout talde batean egon arren, bere osagaiak `OStreamResponse` itzultzen badu layout-a ez da exekutatzen.

Ikusi `/docs/eu/concepts/middlewares.md`.

---

## 4. CSS / JS injekzioa

Layout-ak konfiguratutako frontend baliabideak `</head>` duten dokumentuetan injektatzeko puntua dira.

Injekzio hori ez da streaming erantzunetan aplikatzen.

---

## 5. Praktika onak

- Mantendu layout-ak egitura eta aurkezpenean zentratuta.
- Ez sartu negozio-logikarik layout-etan.
- Ez erabili layout-a streaming deskarga batek behar dituen goiburuak definitzeko; erabili `OStreamResponse` edo Middlewareak.

---

## 6. Laburpena

- Layout-ek erantzun tradizionalak inguratzen dituzte.
- `afterRender` layout-a baino lehen exekutatzen da.
- `afterResponse` azken gorputz tradizionalaren ondoren exekutatzen da.
- `before` edo `afterRender` faseko `stop` batek layout-a saihesten du.
- `OStreamResponse` erantzunek layout-a beti saihesten dute.
