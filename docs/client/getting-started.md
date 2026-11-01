# Getting Started

## Requirements

- PHP 8.2 or higher
- Laravel 10, 11, 12, or 13
- Imagick PHP extension (for image processing)
- `intervention/image` v3

## Installation

```bash
composer require m2code/file-manager
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=config --provider="M2code\FileManager\FileManagerServiceProvider"
```

The package auto-discovers via Laravel's package discovery. No manual provider registration is needed.

## Imagick Setup

Image processing features (BlurHash, AVIF/WebP optimization, watermark, low-quality variants) require the Imagick PHP extension. It is declared in `composer.json` as `ext-imagick` and must be installed separately.

### Platform-specific installation

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

**Windows**

Download the appropriate DLL from [windows.php.net](https://windows.php.net/downloads/pecl/releases/imagick/), matching your PHP version, thread safety (TS/NTS), and architecture (x64/x86). Copy to `ext/` and add `extension=imagick` to `php.ini`.

### Enable in php.ini (all platforms)

```ini
extension=imagick
```

### Verification

```bash
php -m | grep imagick
# Expected output: "imagick"
```

## Verifying the Installation

Run the included test upload command:

```bash
php artisan file-manager:test-upload
```

Expected output: `"Saved at: test/..."` with a file path.

## First Configuration

Add the required environment variables to `.env`:

```env
FILE_MANAGER_DISK=public
FILE_MANAGER_API_TOKEN=your-secret-token-here
```

### Optional — File Encryption at Rest

Enable to encrypt file content before writing to disk. Files are decrypted on-the-fly when served.

```env
FILE_MANAGER_ENCRYPTION_ENABLED=true
FILE_MANAGER_ENCRYPTION_DRIVER=laravel        # 'laravel' (default) or 'openssl'
FILE_MANAGER_ENCRYPTION_KEY=                  # required when driver=openssl
```

### Optional — Secure URL Paths

When set, file paths in URLs are encrypted with AES-256-CBC instead of plain base64. Legacy base64-encoded URLs from previous versions remain valid (backward compatible).

```env
FILE_MANAGER_URL_SECRET=your-secret-key-here
```

See the [Usage Guide](usage.md) for details on both features.

## Next Steps

- [Usage Guide](usage.md) — Facades, ImageUploader, FileMover, FileUrl
- [HTTP Upload API](http-api.md) — Endpoints for frontend and mobile apps
- [Configuration Reference](configuration.md) — All config options and env variables
