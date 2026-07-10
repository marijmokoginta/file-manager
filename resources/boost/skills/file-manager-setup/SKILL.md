---
name: file-manager-setup
description: Installs and configures the m2code/file-manager package for a Laravel project. Use when user asks to "install file manager", "setup file manager", "configure file upload package", "add file upload to Laravel", "install m2code file manager", or "setup file manager package". Covers composer installation, config publishing, Imagick extension setup per operating system, environment variables, and scheduler configuration.
---

# File Manager Setup

## Overview

Install and configure the `m2code/file-manager` package from scratch. The package requires PHP 8.2+, Laravel 10-13, and the Imagick PHP extension for image processing features. This skill guides through every step until the package is ready for use.

## Prerequisites

Before starting, verify:

- PHP 8.2 or higher
- Laravel 10, 11, 12, or 13
- Composer installed

## Step 1: Install the package

```bash
composer require m2code/file-manager
```

The package auto-discovers via Laravel's package discovery. No manual provider registration is needed.

## Step 2: Publish configuration

```bash
php artisan vendor:publish --tag=config --provider="M2code\FileManager\FileManagerServiceProvider"
```

This creates `config/file-manager.php`. All settings have sensible defaults and can be overridden via environment variables.

## Step 3: Install and verify Imagick

Image processing features (BlurHash, optimization, watermark, low-quality variants) require the Imagick PHP extension.

### Check if Imagick is installed

```bash
php -m | grep imagick
```

Expected output: `imagick`

If not installed, follow the appropriate section below.

### Ubuntu / Debian

```bash
sudo apt update && sudo apt install imagemagick php-imagick
sudo service php-fpm restart
```

### CentOS / RHEL

```bash
sudo yum install epel-release ImageMagick ImageMagick-devel
sudo pecl install imagick
```

### macOS

```bash
brew install imagemagick && pecl install imagick
```

### Windows

Download the appropriate DLL from [windows.php.net](https://windows.php.net/downloads/pecl/releases/imagick/), matching:
- PHP version
- Thread safety (TS/NTS)
- Architecture (x64/x86)

Copy to `ext/` directory and add `extension=imagick` to `php.ini`.

### After installation (all platforms)

Enable in `php.ini`:

```ini
extension=imagick
```

Restart PHP/web server, then verify:

```bash
php -m | grep imagick
```

## Step 4: Configure environment variables

Add the required environment variables to `.env`. The minimum set for production:

```env
FILE_MANAGER_API_TOKEN=your-secret-token-here
FILE_MANAGER_DISK=public
FILE_MANAGER_TMP_DISK=local
FILE_MANAGER_TMP_PREFIX=tmp/uploads
```

Complete list of available environment variables:

| Variable | Default | Purpose |
|----------|---------|---------|
| `FILE_MANAGER_DRIVER` | `local` | Active storage driver |
| `FILE_MANAGER_DISK` | `public` | Laravel filesystem disk for permanent storage |
| `FILE_MANAGER_DELETER` | (falls back to `FILE_MANAGER_DRIVER`) | Active deletion driver |
| `FILE_MANAGER_API_TOKEN` | — | Bearer token for upload API auth |
| `FILE_MANAGER_API_MIDDLEWARE` | `file-manager.api` | Middleware for API routes |
| `FILE_MANAGER_ALLOWED_ORIGINS` | — | Comma-separated CORS origins |
| `FILE_MANAGER_MAX_SIZE_DEFAULT` | `10240` | Default max upload size (KiB) |
| `FILE_MANAGER_MAX_SIZE_IMAGE` | `10240` | Max image upload size (KiB) |
| `FILE_MANAGER_MAX_SIZE_DOCUMENT` | `20480` | Max document upload size (KiB) |
| `FILE_MANAGER_TMP_DISK` | `local` | Disk for temporary uploads |
| `FILE_MANAGER_TMP_PREFIX` | `tmp/uploads` | Directory prefix in tmp disk |
| `FILE_MANAGER_TMP_LIFETIME` | `86400` | Tmp file lifetime in seconds |
| `FILE_MANAGER_RETRY_ENABLED` | `true` | Enable upload retry |
| `FILE_MANAGER_RETRY_MAX` | `3` | Max retry attempts |
| `FILE_MANAGER_RETRY_DELAY` | `100` | Delay between retries (ms) |

If `FILE_MANAGER_API_TOKEN` is not set, the upload API endpoint is open (no authentication). This is acceptable for local development but must be configured for production.

## Step 5: Set up the scheduler

The package registers a daily cleanup command (`file-manager:clean-tmp`) that deletes expired temporary uploads. Enable the Laravel scheduler:

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

## Step 6: Verify the installation

Run the included test upload command:

```bash
php artisan file-manager:test-upload
```

This uploads a dummy SVG and confirms the package is working. Expected output includes "Saved at: test/..." with a file path.

For a full test suite run:

```bash
vendor/bin/phpunit -c phpunit.xml
```

## What to do next

After successful setup:

- **Upload images programmatically**: see `file-manager-image-upload` skill for the fluent `ImageUploader` API
- **Set up the HTTP upload API**: see `file-manager-api-integration` skill for the `POST /upload` endpoint
- **Manage file lifecycle**: see `file-manager-lifecycle` skill for moving, URL generation, and deletion
