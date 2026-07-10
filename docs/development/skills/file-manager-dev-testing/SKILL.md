---
name: file-manager-dev-testing
description: Guides writing and running tests for the m2code/file-manager package. Use when asked to "write tests", "run file-manager tests", "add test coverage", "fix failing test", or "test image upload". Covers PHPUnit configuration, test structure, disk faking, image mocking, feature vs unit tests, and assertion patterns.
---

# Testing the File Manager Package

## Overview

The package uses PHPUnit 11 with Orchestra Testbench for Laravel integration. Tests are organized into Feature (integration) and Unit (isolated) categories. All tests use fake disks — never write to the real filesystem.

## Running Tests

```bash
# Full suite
vendor/bin/phpunit -c phpunit.xml

# Single test method
vendor/bin/phpunit -c phpunit.xml --filter test_image_upload

# Single test file
vendor/bin/phpunit -c phpunit.xml tests/Feature/FileUploadTest.php
```

## Test Structure

```
tests/
├── TestCase.php          ← Base: boots Orchestra, sets up config
├── Feature/              ← Full Laravel boot, tests end-to-end flows
└── Unit/                 ← No framework boot, tests isolated logic
```

### Feature vs Unit: when to use which

| Feature Test | Unit Test |
|-------------|-----------|
| Upload → variants → save → URL → delete | DTO serialization |
| HTTP API request → response | Value object construction |
| Driver save/delete/move with fake disk | Factory method detection |
| Console command execution | Helper method logic |

## Test Conventions

### Disk faking

Always fake disks. Never write to the real filesystem:

```php
protected function setUp(): void
{
    parent::setUp();
    Storage::fake('public');
    Storage::fake('tmp');
    Storage::fake('local');
}
```

### Image mocking

```php
// Raster image (with actual pixel content for processing)
$file = UploadedFile::fake()->image('photo.png', 100, 100);

// Non-image file with specific MIME type
$pdf = UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf');

// File with custom content (SVG)
$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20">...</svg>';
$file = UploadedFile::fake()->createWithContent('icon.svg', $svg);
```

### Assertion patterns

```php
// File existence
Storage::disk('public')->assertExists($path);
Storage::disk('public')->assertMissing($path);

// Response assertions (API tests)
$response->assertCreated();        // 201
$response->assertOk();             // 200
$response->assertUnauthorized();   // 401
$response->assertUnprocessable();  // 422
$response->assertJsonStructure(['type', 'tmp_path', 'extra']);

// Variant assertions
$this->assertNotNull($result->variants->get('original'));
$this->assertNull($result->variants->get('watermark'));

// BlurHash assertions
$this->assertNotNull($result->blurhash);
$this->assertNotEmpty($result->blurhash);

// URL assertions
$url = FileUrl::getUrl($path);
$this->assertNotNull($url);

$expiredUrl = FileUrl::getSignedUrl($path, now()->subMinute());
$this->get($expiredUrl)->assertForbidden(); // 403
```

## TestCase Base Class

The base `TestCase` extends `Orchestra\Testbench\TestCase` and sets up:

```php
namespace M2code\FileManager\tests;

use M2code\FileManager\FileManagerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
        // Additional setup per test class
    }

    protected function getPackageProviders($app): array
    {
        return [FileManagerServiceProvider::class];
    }
}
```

## Writing a New Feature Test

Template:

```php
<?php

namespace M2code\FileManager\tests\Feature;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use M2code\FileManager\tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class MyFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('tmp');
    }

    #[Test]
    public function it_does_something(): void
    {
        // Arrange
        $file = UploadedFile::fake()->image('test.png', 100, 100);

        // Act
        $result = /* call the code under test */;

        // Assert
        $this->assertNotNull($result);
        Storage::disk('public')->assertExists($result->filePath);
    }
}
```

## Writing a New Unit Test

Template:

```php
<?php

namespace M2code\FileManager\tests\Unit;

use M2code\FileManager\tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class MyUnitTest extends TestCase
{
    #[Test]
    public function it_does_something(): void
    {
        // No fake disks needed for pure logic tests

        $result = /* call the code under test */;

        $this->assertEquals('expected', $result);
    }

    #[Test]
    public function it_handles_edge_case(): void
    {
        // Always test: empty input, null, zero, negative, max values
    }
}
```

## Common Test Categories

### Upload tests
- Upload with default options
- Upload with all options disabled
- Upload with specific options enabled
- SVG upload skips processing
- Base64 input upload
- Invalid base64 is rejected

### API tests
- Valid token accepted
- Invalid/missing token rejected (401)
- File required (422)
- Options accepted and processed
- Unknown options ignored
- Cancel token flow
- Tmp folder isolation per request

### Driver tests
- File saved to correct disk
- File deleted from disk
- File move preserves filename
- Move throws on missing source
- Delete non-existing returns false

### Variant tests
- Original always present
- Each variant present when requested
- Each variant null when not requested
- BlurHash generated/not-generated
- Optimized format fallback

## Debugging Failing Tests

```bash
# Verbose output
vendor/bin/phpunit -c phpunit.xml --filter failing_test -v

# Stop on first failure
vendor/bin/phpunit -c phpunit.xml --stop-on-failure

# Check config
php -r "require 'vendor/autoload.php'; dump(config('file-manager'));"
```

## Quality checklist for any new test

- [ ] Uses `Storage::fake()` — never writes to real disk
- [ ] Uses `#[Test]` attribute (PHPUnit 11 convention)
- [ ] Tests both happy path and at least one edge case
- [ ] Asserts file existence/missing with specific disk
- [ ] Cleans up temp files if using `UploadedFile` with real path
- [ ] Does not depend on test execution order
- [ ] Passes in isolation: `--filter` on just this test
