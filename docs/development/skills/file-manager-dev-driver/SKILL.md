---
name: file-manager-dev-driver
description: Guides adding a new storage driver (S3, Firebase, etc.) to the m2code/file-manager package. Use when asked to "add S3 driver", "add Firebase storage", "implement cloud driver", "create new storage driver", or "extend storage backend". Covers implementing FileSaver, FileDeleter, FileUrlGenerator contracts and registering in config and resolvers.
---

# Add a New Storage Driver

## Overview

Add support for a new storage backend by implementing three Domain contracts and registering them in the configuration and resolver system.

## Prerequisites

Before starting, read:
- `src/Domain/Contracts/FileSaver.php` — save contract
- `src/Domain/Contracts/FileDeleter.php` — delete contract
- `src/Domain/Contracts/FileUrlGenerator.php` — URL contract
- `src/Drivers/Local/LocalFileSaver.php` — reference implementation
- `src/Core/FileDriverResolver.php` — how drivers are resolved
- `src/config/file-manager.php` — driver configuration

## Architecture

Each driver slot is independent. You can implement one, two, or all three contracts depending on what the backend supports:

```
Domain Contracts         Driver Implementations
─────────────────       ─────────────────────
FileSaver       ←───    S3FileSaver
FileDeleter     ←───    S3FileDeleter
FileUrlGenerator ←───   S3FileUrlGenerator
```

## Step 1: Create the driver directory

```
src/Drivers/S3/
├── S3FileSaver.php
└── S3FileDeleter.php

src/Infrastructure/UrlGenerator/
└── S3FileUrlGenerator.php
```

## Step 2: Implement FileSaver

```php
<?php

namespace M2code\FileManager\Drivers\S3;

use M2code\FileManager\Application\FileInput\FileInput;
use M2code\FileManager\Domain\Contracts\FileSaver;
use M2code\FileManager\Domain\Services\FileNameGenerator;
use M2code\FileManager\DTO\FileOperationResult;
use Illuminate\Support\Facades\Storage;

class S3FileSaver implements FileSaver
{
    protected string $disk;

    public function __construct(array $config = [])
    {
        $this->disk = $config['disk'] ?? 's3';
    }

    public function save($file, string $folder): FileOperationResult
    {
        $input = FileInputFactory::from($file);

        $fileName = FileNameGenerator::generate(
            $input->getExtension()
        );

        $path = trim($folder, '/') . '/' . $fileName;

        Storage::disk($this->disk)->put($path, $input->getContent());

        return new FileOperationResult(
            filePath: $path,
            fileName: $fileName,
        );
    }
}
```

### Key rules for FileSaver

- Accept mixed `$file` input (UploadedFile, base64, SVG string)
- Use `FileInputFactory::from()` to normalize input
- Use `FileNameGenerator::generate()` for unique filenames
- Return `FileOperationResult` with filePath and fileName
- Get disk name from constructor config array
- Never throw for valid input — let exceptions propagate naturally for I/O failures

## Step 3: Implement FileDeleter

```php
<?php

namespace M2code\FileManager\Drivers\S3;

use M2code\FileManager\Domain\Contracts\FileDeleter;
use Illuminate\Support\Facades\Storage;

class S3FileDeleter implements FileDeleter
{
    protected string $disk;

    public function __construct(array $config = [])
    {
        $this->disk = $config['disk'] ?? 's3';
    }

    public function delete(string $path): bool
    {
        $storage = Storage::disk($this->disk);

        if (!$storage->exists($path)) {
            return false;
        }

        return $storage->delete($path);
    }

    public function deleteMany(array $paths): array
    {
        $results = [];

        foreach ($paths as $path) {
            $results[$path] = $this->delete($path);
        }

        return $results;
    }
}
```

### Key rules for FileDeleter

- Return `false` (not throw) for non-existing files
- `deleteMany()` returns a `[$path => bool]` map
- Operations must be idempotent

## Step 4: Implement FileUrlGenerator

```php
<?php

namespace M2code\FileManager\Infrastructure\UrlGenerator;

use DateTimeInterface;
use M2code\FileManager\Domain\Contracts\FileUrlGenerator;
use Illuminate\Support\Facades\Storage;

class S3FileUrlGenerator implements FileUrlGenerator
{
    protected string $disk;

    public function __construct(array $config = [])
    {
        $this->disk = $config['disk'] ?? 's3';
    }

    public function getUrl(string $path): string
    {
        return Storage::disk($this->disk)->url($path);
    }

    public function getSignedUrl(string $path, DateTimeInterface $expiresAt): string
    {
        return Storage::disk($this->disk)->temporaryUrl(
            $path,
            $expiresAt,
        );
    }
}
```

## Step 5: Register in configuration

Edit `src/config/file-manager.php`:

```php
'drivers' => [
    'local' => [
        'class' => LocalFileSaver::class,
        'disk' => env('FILE_MANAGER_DISK', 'public'),
    ],
    's3' => [                                 // ← add
        'class' => S3FileSaver::class,         // ← add
        'disk' => env('FILE_MANAGER_S3_DISK', 's3'), // ← add
    ],                                        // ← add
],

'deleters' => [
    'local' => [...],
    's3' => [                                 // ← add
        'class' => S3FileDeleter::class,       // ← add
        'disk' => env('FILE_MANAGER_S3_DISK', 's3'), // ← add
    ],                                        // ← add
],

'url_generators' => [
    'local' => [...],
    's3' => [                                  // ← add
        'class' => S3FileUrlGenerator::class,   // ← add
        'disk' => env('FILE_MANAGER_S3_DISK', 's3'), // ← add
    ],                                         // ← add
],
```

## Step 6: Update resolvers

The resolvers (`FileDriverResolver`, `FileDeleterResolver`, `FileUrlGeneratorResolver`) read the `default_driver` / `default_deleter` / `default_url_generator` config keys and instantiate the class from the corresponding section. Verify they handle the new key — if they use a simple key lookup, no code change is needed.

## Step 7: Write tests

```php
<?php

namespace M2code\FileManager\tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use M2code\FileManager\Facades\FileManager;
use M2code\FileManager\tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class S3DriverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3');
        config()->set('file-manager.default_driver', 's3');
    }

    #[Test]
    public function it_saves_file_to_s3_driver(): void
    {
        $file = UploadedFile::fake()->image('photo.png', 100, 100);
        $result = FileManager::save($file, 'uploads');

        Storage::disk('s3')->assertExists($result->filePath);
    }
}
```

Run tests:

```bash
vendor/bin/phpunit -c phpunit.xml --filter S3DriverTest
```

## Quality checklist

- [ ] FileSaver, FileDeleter, and FileUrlGenerator all implemented
- [ ] All three use constructor config array for disk name
- [ ] Config entries added under `drivers`, `deleters`, `url_generators`
- [ ] Resolvers work with the new key (no code change needed if key-based lookup)
- [ ] Feature tests pass with faked disk
- [ ] README updated with new driver in Supported Drivers section
