# Middlewareak

**Osumi Framework**-eko Middlewareak HTTP eskaera baten bizi-zikloan parte hartzen duten klase berrerabilgarriak dira.

Hiru fasetan exekuta daitezke:

- `before`: ibilbideko osagaia exekutatu aurretik.
- `afterRender`: osagaia errendatu ondoren eta erantzun tradizionaletan layout-a aplikatu aurretik.
- `afterResponse`: azken erantzuna prestatu ondoren eta bidali aurretik.

## 1. Middleware baten egitura

```php
final class ExampleMiddleware {
	/**
	 * Handle a middleware execution phase.
	 *
	 * @param string $phase Current middleware phase.
	 * @param array<string, mixed> $data Current middleware pipeline data.
	 *
	 * @return array<string, mixed> Middleware result.
	 */
	public static function handle(
		string $phase,
		array $data
	): array {
		return [];
	}
}
```

Aplikazioko Middlewareak normalean `src/Middleware/` direktorioan gordetzen dira.

## 2. Faseak

```php
OMiddleware::PHASE_BEFORE
OMiddleware::PHASE_AFTER_RENDER
OMiddleware::PHASE_AFTER_RESPONSE
```

Ohiko fluxua:

```text
Bideratzea
↓
before
↓
Osagaia
↓
afterRender
↓
Layout-a
↓
afterResponse
↓
HTTP erantzuna
```

Streaming erantzunetan layout-a ez da exekutatzen eta `afterResponse` stream-eko byteak bidali aurretik amaitzen da.

## 3. Middleware emaitza

`handle()` metodoak beti array bat itzuli behar du. Gako hauek onartzen dira:

- `context`
- `body`
- `headers`
- `status_code`
- `stop`
- `message`

`body` `afterRender` edo `afterResponse` faseetan erabiltzen da, baina ez streaming erantzunetan.

Streaming erantzun batean `body` itzultzeak `InvalidArgumentException` sortzen du.

## 4. Faseko egoera metatua

`$data` array-ak, besteak beste, honako hauek ditu:

```php
$data['context']
$data['component_body']
$data['final_body']
$data['response_headers']
$data['status_code']
$data['is_streaming_response']
$data['is_error']
$data['error_phase']
$data['error_status_code']
$data['error_message']
```

## 5. Streaming erantzunak

Osagaiak `OStreamResponse` itzultzen duenean:

```php
$data['is_streaming_response'] === true
```

`afterRender` eta `afterResponse` faseetan.

Middlewareek honako hauek alda ditzakete:

- `context`
- `headers`
- `status_code`
- `stop`
- `message`

Ezin dute `body` itzuli.

Framework-ak ez du stream-eko byterik bidaltzen bi faseak amaitu arte. Middleware batek `stop => true` itzultzen badu, stream-a baztertzen da, erantzun normalaren goiburuak berrezartzen dira eta ohiko errore-erantzuna sortzen da.

## 6. Middleware globalak

`src/Middleware/Middlewares.php` fitxategian konfiguratzen dira.

## 7. Ibilbide eta taldeetako Middlewareak

Ibilbideek eta `prefix()`, `layout()` eta `group()` metodoek Middleware definizioak onartzen dituzte.

Fase bakoitzean ordena hau da:

```text
globala
↓
kanpoko taldea
↓
barneko taldea
↓
ibilbidea
```

## 8. ORequest eta DTO testuingurua

`ORequest`-ek `getMiddleware()` eta `getMiddlewareValue()` eskaintzen ditu.

DTOek Middleware testuingurua iturburu esplizitu gisa erabil dezakete `middleware` eta `middlewareProperty` bidez.

## 9. Errore-egoera `afterResponse` fasean

Aurreko `stop` baten ondoren:

```php
$data['is_error']
$data['error_phase']
$data['error_status_code']
$data['error_message']
```

## 10. Praktika onak

- Erabili `before` autentifikazio, baimen eta testuingururako.
- Erabili `afterRender` erantzun tradizionaletan layout-aren aurreko aldaketetarako.
- Erabili `afterResponse` azken aldaketa, auditoria eta logging-erako.
- Streaming erantzunetan aldatu goiburuak edo egoera, ez `body`.
- Mantendu Middlewareak txiki eta fokatuak.
