## m2code/file-manager

This package provides file management capabilities for Laravel applications: upload with image processing, storage across multiple drivers, URL generation, and file lifecycle management.

### When to use this package

Use when the user needs to upload files (images, SVG, PDF), generate image variants (BlurHash, AVIF/WebP optimization, watermark, low-quality placeholders), move files between storage layers, generate public or signed URLs, or delete files individually or in batch.

### Three Main Facades

@verbatim
<code-snippet name="FileManager Facade API" lang="php">
use M2code\FileManager\Facades\FileManager;
use M2code\FileManager\Facades\FileMover;
use M2code\FileManager\Facades\FileUrl;

// Save any file type (UploadedFile, base64, SVG string)
$result = FileManager::save($file, 'uploads');
$result->filePath; // "uploads/abc123.png"

// Delete files
FileManager::delete('uploads/old.png');
FileManager::deleteMany(['a.jpg', 'b.jpg']);

// Move from tmp storage to permanent (see file-manager-api-integration skill)
$path = FileMover::move('tmp/uploads/uuid/photo.png', 'avatars');

// Generate URLs
$url = FileUrl::getUrl('avatars/photo.png');
$signed = FileUrl::getSignedUrl('avatars/photo.png', now()->addHour());
</code-snippet>
@endverbatim

### Two Upload Modes

**Programmatic (server-side):** Use `ImageUploader` fluent API directly in PHP code. Best for forms processed by Laravel controllers.

@verbatim
<code-snippet name="ImageUploader Fluent API" lang="php">
use M2code\FileManager\Application\Uploader\ImageUploader;

$result = ImageUploader::make()
    ->blur()                    // BlurHash string
    ->optimize('avif')          // AVIF with WebP fallback
    ->watermark()               // watermark overlay
    ->lowQuality()              // low quality placeholder
    ->upload($request->file('photo'), 'uploads/images');

// Access variants (use null-safe accessor — variants not requested are null)
$result->variants->get('original')?->path;
$result->variants->get('optimized')?->path;
$result->blurhash;              // null if SVG or not requested
</code-snippet>
@endverbatim

**HTTP API (for frontend/mobile):** Use `POST /upload` endpoint with bearer token auth. Files go to tmp storage; promote with `FileMover::move()`. See the `file-manager-api-integration` skill for the full flow.

@verbatim
<code-snippet name="UploadService Programmatic API" lang="php">
use M2code\FileManager\Application\UploadService;

$uploadService = app(UploadService::class);
$response = $uploadService->upload($file, $options);

// Then move to permanent storage:
$path = FileMover::move($response->tmpPath, 'avatars');
</code-snippet>
@endverbatim

### Image Processing Variants

When using `ImageUploader`, these variants may be generated (only when requested):

| Variant | Method | Description |
|---------|--------|-------------|
| `original` | Always | Source file, no transformation |
| `optimized` | `->optimize('avif')` | AVIF/WebP compressed version |
| `low_quality` | `->lowQuality()` | Low-res JPEG placeholder |
| `watermark` | `->watermark()` | Watermarked copy |

**SVG files** (`image/svg+xml`) automatically skip all raster processing — only the `original` variant is saved, and `$result->blurhash` is always `null` for SVGs. No special handling code is needed; the package handles this internally.

### File Type Support

| MIME Type | Handler | Processing |
|-----------|---------|-------------|
| `image/*` (raster) | `ImageFileHandler` | BlurHash, optimize, watermark, low quality |
| `image/svg+xml` | `SvgFileHandler` | Saved as-is, no processing |
| `application/pdf` | `PdfFileHandler` | Saved as-is, no processing |

Unsupported file types (zip, docx, mp4, etc.) throw a `RuntimeException`.

### Input Types

All upload methods accept three input types automatically — no manual conversion:
1. `UploadedFile` — standard Laravel file from `$request->file()`
2. Base64 data URI — `'data:image/png;base64,...'`
3. Raw SVG string — `'<svg xmlns="...">...</svg>'`

### Temporary Storage and Cleanup

Files uploaded via the HTTP API go to **tmp storage** (configured via `FILE_MANAGER_TMP_DISK`). They must be moved to permanent storage using `FileMover::move()`. Tmp files older than `FILE_MANAGER_TMP_LIFETIME` (default 24 hours) are automatically deleted by the `file-manager:clean-tmp` scheduled command.

### Skills

For detailed, step-by-step workflows, use these skills (loaded on-demand by Boost):
- `file-manager-setup` — installation, Imagick setup, environment configuration
- `file-manager-image-upload` — programmatic image upload with all processing options
- `file-manager-api-integration` — HTTP upload API for frontend/mobile integration
- `file-manager-lifecycle` — FileMover, FileUrl, file deletion
- `file-manager-debug` — troubleshooting common issues
