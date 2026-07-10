---
name: file-manager-dev-action
description: Guides adding a new image processing action to the m2code/file-manager package. Use when asked to "add image processing", "add new variant type", "create image action", "add thumbnail generation", "extend image processor", or "add image transformation". Covers implementing ImageAction, registering in ServiceProvider, adding to ImageProcessor pipeline, and wiring into ImageUploader fluent API.
---

# Add a New Image Processing Action

## Overview

Add a new image transformation (e.g., thumbnail, grayscale, rotate, custom filter) that generates a new variant from an uploaded image. Each action is a separate class implementing the `ImageAction` pattern.

## Prerequisites

Before starting, read:
- `src/Application/Image/Actions/ImageAction.php` — base class (if it exists; check current codebase)
- `src/Application/Image/Actions/GenerateBlurAction.php` — reference implementation
- `src/Application/Image/ImageProcessor.php` — how actions are orchestrated
- `src/Application/Uploader/ImageUploader.php` — fluent API

## Step 1: Create the action class

Create in `src/Application/Image/Actions/`:

```php
<?php

namespace M2code\FileManager\Application\Image\Actions;

use Intervention\Image\ImageManager;

class GenerateThumbnailAction
{
    protected ImageManager $imageManager;

    public function __construct()
    {
        $this->imageManager = ImageManager::imagick();
    }

    /**
     * Execute the action on an input file.
     *
     * @param  string  $file  Path to the source image file
     * @param  mixed   ...$args  Optional arguments (e.g., width, height)
     * @return string  Path to the generated output file
     */
    public function execute(string $file, ...$args): string
    {
        $width = $args[0] ?? 150;
        $height = $args[1] ?? 150;

        $outputPath = tempnam(sys_get_temp_dir(), 'fm_thumb_') . '.jpg';

        $image = $this->imageManager->read($file);
        $image->cover($width, $height);
        $image->toJpeg(70)->save($outputPath);

        return $outputPath;
    }
}
```

### Action conventions

- Name: `Generate{Thing}Action` or `Apply{Thing}Action`
- Single entry point: `execute(string $file): string`
- Takes file path as input, returns file path as output
- Uses tempnam() for output — the caller handles cleanup
- Uses Intervention Image (Imagick driver) for transformations
- Never throws for unsupported formats — return early or return the original file

## Step 2: Register in ServiceProvider

Edit `src/FileManagerServiceProvider.php`:

```php
use M2code\FileManager\Application\Image\Actions\GenerateThumbnailAction;

// In the register() method:
$this->app->bind(GenerateThumbnailAction::class, fn () => new GenerateThumbnailAction());
```

## Step 3: Add to ImageProcessor

Edit `src/Application/Image/ImageProcessor.php`:

```php
class ImageProcessor
{
    protected bool $shouldThumbnail = false;

    public function __construct(
        protected GenerateBlurAction $blurAction,
        protected GenerateLowQualityAction $lowQualityAction,
        protected ApplyWatermarkAction $watermarkAction,
        protected GenerateOptimizedImageAction $optimizedImageAction,
        protected GenerateThumbnailAction $thumbnailAction,  // ← add
    ) {}

    public function withThumbnail(bool $shouldThumbnail): self
    {
        $this->shouldThumbnail = $shouldThumbnail;
        return $this;
    }

    public function process($file): FileVariants
    {
        // ...existing processing...

        if ($this->shouldThumbnail) {
            $variants->add(new FileVariant(
                'thumbnail',
                $this->thumbnailAction->execute($file)
            ));
        }

        return $variants;
    }

    public function reset(): self
    {
        // ...existing resets...
        $this->shouldThumbnail = false;  // ← add
        return $this;
    }
}
```

Update the `ImageProcessor` binding in ServiceProvider to include the new dependency.

## Step 4: Add fluent API to ImageUploader

Edit `src/Application/Uploader/ImageUploader.php`:

```php
class ImageUploader
{
    protected bool $thumbnail = false;

    public function thumbnail(): self
    {
        $this->thumbnail = true;
        return $this;
    }

    public function upload($file, string $folder, ?FileSaver $driver = null): ImageUploadResult
    {
        // ...existing code...

        $processedVariants = $this->processor
            ->reset()
            ->withBlur($this->blur)
            ->withWatermark($this->watermark)
            ->withLowQuality($this->lowQuality)
            ->withOptimize($this->optimize, $this->optimizeFormat)
            ->withThumbnail($this->thumbnail)  // ← add
            ->process($this->resolveProcessableSource($input, $file));

        // ...save variants...
    }
}
```

Also add the key to `withOptions()`:

```php
public function withOptions(array $options): self
{
    foreach ($options as $key => $value) {
        if (!is_bool($value)) continue;

        match ($key) {
            'blurhash'    => $this->blur = $value,
            'watermark'   => $this->watermark = $value,
            'low_quality' => $this->lowQuality = $value,
            'optimize'    => $this->optimize = $value,
            'thumbnail'   => $this->thumbnail = $value,  // ← add
            default       => null,
        };
    }
    return $this;
}
```

## Step 5: Write tests

```php
<?php

namespace M2code\FileManager\tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use M2code\FileManager\Application\Uploader\ImageUploader;
use M2code\FileManager\tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ThumbnailVariantTest extends TestCase
{
    #[Test]
    public function it_generates_thumbnail_variant(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('photo.png', 800, 600);

        $result = ImageUploader::make()
            ->thumbnail()
            ->upload($file, 'photos');

        $thumbnail = $result->variants->get('thumbnail');
        $this->assertNotNull($thumbnail);
        Storage::disk('public')->assertExists($thumbnail->path);
    }

    #[Test]
    public function thumbnail_is_null_when_not_requested(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('photo.png', 100, 100);

        $result = ImageUploader::make()
            ->upload($file, 'photos');

        $this->assertNull($result->variants->get('thumbnail'));
    }
}
```

Run tests:

```bash
vendor/bin/phpunit -c phpunit.xml --filter ThumbnailVariantTest
```

## Quality checklist

- [ ] Action class follows `{Verb}{Noun}Action` naming
- [ ] `execute($file): string` accepts file path, returns output path
- [ ] Registered in ServiceProvider via `->bind()`
- [ ] Added to `ImageProcessor` pipeline with boolean flag + reset
- [ ] Added to `ImageUploader` fluent API (method + withOptions key)
- [ ] Added to `ImageFileHandler::supportedOptions()` and `defaultOptions()` if applicable
- [ ] New variant key follows snake_case convention
- [ ] Backward compatible — existing uploads unaffected
- [ ] Tests cover: variant generated when requested, null when not requested
- [ ] SVG files skip the action (handled by ImageUploader)
