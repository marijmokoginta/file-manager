# File Input Types

## Overview

The package uses a `FileInput` abstraction layer to normalize different input formats. The `FileInputFactory` automatically detects the input type and creates the appropriate implementation. User code never needs to instantiate these classes directly.

## Supported Input Types

### 1. UploadedFile (Laravel)

Standard Laravel file upload from an HTTP request.

```php
$file = $request->file('photo');
$result = ImageUploader::make()->upload($file, 'photos');
```

- **Detection**: Instance of `Illuminate\Http\UploadedFile`
- **Internal class**: `UploadedFileInput`
- **Processing**: Full image pipeline (raster) or SVG bypass

### 2. Base64 Data URI

Base64-encoded file content as a string. Must be a valid data URI with MIME type prefix.

```php
// Raster image: full processing supported
$base64Png = 'data:image/png;base64,iVBORw0KGgo...';
$result = ImageUploader::make()->blur()->upload($base64Png, 'photos');

// SVG via base64: processing skipped
$base64Svg = 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0...';
$result = ImageUploader::make()->upload($base64Svg, 'icons');
```

- **Detection**: String starting with `data:`
- **Internal class**: `Base64FileInput`
- **Validation**: Invalid base64 content or malformed data URI throws an error
- **Format**: `data:{mime_type};base64,{encoded_content}`

Supported MIME types for base64:
- `image/png`, `image/jpeg`, `image/webp`, `image/gif`, `image/avif`
- `image/svg+xml`
- `application/pdf`

### 3. Raw SVG String

Raw SVG markup as a string, without base64 encoding.

```php
$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64"><rect width="64" height="64"/></svg>';
$result = ImageUploader::make()->upload($svg, 'icons');
```

- **Detection**: String containing `<svg` tag, detected after UploadedFile and base64 checks fail
- **Internal class**: `SvgStringFileInput`
- **Processing**: Saved as-is. No raster processing applies.
- **MIME type**: Always `image/svg+xml`
- **File extension**: `.svg`

### 4. Unsupported Input

Any input that does not match the above types throws a `RuntimeException`. The package does not accept:
- Raw binary strings (must be wrapped in a data URI)
- File paths as strings
- Streams or resources

## Input Detection Order

The factory checks input types in this order:

1. Is it an `UploadedFile` instance?
2. Is it a base64 data URI string?
3. Is it a raw SVG string?
4. Otherwise, throw `RuntimeException`

## Important Notes

- The `FileInput` abstraction is internal. Do not manually create `FileInput` subclasses in application code.
- When passing files to `ImageUploader::upload()`, always pass the raw input — the method internally calls `FileInputFactory::from()`.
- For the HTTP upload API (`POST /upload`), the `UploadFormRequest` handles base64-to-UploadedFile conversion before the request reaches the controller. This is separate from the `FileInput` abstraction.
