# Usage Guide

## Facades

The package exposes three facades for the most common operations.

| Facade | Container Binding | Primary Methods |
|--------|------------------|----------------|
| `FileManager` | `file-manager` | `save()`, `delete()`, `deleteMany()`, `deleteVariants()` |
| `FileUrl` | `file-url` | `getUrl()`, `getSignedUrl()`, `decodePath()` |
| `FileMover` | `file-mover` | `move()`, `moveAll()` |

## Saving Files

All save methods accept three input types automatically:
- Laravel `UploadedFile` instances
- Base64 data URIs (`data:image/png;base64,...`)
- Raw SVG strings (`<svg>...</svg>`)

```php
use M2code\FileManager\Facades\FileManager;

// UploadedFile
$result = FileManager::save($request->file('document'), 'uploads');
echo $result->filePath; // "uploads/abc123.pdf"

// Base64
$result = FileManager::save('data:image/png;base64,...', 'uploads');

// Raw SVG
$result = FileManager::save('<svg>...</svg>', 'uploads');
```

## Image Upload with Processing

Use the `ImageUploader` fluent API for programmatic image uploads:

```php
use M2code\FileManager\Application\Uploader\ImageUploader;

$result = ImageUploader::make()
    ->blur()                    // generate BlurHash
    ->optimize('avif')          // AVIF with WebP fallback
    ->watermark()               // apply watermark
    ->lowQuality()              // generate low quality placeholder
    ->upload($request->file('photo'), 'uploads/images');
```

### Accessing Variants

```php
// Preferred: via variants collection (recommended)
$result->variants->get('original')?->path;
$result->variants->get('optimized')?->path;
$result->variants->get('watermark')?->path;
$result->variants->get('low_quality')?->path;
$result->blurhash;              // BlurHash string (null if SVG)

// Backward-compatible accessors
$result->path;                  // alias for original
$result->optimizedPath;         // alias for optimized
$result->lowQualityPath;        // alias for low_quality
$result->watermarkPath;         // alias for watermark
```

### Processing Options

| Method | Flag | Default | Description |
|--------|------|---------|-------------|
| `->blur()` | `blurhash` | `false` | Generate BlurHash string |
| `->optimize('avif')` | `optimize` | `false` | AVIF/WebP optimized variant |
| `->watermark()` | `watermark` | `false` | Watermark overlay |
| `->lowQuality()` | `low_quality` | `false` | Low quality JPEG placeholder |
| `->encrypted()` | `encrypted` | `null`¹ | Enable/disable file encryption |

¹ `null` = follow `FILE_MANAGER_ENCRYPTION_ENABLED` config. Set `true` or `false` to override per-upload.

Use `->withOptions([])` to pass options as an array:

```php
$result = ImageUploader::make()
    ->withOptions([
        'blurhash'  => true,
        'optimize'  => false,
        'watermark' => true,
    ])
    ->upload($file, 'uploads');
```

### SVG Behavior

SVG files (`image/svg+xml`) automatically skip all raster processing. Only the `original` variant is saved. `$result->blurhash` is `null`. No special handling code is needed — the package handles this internally.

## File Types

The package auto-detects file types and routes them to the appropriate handler:

| MIME Type | Handler | Processing |
|-----------|---------|-------------|
| `image/*` (except SVG) | `ImageFileHandler` | BlurHash, optimize, watermark, low quality |
| `image/svg+xml` | `SvgFileHandler` | None (saved as-is) |
| `application/pdf` | `PdfFileHandler` | None (saved as-is) |

Unsupported file types throw `RuntimeException`.

## Drivers

Each concern has its own swappable driver:

| Concern | Interface | Default |
|---------|-----------|---------|
| Storage | `FileSaver` | `LocalFileSaver` |
| Deletion | `FileDeleter` | `LocalFileDeleter` |
| URL generation | `FileUrlGenerator` | `LocalFileUrlGenerator` |
| File moving | `FileMover` | `LocalFileMover` |

Configure the active driver in `config/file-manager.php`:

```php
'default_driver'         => env('FILE_MANAGER_DRIVER', 'local'),
'default_deleter'        => env('FILE_MANAGER_DELETER', env('FILE_MANAGER_DRIVER', 'local')),
'default_url_generator'  => env('FILE_MANAGER_DRIVER', 'local'),
```

## FileMover: Tmp to Permanent

Files uploaded via the HTTP API reside in temporary storage. Promote them with `FileMover`:

```php
use M2code\FileManager\Facades\FileMover;

// Move a single file
$permanentPath = FileMover::move(
    tmpPath: 'tmp/uploads/550e8400/photo.png',
    destinationFolder: 'avatars',
);
// Returns: "avatars/photo.png"

// Move all files in a tmp folder (all variants at once)
$results = FileMover::moveAll(
    tmpFolder: 'tmp/uploads/550e8400',
    destinationFolder: 'avatars',
);
// Returns: ['tmp/uploads/550e8400/original.png' => 'avatars/original.png', ...]
```

- Source files are deleted from tmp after successful move
- Filename is preserved
- Throws `RuntimeException` if source not found
- Optional third parameter for target disk: `FileMover::move($tmp, $dest, 's3')`

## Generating URLs

```php
use M2code\FileManager\Facades\FileUrl;

$url = FileUrl::getUrl('avatars/photo.png');
$signed = FileUrl::getSignedUrl('avatars/photo.png', now()->addHour());
```

URLs resolve through `GET /file/{disk}/{path}`.

### Path Encoding

By default, the file path is base64-encoded in the URL. When `FILE_MANAGER_URL_SECRET` is configured, paths are **encrypted with AES-256-CBC** instead — making them indecipherable to third parties.

```env
# .env — enable encrypted paths
FILE_MANAGER_URL_SECRET=your-secret-key
```

Legacy base64-encoded URLs remain valid after enabling the secret. The `FileController` automatically detects and decodes both formats.

### Decoding Paths

```php
// Reverse a URL-encoded path back to the original file path
$originalPath = FileUrl::decodePath($encodedPath);
// Supports both encrypted and legacy base64 formats
// Returns null if decoding fails
```

## File Encryption at Rest

When enabled, file content is encrypted before writing to disk and decrypted on-the-fly when served via `FileController`.

### Configuration

```env
# .env
FILE_MANAGER_ENCRYPTION_ENABLED=true
FILE_MANAGER_ENCRYPTION_DRIVER=laravel    # 'laravel' | 'openssl'
FILE_MANAGER_ENCRYPTION_KEY=              # required when driver=openssl
```

Two encryption drivers are supported:

| Driver | Key Source | Notes |
|--------|-----------|-------|
| `laravel` (default) | `APP_KEY` from `.env` | Uses Laravel's `Crypt` facade. No additional setup required. |
| `openssl` | `FILE_MANAGER_ENCRYPTION_KEY` (min 32 chars) | Pure PHP OpenSSL. Framework-agnostic. Must set a custom key. |

### Per-Operation Override

Override the config default for individual operations:

```php
use M2code\FileManager\Facades\FileManager;
use M2code\FileManager\Application\Uploader\ImageUploader;

// Disable encryption for this specific save
FileManager::save($file, 'public', encrypted: false);

// Enable encryption via ImageUploader fluent API
$result = ImageUploader::make()
    ->encrypted()                       // force encrypt
    ->upload($file, 'invoices');

// Disable encryption via options array
$result = ImageUploader::make()
    ->withOptions(['encrypted' => false])
    ->upload($file, 'invoices');
```

### How It Works

1. **On save**: `LocalFileSaver` encrypts content via `ContentEncryptor` before `Storage::put()`
2. **On serve**: `FileController` decrypts content via `ContentEncryptor::decrypt()` before returning the response
3. **On move**: `LocalFileMover` delegates writes to `FileSaver::save()`, which handles encryption consistently

### Backward Compatibility

- When encryption is **disabled** (default): files saved as plaintext (same as previous versions)
- When encryption is **enabled**: new files are encrypted, existing plaintext files are served as-is
- If encryption is enabled and later disabled: new files are plaintext, previously encrypted files are served as plaintext (best-effort fallback)

## Deleting Files

```php
use M2code\FileManager\Facades\FileManager;

// Single file
FileManager::delete('uploads/old.png');

// Batch
FileManager::deleteMany(['a.jpg', 'b.jpg', 'c.jpg']);

// All variants of an upload
FileManager::deleteVariants($result->variants);
```

Deletion is idempotent — non-existing files return `false` instead of throwing.

## Scheduled Cleanup

Temporary files are automatically cleaned up daily. Enable the scheduler with the standard Laravel cron entry:

```cron
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

Manual cleanup:

```bash
php artisan file-manager:clean-tmp
```

Tmp lifetime is configurable:

```env
FILE_MANAGER_TMP_LIFETIME=86400   # seconds (default: 24 hours)
```
