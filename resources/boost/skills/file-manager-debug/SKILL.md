---
name: file-manager-debug
description: Diagnoses and resolves common issues with the m2code/file-manager package. Use when user says "file upload not working", "debug file manager", "image processing failed", "Imagick not found", "upload returns 401", "upload returns 422", "blurhash null", "variant not generated", "tmp files not cleaned", or "file manager error". Covers Imagick setup verification, authentication issues, file size validation, disk configuration, scheduler troubleshooting, retry mechanism, and variant generation failures.
---

# File Manager Debugging

## Overview

Systematic diagnosis for common file-manager package issues. Work through each section in order. Most issues fall into one of these categories: Imagick not installed, authentication misconfiguration, disk/permissions problems, or missing scheduler setup.

## Diagnostic Checklist

Run through these checks before deep-diving into any single issue.

### 1. Verify Imagick Installation

Image processing failures are almost always due to missing or broken Imagick.

```bash
# Check if Imagick is loaded
php -m | grep imagick

# Expected output: "imagick"
# If empty: Imagick is not installed or not enabled in php.ini
```

If Imagick is missing, follow the platform-specific installation in the `file-manager-setup` skill or README.

```bash
# Ubuntu/Debian quick fix
sudo apt update && sudo apt install imagemagick php-imagick
sudo service php-fpm restart
```

If Imagick is installed but features still fail:

```bash
# Check AVIF support (required for ->optimize('avif'))
php -r "var_dump(Imagick::queryFormats());" | grep -i avif

# Check WebP support (fallback for AVIF)
php -r "var_dump(Imagick::queryFormats());" | grep -i webp
```

If AVIF is not listed, the `optimize('avif')` call falls back to WebP. If neither is listed, the optimized variant is silently skipped.

### 2. Verify Package Installation

```bash
# Confirm the package is installed
composer show m2code/file-manager

# Confirm the service provider is discovered
php artisan package:discover
```

### 3. Verify Configuration is Published

```bash
# Check if config exists
ls config/file-manager.php

# If missing, publish it
php artisan vendor:publish --tag=config --provider="M2code\FileManager\FileManagerServiceProvider"
```

### 4. Verify Environment Variables

```bash
# Check all file-manager env values
php artisan tinker --execute="dump(config('file-manager'));"
```

Common misconfigurations:
- `FILE_MANAGER_DISK` pointing to a non-existent filesystem disk
- `FILE_MANAGER_API_TOKEN` empty in production (open endpoint)
- `FILE_MANAGER_TMP_DISK` not configured

### 5. Verify Scheduler is Running

```bash
# Check if tmp files are accumulating
php artisan file-manager:clean-tmp --verbose

# If the command works but tmp files keep accumulating:
# The Laravel scheduler cron is not running.
# Add to crontab:
# * * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

## Common Issues and Solutions

### Issue: "401 Unauthorized" on POST /upload

**Symptoms**: All upload API requests return 401.

**Diagnosis**:
```bash
# Check if token is configured
grep FILE_MANAGER_API_TOKEN .env
```

**Causes and fixes**:

1. **Token not set in .env** — Set `FILE_MANAGER_API_TOKEN=your-token` and clear config cache:
   ```bash
   php artisan config:clear
   ```

2. **Token mismatch** — Verify the exact token string. No extra whitespace. The `Authorization` header must be exactly `Bearer your-token`.

3. **Config cached with old value** — Clear config cache:
   ```bash
   php artisan config:clear
   ```

4. **Token is comma-separated** — If using multiple tokens, the entire string is split by comma. Ensure no spaces around commas unless intended as part of the token.

### Issue: "422 Unprocessable Entity" — File Size Exceeded

**Symptoms**: Upload returns 422 with message about file size.

**Diagnosis**:
```bash
php artisan tinker --execute="dump(config('file-manager.validation.max_file_size'));"
```

**Fixes**:
- Increase the limit in `.env`:
  ```env
  FILE_MANAGER_MAX_SIZE_IMAGE=20480   # 20 MiB
  FILE_MANAGER_MAX_SIZE_DOCUMENT=51200 # 50 MiB
  ```
- Clear config cache and retry.

### Issue: "422 Unprocessable Entity" — Unsupported File Type

**Symptoms**: Upload returns 422 with "Unsupported file type".

**Causes**:
- File type not supported by any handler (currently: image/*, image/svg+xml, application/pdf)
- MIME type detection failed

**Check supported types**:
- Images: `image/png`, `image/jpeg`, `image/webp`, `image/gif`, `image/avif`
- Vector: `image/svg+xml`
- Documents: `application/pdf`

Files like `.zip`, `.docx`, `.mp4`, `.mov` are not supported.

### Issue: BlurHash is null

**Symptoms**: `$result->blurhash` is null even though `->blur()` was called.

**Causes**:
1. File is SVG — SVG files silently skip blurhash generation. This is expected behavior.
2. Imagick is not installed — blurhash requires Imagick.
3. Imagick cannot read the image format.

**Verify**:
```php
// Check if the file is SVG
$result->variants->get('original')?->path; // ends with .svg?
```

### Issue: Optimized variant is null

**Symptoms**: `$result->variants->get('optimized')` is null after calling `->optimize('avif')`.

**Causes**:
1. File is SVG — processing is skipped.
2. Imagick does not support AVIF or WebP — the variant is silently skipped.
3. `optimize` flag was not actually enabled (check the fluent chain).

**Verify AVIF/WebP support**:
```bash
php -r "var_dump(in_array('AVIF', Imagick::queryFormats()));"
php -r "var_dump(in_array('WEBP', Imagick::queryFormats()));"
```

If both return `false`, the optimized variant will always be null.

### Issue: Files not appearing in expected location

**Symptoms**: Upload succeeds but files are not in `storage/app/public/`.

**Causes**:
1. Files are in tmp disk, not permanent storage. Use `FileMover::move()`.
2. The `FILE_MANAGER_DISK` disk is different from what you're checking.
3. Symbolic link not created for `public` disk:
   ```bash
   php artisan storage:link
   ```

### Issue: File not found at URL

**Symptoms**: `FileUrl::getUrl()` returns a URL but accessing it returns 404.

**Causes**:
1. The path in the database is a tmp path, not a permanent path.
2. The file was deleted by the scheduler (exceeded `FILE_MANAGER_TMP_LIFETIME`).
3. The disk in the URL route doesn't match the disk where the file is stored.

### Issue: Upload retry not working

**Symptoms**: Upload fails immediately without retrying.

**Check**:
```bash
php artisan tinker --execute="dump(config('file-manager.upload.retry'));"
```

Verify:
- `enabled` is `true` (or `FILE_MANAGER_RETRY_ENABLED=true`)
- `max_attempts` is > 1
- `delay` is reasonable (in milliseconds)

If retry is disabled, transient disk I/O failures will not be retried.

## Verification Command

Run the built-in test to confirm basic package functionality:

```bash
php artisan file-manager:test-upload
```

Expected output: "Saved at: test/..." with a file path. If this fails, the package is not correctly installed or configured.

## Test Suite

Run the full test suite to identify issues:

```bash
vendor/bin/phpunit -c phpunit.xml
```

82 tests should pass. Any failures indicate a configuration or environment issue.
