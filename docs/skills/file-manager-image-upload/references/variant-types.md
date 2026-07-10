# Image Variant Types

## Overview

The `ImageUploader` produces a `FileVariants` collection containing zero or more `FileVariant` objects. Each variant represents a processed version of the original uploaded file.

## Variant Collection API

```php
// Get a specific variant (returns FileVariant or null)
$variant = $result->variants->get('original');

// Get all variants as an array
$allVariants = $result->variants->all();

// Get all variant file paths as a flat array
$paths = $result->variants->paths();
// Returns: ['uploads/original.png', 'uploads/optimized.avif', ...]
```

## Variant Types

### original

- **Key**: `original`
- **Generated**: Always (every upload)
- **Description**: The source file saved to disk as-is. No transformation applied.
- **File extension**: Preserved from the original input.

### optimized

- **Key**: `optimized`
- **Generated**: When `->optimize('avif')` or `->optimize('webp')` is called
- **Description**: A compressed copy of the image in AVIF or WebP format. AVIF is attempted first; if the Imagick installation does not support AVIF, WebP is used as fallback. If neither is supported, this variant is skipped (no error).
- **File extension**: `.avif` or `.webp`
- **Use case**: Primary asset for display — smaller file size, modern format.
- **Important**: The key is always `optimized`, regardless of whether AVIF or WebP was generated.

### low_quality

- **Key**: `low_quality`
- **Generated**: When `->lowQuality()` is called
- **Description**: A heavily compressed JPEG version of the image, typically used as a placeholder during progressive loading (e.g., displayed while the full-resolution `optimized` variant loads).
- **File extension**: `.jpg`
- **Use case**: Blur-up or progressive image loading patterns.

### watermark

- **Key**: `watermark`
- **Generated**: When `->watermark()` is called
- **Description**: A copy of the image with a watermark overlay applied. The watermark content and position are configured separately in the image processing layer.
- **File extension**: Preserved from the original input.
- **Use case**: Public-facing images that need brand protection.

## SVG Handling

SVG files (`image/svg+xml`) only produce the `original` variant. All other variant types are silently skipped because raster image processing operations (resize, re-encode, overlay) do not apply to vector graphics.

## Deletion

To delete all variants of an upload, use:

```php
use M2code\FileManager\Facades\FileManager;

FileManager::deleteVariants($result->variants);
```

This calls `deleteMany()` internally with all variant paths. It is safe to call even if some variants are null — only existing paths are processed.

## Extending Variants

The variants system is extensible. Custom handlers can add new variant types by implementing `FileTypeHandler` and registering in `FileManagerServiceProvider`. Use consistent, snake_case keys (e.g., `thumbnail`, `social_preview`).
