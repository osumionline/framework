# Components

Components in Osumi Framework are small, reusable pieces of code. They normally render a template, although since **Osumi Framework 9.10** they can also produce a streamed HTTP response directly through `OStreamResponse`.

A traditional component consists of:

- A PHP class extending `OComponent`.
- A template file (`php`, `html`, `json` or `xml`, depending on usage).

A stream-only component may omit its template when its `run()` method explicitly declares `OStreamResponse` as its return type.

---

## Basic Component Structure

### Component Class

```php
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Email\LostPassword;

use Osumi\OsumiFramework\Core\OComponent;

class LostPasswordComponent extends OComponent {
	public ?string $token = null;
}
```

### Template File

```html
<div>
	Token: {{ token }}
</div>
```

---

## Advanced Features

### Automatic Content-Type Headers

When a template-based component is used as the main action for a URL, the framework prepares the appropriate `Content-Type` from the template extension:

- `.json`: `application/json`.
- `.xml`: `text/xml`.
- `.html` / `.php`: `text/html`.

`OStreamResponse` instances define their own HTTP headers.

### Component Nesting

Traditional components can be nested to promote reusability.

```php
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Component\Father;

use Osumi\OsumiFramework\App\Component\Child\ChildComponent;
use Osumi\OsumiFramework\Core\OComponent;

class FatherComponent extends OComponent {
	public ?ChildComponent $child = null;

	/**
	 * Prepare the nested component.
	 *
	 * @return void
	 */
	public function run(): void {
		$this->child = new ChildComponent();
		$this->child->name = 'Child Name';
	}
}
```

An `OStreamResponse` cannot be rendered as a nested component or converted to a string.

### Template Syntax and Access

1. **PHP templates (`.php`)** can execute PHP and access public properties as local variables.
2. **Static/structured templates (`.html`, `.json`, `.xml`)** use `{{ variable_name }}`.

---

## The `run()` Method

A component can define an optional `run()` method.

When the component is used as a route action, these parameter signatures are supported:

```php
public function run(): void
```

```php
public function run(ORequest $req): void
```

```php
public function run(MyDTO $dto): void
```

Behavior:

- `run()` receives no request data.
- `run(ORequest $req)` receives the current request.
- `run(MyDTO $dto)` receives a DTO populated from the current request. `MyDTO` must extend `ODTO`.
- DTOs are recognized through inheritance from `ODTO`, not through their namespace.
- More than one parameter is not supported.
- The parameter, when present, must be non-nullable.

The method may prepare properties for a template or return an `OStreamResponse`.

### Middleware Context

`ORequest` exposes context published by executed Middlewares.

```php
public function run(ORequest $req): void {
	$login = $req->getMiddleware(
		'Login'
	);
}
```

```php
public function run(ORequest $req): void {
	$id = $req->getMiddlewareValue(
		'Login',
		'id'
	);
}
```

`LoginMiddleware` is exposed through the public name `Login`.

A missing Middleware context returns an empty array and a missing context value returns `null`.

See `/docs/en/concepts/middlewares.md`.

---

## Streamed Responses with `OStreamResponse`

A streamed response allows large files or progressively generated content to be sent without materializing the complete body in memory.

```php
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Download;

use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\Web\OStreamResponse;

class DownloadComponent extends OComponent {
	/**
	 * Stream a file to the client.
	 *
	 * @return OStreamResponse Streamed HTTP response.
	 */
	public function run(): OStreamResponse {
		$file = '/path/to/file.zip';
		$stream = fopen(
			$file,
			'rb'
		);

		if ($stream === false) {
			throw new \RuntimeException(
				'Could not open file.'
			);
		}

		$size = filesize(
			$file
		);

		if ($size === false) {
			fclose(
				$stream
			);

			throw new \RuntimeException(
				'Could not determine file size.'
			);
		}

		return new OStreamResponse(
			$stream,
			[
				'Content-Type' => 'application/zip',
				'Content-Length' => strval($size),
				'Content-Disposition' => 'attachment; filename="file.zip"'
			]
		);
	}
}
```

The template can be omitted because `run()` explicitly declares `OStreamResponse`.

The normal route parameters are also supported:

```php
public function run(ORequest $req): OStreamResponse
```

```php
public function run(MyDTO $dto): OStreamResponse
```

The `OStreamResponse` constructor receives:

1. a readable stream;
2. HTTP headers;
3. HTTP status code, default `200`;
4. chunk size, default 1 MiB;
5. whether the framework should close the stream, default `true`.

### Streaming lifecycle

```text
before Middlewares
↓
Component
↓
OStreamResponse
↓
afterRender Middlewares
↓
afterResponse Middlewares
↓
Database connections closed
↓
HTTP headers
↓
Chunked stream emission
```

Rules:

- No layout is applied.
- No stream bytes are sent until both `afterRender` and `afterResponse` finish.
- Middlewares can change headers and HTTP status.
- Middlewares cannot replace `body` while the response is streaming.
- `$data['is_streaming_response']` is `true` during those phases.
- If a Middleware stops before emission, the stream is discarded and the normal typed error response is produced.
- Framework-owned streams are closed after completion, when discarded, or if the response is unexpectedly destroyed.
- If reading fails after emission has started, the HTTP response can no longer be safely replaced with another body.

---

## Accessing Global Options

Components can access framework services such as:

- `getConfig()`: global `OConfig`.
- `getLog()`: component `OLog`.
- `getSession()`: `OSession`.

---

## Rendering Components

Template-based components can be converted to strings:

```php
$cmp = new BooksComponent();
echo strval($cmp);
```

A component returning `OStreamResponse` must be handled by the framework HTTP pipeline and cannot be converted to a string.

---

# Template Pipes

Osumi Framework templates support Angular-style pipes.

### Syntax

```text
{{ value | pipeName }}
{{ value | pipeName:param }}
{{ value | pipeName:param1:param2 }}
```

Pipes are processed by `OPipeFunctions`.

## `date`

Formats date values. Default format: `d/m/Y H:i:s`.

## `number`

Uses `number_format()`.

## `string`

Applies `urlencode()` and returns a quoted string.

## `plain`

Encodes a string as a JSON-safe quoted value without URL encoding. Unicode is preserved and JSON-sensitive characters are escaped correctly.

## `bool`

Produces `true`, `false` or `null`.

---

## Best Practices

- Keep templates simple.
- Use `run()` to prepare data.
- Use typed public properties.
- Prefer nullable defaults such as `?type = null` where appropriate.
- Use `OStreamResponse` for large or progressive bodies that should not be fully loaded into memory.
- Explicitly declare `OStreamResponse` as the return type when the component has no template.
