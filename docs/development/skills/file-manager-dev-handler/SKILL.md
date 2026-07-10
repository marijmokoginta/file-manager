---
name: file-manager-dev-handler
description: Guides adding a new file type handler to the m2code/file-manager package. Use when asked to "add a new file type", "support video files", "add handler for", "create file type handler", or "extend file type support". Covers implementing FileTypeHandler interface, registering in ServiceProvider, writing feature tests, and updating config.
---

# Add a New File Type Handler

## Overview

Add support for a new file type (e.g., video, audio, document format) by implementing the `FileTypeHandler` interface and registering it in the handler chain.

## Prerequisites

Before starting, read:
- `src/Domain/Contracts/FileTypeHandler.php` — the contract
- `src/Application/FileRouter/ImageFileHandler.php` — reference implementation
- `src/FileManagerServiceProvider.php` — handler registration

## Step 1: Create the handler class

Create a new class in `src/Application/FileRouter/` following the naming convention `{Type}FileHandler`.

```php
<?php

namespace M2code\FileManager\Application\FileRouter;

use M2code\FileManager\Application\FileInput\FileInput;
use M2code\FileManager\Domain\Contracts\FileSaver;
use M2code\FileManager\Domain\Contracts\FileTypeHandler;
use M2code\FileManager\DTO\FileOperationResult;
use M2code\FileManager\DTO\UploadResponse;

class VideoFileHandler implements FileTypeHandler
{
    public function __construct(protected FileSaver $driver) {}

    public function canHandle(FileInput $input): bool
    {
        // Match video MIME types
        $mimeType = strtolower($input->getMimeType());
        return str_starts_with($mimeType, 'video/');
    }

    public function handleSave(FileInput $input, string $folder): FileOperationResult
    {
        return $this->driver->save($input, $folder);
    }

    public function handleUpload(FileInput $input, string $folder, array $options): UploadResponse
    {
        $result = $this->driver->save($input, $folder);

        // If your handler generates extra data (thumbnails, metadata),
        // include it in the 'extra' array.
        $extra = [];

        return new UploadResponse(
            type: 'video',
            tmpPath: $result->filePath,
            tmpFolder: $folder,
            originalName: $input->getClientOriginalName(),
            size: strlen($input->getContent()),
            mimeType: $input->getMimeType(),
            extra: $extra,
        );
    }

    public function supportedOptions(): array
    {
        // List option keys this handler accepts
        return [];
    }

    public function defaultOptions(): array
    {
        return [];
    }
}
```

### Rules for canHandle()

- Check MIME type, not file extension.
- Use `str_starts_with()` for prefix matching.
- Be as specific as possible to avoid false positives.
- SVG is excluded from `image/*` using `$mimeType !== 'image/svg+xml'`.

## Step 2: Register the handler

Edit `src/FileManagerServiceProvider.php`. Add the new handler to the `FileTypeRouterService` constructor array:

```php
$this->app->bind(FileSaver::class, function () {
    $driver = FileDriverResolver::resolve();

    return new FileTypeRouterService([
        new SvgFileHandler($driver),
        new PdfFileHandler($driver),
        new ImageFileHandler($driver),
        new VideoFileHandler($driver),  // ← add here

        // Other handlers
    ]);
});
```

Handler order matters: the first handler whose `canHandle()` returns `true` wins. SVG must come before Image because SVG also matches `image/*`.

## Step 3: Update configuration (if needed)

If the new file type needs a custom max file size, add it to `config/file-manager.php`:

```php
'validation' => [
    'max_file_size' => [
        'default'  => env('FILE_MANAGER_MAX_SIZE_DEFAULT', 10240),
        'image'    => env('FILE_MANAGER_MAX_SIZE_IMAGE', 10240),
        'document' => env('FILE_MANAGER_MAX_SIZE_DOCUMENT', 20480),
        'video'    => env('FILE_MANAGER_MAX_SIZE_VIDEO', 102400), // ← add
    ],
],
```

Update the `resolveCategory()` method in `UploadService` to map the MIME type to this category.

## Step 4: Write tests

Create a test file in `tests/Feature/`:

```php
<?php

namespace M2code\FileManager\tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use M2code\FileManager\Facades\FileManager;
use M2code\FileManager\tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class VideoFileUploadTest extends TestCase
{
    #[Test]
    public function it_handles_video_upload(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('test.mp4', 500, 'video/mp4');
        $result = FileManager::save($file, 'videos');

        $this->assertNotNull($result->filePath);
        $this->assertStringEndsWith('.mp4', $result->filePath);
        Storage::disk('public')->assertExists($result->filePath);
    }
}
```

Run tests:

```bash
vendor/bin/phpunit -c phpunit.xml --filter VideoFileUploadTest
```

## Step 5: Update documentation

- Add the new MIME type to the file type table in `README.md`
- Add the new handler to the supported file types list in `resources/boost/guidelines/core.blade.php`
- Add a new skill in `resources/boost/skills/` if the file type has a complex upload flow

## Quality checklist

- [ ] Handler implements all 5 methods of `FileTypeHandler`
- [ ] `canHandle()` is specific and does not overlap with existing handlers
- [ ] Handler is registered in `FileTypeRouterService` constructor
- [ ] Config updated for size validation if category is new
- [ ] Feature tests pass for all supported MIME types
- [ ] Documentation updated (README + Boost guidelines)
