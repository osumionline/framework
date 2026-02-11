# File Uploads

Handling file uploads in **Osumi Framework** follows the same design principles as the rest of the system:

- **DTOs** handle and validate incoming data
- **Components** orchestrate the operation
- The uploaded file is available through **`ORequest`**
- You decide where and how files should be stored

This recipe shows how to accept an uploaded file (e.g. from an HTML `<input type="file" name="photo">`), process it in a component, and store it properly.

---

# 1. Overview of How Uploads Work

When a user submits a form with a file, PHP places the uploaded file information inside `$_FILES`.

Osumi Framework merges request data (params, headers, filters, files) into an **`ORequest`** instance.\
From inside your component’s `run()` method, you can access:

```php
$req->getFile("photo");
```

This returns the standard PHP upload array:

```php
[
  'name'     => 'example.png',
  'type'     => 'image/png',
  'tmp_name' => '/tmp/phpXYZ123',
  'error'    => 0,
  'size'     => 123456
]
```

To preserve consistency with the rest of the framework, you usually wrap this access inside a **DTO**, so validation and structure stay clean.

---

# 2. Creating a DTO for File Uploads

DTOs can accept any request parameter, including files.
Since uploaded files do not come from headers or filters, you read them directly from `ORequest` inside the DTO constructor.

Example DTO:

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\DTO;

use Osumi\OsumiFramework\DTO\ODTO;
use Osumi\OsumiFramework\DTO\ODTOField;
use Osumi\OsumiFramework\Web\ORequest;

class PhotoUploadDTO extends ODTO {
  #[ODTOField(required: true)]
  public ?array $photo = null;

  public function __construct(ORequest $req) {
    parent::__construct($req);

    // Load the uploaded file
    $this->photo = $req->getFile('photo');

    // Validate file presence
    if ($this->photo === null || $this->photo['error'] !== 0) {
      $this->validation_errors[] = "A valid file is required.";
    }
  }
}
```

### Notes:

- `required: true` ensures the DTO will be invalid if the file is missing
- `getFile('photo')` retrieves the upload
- You can extend validation (file size, mime type, etc.)

---

# 3. Creating the Upload Component

The component receives the DTO and processes the uploaded file.

```php
<?php declare(strict_types=1);

namespace Osumi\OsumiFramework\App\Module\Api\UploadPhoto;

use Osumi\OsumiFramework\Core\OComponent;
use Osumi\OsumiFramework\App\DTO\PhotoUploadDTO;

class UploadPhotoComponent extends OComponent {
  public string $status = 'ok';
  public string $message = '';
  public ?string $filename = null;

  public function run(PhotoUploadDTO $dto): void {
    if (!$dto->isValid()) {
      $this->status = 'error';
      $this->message = implode(", ", $dto->getValidationErrors());
      return;
    }

    // Access uploaded file data
    $file = $dto->photo;

    // Generate a destination file path
    $new_name = uniqid("photo_") . "_" . basename($file['name']);
    $upload_dir = $this->getConfig()->getDir('uploads');
    $dest = $upload_dir . $new_name;

    // Move the uploaded file
    if (!move_uploaded_file($file['tmp_name'], $dest)) {
      $this->status = 'error';
      $this->message = 'Failed to store file.';
      return;
    }

    $this->filename = $new_name;
    $this->message = 'File uploaded successfully.';
  }
}
```

### Key points:

- `run()` receives a `PhotoUploadDTO`
- The DTO ensures validity
- `move_uploaded_file()` stores the file safely
- You should store uploads in a configured directory (`uploads` or similar)
- Components remain clean and readable

---

# 4. HTML Form Example

When consuming this endpoint from a web page:

### Important:

`enctype="multipart/form-data"` is required for PHP to populate `$_FILES`.

---

# 5. Defining the Route

Add a route that receives a POST request and maps to your upload component:

```php
use Osumi\OsumiFramework\Routing\ORoute;
use Osumi\OsumiFramework\App\Module\Api\UploadPhoto\UploadPhotoComponent;

ORoute::post('/api/upload-photo', UploadPhotoComponent::class);
```

If the upload requires authentication, simply add your filter:

```php
ORoute::post('/api/upload-photo', UploadPhotoComponent::class, [LoginFilter::class]);
```

---

# 6. Example JSON Response Template

If your component uses a `.json` template, the output might be:

```json
{
	"status": "{{ status }}",
	"message": "{{ message }}",
	"filename": "{{ filename }}"
}
```

---

# 7. Extending Validation

Common validation patterns include:

### Allowed extensions:

```php
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, ['png','jpg','jpeg'])) {
  $this->validation_errors[] = "Invalid file extension.";
}
```

### Max file size:

```php
if ($file['size'] > 2 * 1024 * 1024) { // 2MB
  $this->validation_errors[] = "File is too large.";
}
```

### Valid MIME types:

```php
$allowed = ['image/png','image/jpeg'];
if (!in_array($file['type'], $allowed)) {
  $this->validation_errors[] = "Invalid file type.";
}
```

You can store these rules inside a dedicated service to keep DTOs clean.

---

# 8. Storing File Metadata in Models

If you want to store upload info in a database:

```php
$photo = new Photo();
$photo->filename = $new_name;
$photo->user_id = $userId; // if using LoginFilter
$photo->save();
```

This is usually done inside a service rather than directly in a component.

---

# 9. Best Practices

- **Use DTOs** to validate uploaded files
- **Use services** for file‑related logic if it grows (resizing images, generating thumbnails, etc.)
- **Never trust client‑provided metadata** (always inspect MIME type, extension, size)
- **Keep upload directories outside of public access** unless files need to be exposed
- **Name files uniquely** to avoid overwriting user files
- **Use filters** if only authenticated users may upload files

---

# 10. Summary

To implement uploads in Osumi Framework:

1.  Create a **DTO** to receive and validate the file
2.  Create a **component** to process and store it
3.  Add a **route** pointing to the component
4.  Use `ORequest->getFile(name)` to access uploaded files
5.  Store them using `move_uploaded_file()`
6.  Add authentication filters if necessary

This approach keeps your upload logic consistent with the framework’s design: clean, modular, and predictable.
