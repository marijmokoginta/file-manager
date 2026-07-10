# File Manager for Laravel

[![PHP](https://img.shields.io/badge/PHP-^8.2-blue)](composer.json)
[![Laravel](https://img.shields.io/badge/Laravel-10_|_11_|_12_|_13-red)](composer.json)
[![Tests](https://img.shields.io/badge/tests-82_passing-brightgreen)](.)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

A modular, clean-architecture Laravel package for file management — upload, process, store, and serve files across multiple drivers and file types.

---

## Requirements

- PHP 8.2 or higher
- Laravel 10, 11, 12, or 13
- Imagick PHP extension (for image processing features)
- `intervention/image` v3

---

## Installation

```bash
composer require m2code/file-manager
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=config --provider="M2code\FileManager\FileManagerServiceProvider"
```

The package auto-discovers via Laravel's package discovery. No manual provider registration is needed.

---

## Quick Start

### Save a file programmatically

```php
use M2code\FileManager\Facades\FileManager;

// From an uploaded file
$result = FileManager::save($request->file('document'), 'uploads');
echo $result->filePath; // "uploads/abc123.pdf"

// From a base64 data URI
$result = FileManager::save('data:image/png;base64,...', 'uploads');

// From a raw SVG string
$result = FileManager::save('<svg>...</svg>', 'uploads');
```

### Upload and process an image

```php
use M2code\FileManager\Application\Uploader\ImageUploader;

$result = ImageUploader::make()
    ->blur()                    // generate BlurHash
    ->optimize('avif')          // AVIF with WebP fallback
    ->watermark()               // apply watermark
    ->upload($request->file('photo'), 'uploads/images');

// Access variants
$result->variants->get('original')?->path;
$result->variants->get('optimized')?->path;
$result->variants->get('watermark')?->path;
$result->blurhash;              // BlurHash string
```

You can also pass options as an array instead of fluent methods:

```php
$result = ImageUploader::make()
    ->withOptions([
        'blurhash'  => true,
        'optimize'  => false,
        'watermark' => true,
    ])
    ->upload($file, 'uploads');
```

### Delete files

```php
use M2code\FileManager\Facades\FileManager;

FileManager::delete('uploads/documents/report.pdf');
FileManager::deleteMany(['uploads/a.jpg', 'uploads/b.jpg']);
FileManager::deleteVariants($result->variants);  // delete all variants of an upload
```

### Generate URLs

```php
use M2code\FileManager\Facades\FileUrl;

$url = FileUrl::getUrl('uploads/images/photo.png');
$signed = FileUrl::getSignedUrl('uploads/images/photo.png', now()->addMinutes(5));
```

---

## Core Concepts

### Facades

| Facade | Container Binding | Primary Methods |
|--------|------------------|----------------|
| `FileManager` | `file-manager` | `save()`, `delete()`, `deleteMany()`, `deleteVariants()` |
| `FileUrl` | `file-url` | `getUrl()`, `getSignedUrl()` |
| `FileMover` | `file-mover` | `move()`, `moveAll()` |

### File Type Handling

The package auto-detects file types and routes them to the appropriate handler:

| MIME Type | Handler | Supported Processing |
|-----------|---------|---------------------|
| `image/*` (except SVG) | `ImageFileHandler` | BlurHash, optimize, watermark, low quality |
| `image/svg+xml` | `SvgFileHandler` | None (saved as-is) |
| `application/pdf` | `PdfFileHandler` | None (saved as-is) |

Extend with custom handlers by implementing `FileTypeHandler` and registering via `FileManagerServiceProvider`.

### Drivers

The package uses a multi-driver architecture. Each concern — saving, deleting, URL generation — has its own swappable driver:

| Driver Slot | Default | Interface |
|-------------|---------|-----------|
| Storage | `LocalFileSaver` | `FileSaver` |
| Deletion | `LocalFileDeleter` | `FileDeleter` |
| URL generation | `LocalFileUrlGenerator` | `FileUrlGenerator` |
| File moving | `LocalFileMover` | `FileMover` |

Configure the active driver in `config/file-manager.php`:

```php
'default_driver'         => env('FILE_MANAGER_DRIVER', 'local'),
'default_deleter'        => env('FILE_MANAGER_DELETER', env('FILE_MANAGER_DRIVER', 'local')),
'default_url_generator'  => env('FILE_MANAGER_DRIVER', 'local'),
```

All drivers use the same Laravel filesystem disk by default:

```env
FILE_MANAGER_DISK=public
```

---

## HTTP Upload API

The package registers a `POST /upload` endpoint for receiving files over HTTP. Files are stored in a temporary location; use `FileMover` to promote them to permanent storage.

### Authentication

The endpoint is protected by bearer token authentication. Configure the token in your `.env`:

```env
FILE_MANAGER_API_TOKEN=your-secret-token
```

Multiple tokens (comma-separated) are supported for zero-downtime key rotation:

```env
FILE_MANAGER_API_TOKEN=v1-token,v2-token
```

Requests without a valid token receive a `401 Unauthorized` response. If no token is configured, the endpoint is open.

### POST /upload

**Multipart request:**

```
POST /upload
Authorization: Bearer your-secret-token
Content-Type: multipart/form-data

file: (binary)
options[blurhash]: false
options[watermark]: true
cancel_token: optional-tracking-id
```

**JSON request (base64):**

```json
POST /upload
Authorization: Bearer your-secret-token
Content-Type: application/json

{
    "file": "data:image/png;base64,iVBORw0KGgo...",
    "options": {
        "blurhash": false,
        "watermark": true
    },
    "cancel_token": "optional-tracking-id"
}
```

**Response:**

```json
{
    "type": "image",
    "tmp_path": "tmp/uploads/550e8400-e29b-41d4-a716-446655440000/original.png",
    "tmp_folder": "tmp/uploads/550e8400-e29b-41d4-a716-446655440000",
    "original_name": "photo.png",
    "size": 204800,
    "mime_type": "image/png",
    "extra": {
        "blurhash": "L6Df8^_4D%M{%MD%M{D%",
        "optimized_path": "tmp/uploads/550e8400-e29b-41d4-a716-446655440000/optimized.avif"
    }
}
```

### Options per file type

| File Type | Option | Type | Default | Description |
|-----------|--------|------|---------|-------------|
| image | `blurhash` | `bool` | `true` | Generate BlurHash string |
| image | `optimize` | `bool` | `true` | Generate AVIF/WebP optimized variant |
| image | `watermark` | `bool` | `false` | Apply watermark overlay |
| image | `low_quality` | `bool` | `false` | Generate low quality placeholder |
| svg | — | — | — | No options supported |
| document | — | — | — | No options supported |

Options not recognized by the target handler are silently ignored. Default values are defined in `config/file-manager.php` under `upload.default_options`.

### File size validation

Maximum file sizes are configured per type category in KiB. Each limit can be overridden via environment variable:

```env
FILE_MANAGER_MAX_SIZE_DEFAULT=10240
FILE_MANAGER_MAX_SIZE_IMAGE=10240
FILE_MANAGER_MAX_SIZE_DOCUMENT=20480
```

Files exceeding the limit receive a `422 Unprocessable Entity` response with a descriptive message.

### Cancel token

Uploads can be cancelled mid-flight using a cancel token. Include a `cancel_token` in the upload request body, then call the cancel endpoint:

```
POST /upload/cancel
Authorization: Bearer your-secret-token

{
    "cancel_token": "my-upload-uuid"
}
```

The token can also be sent via the `X-Cancel-Token` header. Subsequent upload requests using a cancelled token will receive a `499 Client Closed Request` response. Tokens expire after 10 minutes.

---

## FileMover

Files uploaded via the HTTP API reside in a temporary disk. Promote them to permanent storage using the `FileMover` facade:

```php
use M2code\FileManager\Facades\FileMover;

// Move a single file
$permanentPath = FileMover::move(
    tmpPath: 'tmp/uploads/550e8400/photo.png',
    destinationFolder: 'avatars',
);
// Returns: "avatars/photo.png"

// Move all files in a tmp folder
$results = FileMover::moveAll(
    tmpFolder: 'tmp/uploads/550e8400',
    destinationFolder: 'avatars',
);
// Returns: ['tmp/uploads/550e8400/original.png' => 'avatars/original.png', ...]
```

Source files are deleted from the tmp disk after a successful move. If the source file is not found, a `RuntimeException` is thrown.

---

## Scheduled Cleanup

Temporary files that are not moved to permanent storage are automatically cleaned up. The `file-manager:clean-tmp` command is registered to run **daily** via Laravel's scheduler.

Enable the scheduler with the standard Laravel cron entry:

```cron
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

For local development:

```bash
php artisan schedule:work
```

To run cleanup manually:

```bash
php artisan file-manager:clean-tmp
```

Configure tmp lifetime and location:

```env
FILE_MANAGER_TMP_LIFETIME=86400      # seconds (default: 24 hours)
FILE_MANAGER_TMP_DISK=local
FILE_MANAGER_TMP_PREFIX=tmp/uploads
```

---

## Configuration Reference

The complete configuration file after publishing to `config/file-manager.php`:

```php
return [
    // --- Storage Drivers ---
    'default_driver'  => env('FILE_MANAGER_DRIVER', 'local'),
    'drivers' => [
        'local' => [
            'class' => M2code\FileManager\Drivers\Local\LocalFileSaver::class,
            'disk'  => env('FILE_MANAGER_DISK', 'public'),
        ],
    ],
    'default_deleter' => env('FILE_MANAGER_DELETER', env('FILE_MANAGER_DRIVER', 'local')),
    'deleters' => [
        'local' => [
            'class' => M2code\FileManager\Drivers\Local\LocalFileDeleter::class,
            'disk'  => env('FILE_MANAGER_DISK', 'public'),
        ],
    ],
    'default_url_generator' => env('FILE_MANAGER_DRIVER', 'local'),
    'url_generators' => [
        'local' => [
            'class' => M2code\FileManager\Infrastructure\UrlGenerator\LocalFileUrlGenerator::class,
            'disk'  => env('FILE_MANAGER_DISK', 'public'),
        ],
    ],

    // --- File Size Validation (KiB) ---
    'validation' => [
        'max_file_size' => [
            'default'  => env('FILE_MANAGER_MAX_SIZE_DEFAULT', 10240),
            'image'    => env('FILE_MANAGER_MAX_SIZE_IMAGE', 10240),
            'document' => env('FILE_MANAGER_MAX_SIZE_DOCUMENT', 20480),
        ],
    ],

    // --- Temporary Storage ---
    'tmp' => [
        'disk'     => env('FILE_MANAGER_TMP_DISK', 'local'),
        'prefix'   => env('FILE_MANAGER_TMP_PREFIX', 'tmp/uploads'),
        'lifetime' => env('FILE_MANAGER_TMP_LIFETIME', 86400),
    ],

    // --- API Security ---
    'api' => [
        'token'           => env('FILE_MANAGER_API_TOKEN'),
        'allowed_origins' => explode(',', env('FILE_MANAGER_ALLOWED_ORIGINS', '')),
        'middleware'      => env('FILE_MANAGER_API_MIDDLEWARE', 'file-manager.api'),
    ],

    // --- Upload Defaults & Retry ---
    'upload' => [
        'default_options' => [
            'image' => [
                'optimize'    => true,
                'blurhash'    => true,
                'watermark'   => false,
                'low_quality' => false,
            ],
        ],
        'retry' => [
            'enabled'      => env('FILE_MANAGER_RETRY_ENABLED', true),
            'max_attempts' => env('FILE_MANAGER_RETRY_MAX', 3),
            'delay'        => env('FILE_MANAGER_RETRY_DELAY', 100),
        ],
    ],
];
```

---

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `FILE_MANAGER_DRIVER` | `local` | Active storage driver |
| `FILE_MANAGER_DISK` | `public` | Laravel filesystem disk for permanent storage |
| `FILE_MANAGER_DELETER` | (falls back to `FILE_MANAGER_DRIVER`) | Active deletion driver |
| `FILE_MANAGER_API_TOKEN` | — | Bearer token for upload API authentication |
| `FILE_MANAGER_API_MIDDLEWARE` | `file-manager.api` | Middleware alias applied to API routes |
| `FILE_MANAGER_ALLOWED_ORIGINS` | — | Comma-separated CORS origin whitelist |
| `FILE_MANAGER_MAX_SIZE_DEFAULT` | `10240` | Default max upload size (KiB) |
| `FILE_MANAGER_MAX_SIZE_IMAGE` | `10240` | Max upload size for images (KiB) |
| `FILE_MANAGER_MAX_SIZE_DOCUMENT` | `20480` | Max upload size for documents (KiB) |
| `FILE_MANAGER_TMP_DISK` | `local` | Filesystem disk for temporary uploads |
| `FILE_MANAGER_TMP_PREFIX` | `tmp/uploads` | Directory prefix within tmp disk |
| `FILE_MANAGER_TMP_LIFETIME` | `86400` | Tmp file lifetime in seconds (24 hours) |
| `FILE_MANAGER_RETRY_ENABLED` | `true` | Enable automatic retry on upload failure |
| `FILE_MANAGER_RETRY_MAX` | `3` | Maximum retry attempts |
| `FILE_MANAGER_RETRY_DELAY` | `100` | Delay between retries (milliseconds) |

---

## Artisan Commands

| Command | Description |
|---------|-------------|
| `file-manager:clean-tmp` | Delete expired temporary upload directories |
| `file-manager:test-upload` | Upload a dummy SVG for integration testing |

---

## Imagick Setup

Image processing features (BlurHash, optimization, watermark, low-quality variants) require the Imagick PHP extension, declared in `composer.json` as `ext-imagick`.

### Installation by platform

**Ubuntu / Debian**

```bash
sudo apt update && sudo apt install imagemagick php-imagick
sudo service php-fpm restart
```

**CentOS / RHEL**

```bash
sudo yum install epel-release ImageMagick ImageMagick-devel
sudo pecl install imagick
```

**macOS**

```bash
brew install imagemagick && pecl install imagick
```

Enable in `php.ini`:

```ini
extension=imagick
```

**Windows**

Download the appropriate DLL from [windows.php.net](https://windows.php.net/downloads/pecl/releases/imagick/), matching your PHP version, thread safety (TS/NTS), and architecture (x64/x86). Copy to `ext/` and add `extension=imagick` to `php.ini`.

### Verification

```bash
php -m | grep imagick
```

Expected output: `imagick`

---

## Testing

The package includes 82 tests covering all features. Run the suite from the package root:

```bash
vendor/bin/phpunit -c src/phpunit.xml
```

---

## License

[MIT License](LICENSE) — Marij Mokoginta (M2code)
