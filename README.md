# File Manager for Laravel

[![PHP](https://img.shields.io/badge/PHP-^8.2-blue)](composer.json)
[![Laravel](https://img.shields.io/badge/Laravel-10_|_11_|_12_|_13-red)](composer.json)
[![Tests](https://img.shields.io/badge/tests-122_passing-brightgreen)](.)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

A modular, clean-architecture Laravel package for file management — upload, process, store, and serve files across multiple drivers and file types.

---

## Requirements

- PHP 8.2+ / Laravel 10–13 / Imagick PHP extension / `intervention/image` v3

## Installation

```bash
composer require m2code/file-manager
php artisan vendor:publish --tag=config --provider="M2code\FileManager\FileManagerServiceProvider"
```

## Quick Start

```php
use M2code\FileManager\Facades\FileManager;
use M2code\FileManager\Facades\FileUrl;
use M2code\FileManager\Application\Uploader\ImageUploader;

// Save any file (UploadedFile, base64, SVG string)
$result = FileManager::save($request->file('document'), 'uploads');

// Upload and process an image
$result = ImageUploader::make()
    ->blur()->optimize('avif')->watermark()
    ->upload($request->file('photo'), 'uploads');

$result->variants->get('original')?->path;
$result->blurhash;

// Upload with encryption (requires FILE_MANAGER_ENCRYPTION_ENABLED=true)
$result = ImageUploader::make()
    ->encrypted()
    ->upload($request->file('receipt'), 'invoices');

// Delete
FileManager::delete('uploads/old.png');
FileManager::deleteVariants($result->variants);

// URLs
$url = FileUrl::getUrl('uploads/photo.png');
$signed = FileUrl::getSignedUrl('uploads/photo.png', now()->addHour());
```

---

## Documentation

### For Users

| Document | Description |
|----------|-------------|
| [Getting Started](docs/client/getting-started.md) | Installation, requirements, Imagick setup |
| [Usage Guide](docs/client/usage.md) | Facades, ImageUploader, FileMover, FileUrl, variants, deletion |
| [HTTP Upload API](docs/client/http-api.md) | Endpoints, authentication, request/response formats, configuration |

### For Developers

| Document | Description |
|----------|-------------|
| [AGENTS.md](AGENTS.md) | Architecture conventions, DDD layers, code style, testing |
| [PRD: Core Package](docs/prd/000-file-manager-core.md) | Product vision, scope, roadmap |
| [PRD: File Upload API](docs/prd/001-file-upload-api.md) | Upload API feature requirements |
| [ADR Index](docs/adr/) | Architecture decision records |
| [Development Skills](docs/development/skills/) | Step-by-step guides for extending the package |

---

## Testing

```bash
vendor/bin/phpunit -c phpunit.xml
```

---

## License

[MIT License](LICENSE) — Marij Mokoginta (M2code)
