# File Uploads

File uploads in **Osumi Framework 9.10** follow the same application structure as other request handling:

- `ORequest` exposes uploaded files.
- DTOs can validate upload-related input.
- Components orchestrate the request.
- Services are appropriate for reusable file-processing logic.
- Middlewares can protect upload routes.

---

# 1. Accessing an Uploaded File

PHP stores uploaded file information in `$_FILES`.

Osumi Framework exposes it through:

```php
$file = $req->getFile(
	'photo'
);
```

The returned value follows the standard PHP upload structure.

Middleware context is available separately through `getMiddleware()` and `getMiddlewareValue()`.

---

# 2. Upload DTO

Example:

```php
<?php

declare(strict_types=1);

namespace Osumi\OsumiFramework\App\DTO;

use Osumi\OsumiFramework\DTO\ODTO;
use Osumi\OsumiFramework\Web\ORequest;

class PhotoUploadDTO extends ODTO {
	public ?array $photo = null;

	/**
	 * Build the upload DTO.
	 *
	 * @param ORequest $req Current request.
	 */
	public function __construct(ORequest $req) {
		parent::__construct(
			$req
		);

		$this->photo = $req->getFile(
			'photo'
		);
	}
}
```

Validate file presence, upload error, MIME type, extension and size before storing it.

---

# 3. Upload Component

```php
class UploadPhotoComponent extends OComponent {
	public string $status = 'ok';
	public string $message = '';
	public ?string $filename = null;

	/**
	 * Store the uploaded file.
	 *
	 * @param PhotoUploadDTO $dto Upload DTO.
	 *
	 * @return void
	 */
	public function run(PhotoUploadDTO $dto): void {
		$file = $dto->photo;

		if (
			!is_array($file) ||
			!array_key_exists('error', $file) ||
			$file['error'] !== UPLOAD_ERR_OK ||
			!isset(
				$file['tmp_name'],
				$file['name']
			)
		) {
			$this->status = 'error';
			$this->message = 'A valid file is required.';

			return;
		}

		$new_name = uniqid(
			'photo_',
			true
		)
			. '_'
			. basename(
				(string) $file['name']
			);

		$upload_dir = $this->getConfig()->getDir(
			'uploads'
		);

		$destination = $upload_dir
			. $new_name;

		if (
			!move_uploaded_file(
				(string) $file['tmp_name'],
				$destination
			)
		) {
			$this->status = 'error';
			$this->message = 'Failed to store file.';

			return;
		}

		$this->filename = $new_name;
		$this->message = 'File uploaded successfully.';
	}
}
```

---

# 4. Protecting the Upload Route

```php
ORoute::post(
	'/api/upload-photo',
	UploadPhotoComponent::class,
	[
		OMiddleware::PHASE_BEFORE => [
			LoginMiddleware::class
		]
	]
);
```

The upload component will not execute when authentication Middleware stops the request.

Authenticated context can be read with:

```php
$id_user = $req->getMiddlewareValue(
	'Login',
	'id'
);
```

or mapped directly into a DTO using `middleware` and `middlewareProperty`.

---

# 5. Validation

Common checks include:

- `UPLOAD_ERR_OK`
- Maximum size
- MIME type
- Extension
- File name normalization
- Destination permissions

Do not trust client-provided MIME metadata as the only validation source.

---

# 6. Storing Metadata

```php
$photo = new Photo();
$photo->filename = $new_name;
$photo->user_id = $id_user;
$photo->save();
```

For reusable storage logic, use a service.

---

# 7. Best Practices

- Keep upload directories outside public access unless files must be public.
- Generate unique file names.
- Validate size, MIME type and extension.
- Use services when file-processing logic grows.
- Use Middlewares when uploads require authentication or authorization.
- Use Middleware context instead of client-provided user IDs.

---

# 8. Summary

A canonical upload flow is:

1. Protect the route with Middleware if required.
2. Read the file with `ORequest::getFile()`.
3. Validate the upload.
4. Store the file safely.
5. Persist metadata if needed.
6. Use trusted Middleware context for authenticated ownership.
