# File Manager for Laravel

> A modular, clean-architecture-based Laravel package to manage file operations — image, video, documents — with support for multiple drivers (local, cloud, Firebase, etc), progressive images, flexible configuration, and HTTP upload API.

---

## Features

- **File Upload API** — HTTP POST endpoint with auth, validation, and flexible options
- **Save & Delete** — Single or batch file operations via facade
- **Auto-detect file type** & route to appropriate handler (image, SVG, PDF)
- **Input-agnostic** — `UploadedFile`, base64 data URI, raw SVG string, JSON body
- **Image processing** — blurhash, low quality, watermark, optimized AVIF/WebP
- **Structured variants** — `original`, `optimized`, `low_quality`, `watermark`
- **Extensible drivers** — Local, S3, Firebase, custom
- **Tmp-first upload** — Upload to temporary storage, move to permanent via FileMover
- **Cancel token** — Cancel in-flight uploads via cache-based token
- **Scheduler** — Auto-cleanup expired tmp files (daily)
- **Facades** — `FileManager`, `FileUrl`, `FileMover`
- **Clean architecture** — DDD-friendly, fully testable (82 tests)

---

## Installation

```bash
composer require m2code/file-manager
```

## Publish Configuration

```bash
php artisan vendor:publish --tag=config --provider="M2code\FileManager\FileManagerServiceProvider"
```

---

## HTTP Upload API

### Authentication

The upload endpoints are protected by a bearer token. Set your token in `.env`:

```env
FILE_MANAGER_API_TOKEN=your-secret-token
```

Multiple tokens (comma-separated) are supported for zero-downtime rotation:

```env
FILE_MANAGER_API_TOKEN=old-token,new-token
```

If no token is configured, the endpoints are open (not recommended for production).

### POST /upload

Upload a file and receive temporary storage paths. The file is saved to the tmp disk — move it to permanent storage using `FileMover`.

**Request** (multipart):

```
POST /upload
Authorization: Bearer your-secret-token
Content-Type: multipart/form-data

file: (binary)
options[blurhash]: false
options[optimize]: true
```

**Request** (JSON base64):

```
POST /upload
Authorization: Bearer your-secret-token
Content-Type: application/json

{
    "file": "data:image/png;base64,iVBORw0KGgo...",
    "options": {
        "blurhash": false,
        "optimize": true
    },
    "cancel_token": "optional-uuid-for-cancellation"
}
```

**Response** (image):

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

### Supported options per file type

| Type | Option | Default | Description |
|------|--------|---------|-------------|
| image | `blurhash` | `true` | Generate BlurHash string |
| image | `optimize` | `true` | Generate AVIF/WEBP optimized variant |
| image | `watermark` | `false` | Apply watermark |
| image | `low_quality` | `false` | Generate low quality placeholder |
| svg | (none) | - | SVG is saved as-is, image options are ignored |
| document | (none) | - | PDF is saved as-is |

Unknown options are silently ignored. Defaults can be overridden via config:

```php
// config/file-manager.php
'upload' => [
    'default_options' => [
        'image' => [
            'optimize'    => true,
            'blurhash'    => true,
            'watermark'   => false,
            'low_quality' => false,
        ],
    ],
],
```

### File size validation

Configured per file type category, with env override support:

```env
FILE_MANAGER_MAX_SIZE_DEFAULT=10240    # KiB
FILE_MANAGER_MAX_SIZE_IMAGE=10240
FILE_MANAGER_MAX_SIZE_DOCUMENT=20480
```

---

## Cancel Token

Cancel an in-progress upload by marking a token before or during the upload.

### POST /upload/cancel

```json
POST /upload/cancel
Authorization: Bearer your-secret-token

{ "cancel_token": "my-upload-uuid" }
```

Tokens can also be sent via `X-Cancel-Token` header:

```
POST /upload
Authorization: Bearer your-secret-token
X-Cancel-Token: my-upload-uuid
```

Cancelled tokens expire after 10 minutes. A cancelled token causes the upload to throw a `499 Client Closed Request`.

---

## FileMover: Tmp to Permanent

Files uploaded via the API are stored in the tmp disk. Use `FileMover` to promote them:

```php
use M2code\FileManager\Facades\FileMover;

// Move single file
$permanentPath = FileMover::move(
    tmpPath: 'tmp/uploads/uuid/original.png',
    destinationFolder: 'avatars',
);

// Move all files in a tmp folder
$results = FileMover::moveAll(
    tmpFolder: 'tmp/uploads/uuid',
    destinationFolder: 'avatars',
);
// Returns: ['tmp/uploads/uuid/original.png' => 'avatars/original.png', ...]
```

The source files are deleted from tmp after a successful move.

---

## Scheduler: Cleanup Tmp

The `file-manager:clean-tmp` command is auto-registered and runs **daily**. Client only needs the standard Laravel cronjob:

```cron
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

For development, run the scheduler manually:

```bash
php artisan schedule:work
```

Or run cleanup immediately:

```bash
php artisan file-manager:clean-tmp
```

Configure tmp lifetime in `.env`:

```env
FILE_MANAGER_TMP_LIFETIME=86400   # seconds (24 hours)
FILE_MANAGER_TMP_DISK=local
FILE_MANAGER_TMP_PREFIX=tmp/uploads
```

---

## Basic Usage (Facade)

### Save file without processing

```php
use M2code\FileManager\Facades\FileManager;

$result = FileManager::save($request->file('image'), 'uploads');
$result->filePath;
```

### Save from base64 or raw SVG

```php
// Base64 PNG
$base64Png = 'data:image/png;base64,...';
$png = FileManager::save($base64Png, 'uploads');

// Base64 SVG
$base64Svg = 'data:image/svg+xml;base64,...';
$svgFromBase64 = FileManager::save($base64Svg, 'uploads');

// Raw SVG string
$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64">...</svg>';
$svgRaw = FileManager::save($svg, 'uploads');
```

### Upload image with processing

```php
use M2code\FileManager\Application\Uploader\ImageUploader;

$result = ImageUploader::make()
    ->blur()
    ->lowQuality()
    ->watermark()
    ->optimize('avif')
    ->upload($request->file('photo'), 'uploads/images');

$result->variants->get('original')?->path;
$result->variants->get('optimized')?->path;
$result->variants->get('low_quality')?->path;
$result->variants->get('watermark')?->path;
$result->blurhash;
```

### ImageUploader with options array

```php
$result = ImageUploader::make()
    ->withOptions([
        'blurhash'    => true,
        'optimize'    => false,
        'watermark'   => true,
        'low_quality' => false,
    ])
    ->upload($file, 'uploads');
```

### SVG in ImageUploader

```php
$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64">...</svg>';

$result = ImageUploader::make()
    ->blur()->lowQuality()->watermark()->optimize('avif')
    ->upload($svg, 'uploads/images');

// SVG only keeps original variant — raster processing is skipped:
$result->variants->get('original')?->path;   // not null
$result->variants->get('low_quality');       // null
$result->variants->get('optimized');         // null
$result->blurhash;                           // null
```

### Delete files

```php
use M2code\FileManager\Facades\FileManager;

FileManager::delete('uploads/images/file.jpg');

FileManager::deleteMany([
    'uploads/images/a.jpg',
    'uploads/images/b.jpg',
]);

// Delete all variants from upload result:
FileManager::deleteVariants($result->variants);
```

### Get file URL

```php
use M2code\FileManager\Facades\FileUrl;

$url = FileUrl::getUrl('uploads/images/image.jpg');
$signed = FileUrl::getSignedUrl('uploads/images/image.jpg', now()->addMinutes(5));
```

---

## Configuration Reference

Full `config/file-manager.php` after publish:

```php
return [
    // Storage driver for permanent storage
    'default_driver' => env('FILE_MANAGER_DRIVER', 'local'),
    'drivers' => [
        'local' => [
            'class' => LocalFileSaver::class,
            'disk' => env('FILE_MANAGER_DISK', 'public'),
        ],
    ],

    // File deletion driver
    'default_deleter' => env('FILE_MANAGER_DELETER', env('FILE_MANAGER_DRIVER', 'local')),
    'deleters' => [
        'local' => [
            'class' => LocalFileDeleter::class,
            'disk' => env('FILE_MANAGER_DISK', 'public'),
        ],
    ],

    // URL generation driver
    'default_url_generator' => env('FILE_MANAGER_DRIVER', 'local'),
    'url_generators' => [
        'local' => [
            'class' => LocalFileUrlGenerator::class,
            'disk' => env('FILE_MANAGER_DISK', 'public'),
        ],
    ],

    // File size validation (KiB)
    'validation' => [
        'max_file_size' => [
            'default'  => env('FILE_MANAGER_MAX_SIZE_DEFAULT', 10240),
            'image'    => env('FILE_MANAGER_MAX_SIZE_IMAGE', 10240),
            'document' => env('FILE_MANAGER_MAX_SIZE_DOCUMENT', 20480),
        ],
    ],

    // Temporary storage for upload API
    'tmp' => [
        'disk'     => env('FILE_MANAGER_TMP_DISK', 'local'),
        'prefix'   => env('FILE_MANAGER_TMP_PREFIX', 'tmp/uploads'),
        'lifetime' => env('FILE_MANAGER_TMP_LIFETIME', 86400),
    ],

    // API security
    'api' => [
        'token'    => env('FILE_MANAGER_API_TOKEN'),
        'allowed_origins' => explode(',', env('FILE_MANAGER_ALLOWED_ORIGINS', '')),
        'middleware' => env('FILE_MANAGER_API_MIDDLEWARE', 'file-manager.api'),
    ],

    // Default upload options and retry
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
| `FILE_MANAGER_DRIVER` | `local` | Storage driver |
| `FILE_MANAGER_DISK` | `public` | Laravel filesystem disk |
| `FILE_MANAGER_DELETER` | (falls back to `FILE_MANAGER_DRIVER`) | Deletion driver |
| `FILE_MANAGER_API_TOKEN` | - | Bearer token(s) for upload API |
| `FILE_MANAGER_API_MIDDLEWARE` | `file-manager.api` | Custom middleware for API routes |
| `FILE_MANAGER_ALLOWED_ORIGINS` | - | Comma-separated CORS origins |
| `FILE_MANAGER_MAX_SIZE_DEFAULT` | `10240` | Max size default (KiB) |
| `FILE_MANAGER_MAX_SIZE_IMAGE` | `10240` | Max size for images (KiB) |
| `FILE_MANAGER_MAX_SIZE_DOCUMENT` | `20480` | Max size for documents (KiB) |
| `FILE_MANAGER_TMP_DISK` | `local` | Disk for temporary uploads |
| `FILE_MANAGER_TMP_PREFIX` | `tmp/uploads` | Directory prefix for tmp files |
| `FILE_MANAGER_TMP_LIFETIME` | `86400` | Tmp file lifetime in seconds |
| `FILE_MANAGER_RETRY_ENABLED` | `true` | Enable upload retry |
| `FILE_MANAGER_RETRY_MAX` | `3` | Max retry attempts |
| `FILE_MANAGER_RETRY_DELAY` | `100` | Delay between retries (ms) |

---

## Facades

| Facade | Accessor | Methods |
|--------|----------|---------|
| `FileManager` | `file-manager` | `save()`, `delete()`, `deleteMany()`, `deleteVariants()` |
| `FileUrl` | `file-url` | `getUrl()`, `getSignedUrl()` |
| `FileMover` | `file-mover` | `move()`, `moveAll()` |

---

## Supported Drivers

- Local (default)
- Extensible: S3, Firebase, custom (implement `FileSaver`, `FileDeleter`, `FileUrlGenerator`)

---

## Requirement: Imagick

This package uses `intervention/image` with Imagick driver for image processing:

- Blurhash generation
- Optimized AVIF/WebP variant generation
- Low quality image variants
- Watermark

Imagick is required (`ext-imagick` in `composer.json`).

### Why Imagick?

Compared to GD:
- Better image quality
- Faster processing for large images
- More advanced image manipulation

### Installation Guide

#### Windows

1. Download Imagick DLL from https://windows.php.net/downloads/pecl/releases/imagick/
2. Match your PHP version, thread safety (TS/NTS), architecture (x64/x86)
3. Copy `.dll` to `ext/`
4. Enable in `php.ini`: `extension=imagick`
5. Restart web server

#### macOS

```bash
brew install imagemagick
pecl install imagick
```

Enable in `php.ini`: `extension=imagick`

#### Linux (Ubuntu/Debian)

```bash
sudo apt update
sudo apt install imagemagick php-imagick
sudo service php-fpm restart
```

#### Linux (CentOS/RHEL)

```bash
sudo yum install epel-release
sudo yum install ImageMagick ImageMagick-devel
sudo pecl install imagick
```

#### Verify

```bash
php -m | grep imagick
```

---

## Artisan Commands

| Command | Description |
|---------|-------------|
| `file-manager:clean-tmp` | Delete expired temporary upload directories |
| `file-manager:test-upload` | Upload a dummy SVG for testing |

---

## Stable Flow

- Upload via HTTP API with auth, validation, and flexible options
- Upload from code via `FileManager` facade or `ImageUploader` fluent API
- Read variant paths from `ImageUploadResult::variants`
- Move tmp files to permanent storage via `FileMover`
- Generate URL / signed URL via `FileUrl`
- Delete single / batch / all variants via `FileManager`
- Auto-cleanup expired tmp files via scheduler

---

## License

[MIT License](LICENSE) © Marij Mokoginta (M2code)
