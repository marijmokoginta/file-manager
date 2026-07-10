# API Response Formats

## Generic Structure

All upload responses share this base structure:

```json
{
    "type": "string",
    "tmp_path": "string",
    "tmp_folder": "string",
    "original_name": "string|null",
    "size": "integer",
    "mime_type": "string",
    "extra": "object"
}
```

| Field | Type | Always Present | Description |
|-------|------|---------------|-------------|
| `type` | string | Yes | File category: `image`, `svg`, or `document` |
| `tmp_path` | string | Yes | Full path to the original file in tmp storage |
| `tmp_folder` | string | Yes | UUID folder containing all files from this request |
| `original_name` | string\|null | Yes | Original filename from the client |
| `size` | integer | Yes | File size in bytes |
| `mime_type` | string | Yes | Detected MIME type |
| `extra` | object | Yes | Type-specific metadata (may be empty `{}`) |

## Image Response (`type: "image"`)

```json
{
    "type": "image",
    "tmp_path": "tmp/uploads/550e8400-e29b-41d4-a716-446655440000/abc123.png",
    "tmp_folder": "tmp/uploads/550e8400-e29b-41d4-a716-446655440000",
    "original_name": "photo.png",
    "size": 204800,
    "mime_type": "image/png",
    "extra": {
        "blurhash": "L6Df8^_4D%M{%MD%M{D%",
        "optimized_path": "tmp/uploads/550e8400.../abc123.avif",
        "watermark_path": "tmp/uploads/550e8400.../abc123_watermark.jpg",
        "low_quality_path": "tmp/uploads/550e8400.../abc123_low.jpg"
    }
}
```

### extra fields (image)

| Field | Present When | Type | Description |
|-------|-------------|------|-------------|
| `blurhash` | `blurhash` option is enabled (default: true) | string | BlurHash string for placeholder UI |
| `optimized_path` | `optimize` option is enabled (default: true) | string | Path to AVIF/WebP optimized variant in tmp |
| `watermark_path` | `watermark` option is enabled (default: false) | string | Path to watermarked variant in tmp |
| `low_quality_path` | `low_quality` option is enabled (default: false) | string | Path to low quality JPEG variant in tmp |

**Important**: Disabled options do NOT appear in `extra`. Always check for key existence:

```php
$blurhash = $response['extra']['blurhash'] ?? null;
$optimizedPath = $response['extra']['optimized_path'] ?? null;
```

### Default options (configurable)

Defaults are defined in `config/file-manager.php`:

```php
'upload' => [
    'default_options' => [
        'image' => [
            'optimize'    => true,   // AVIF/WebP optimized variant
            'blurhash'    => true,   // BlurHash generation
            'watermark'   => false,  // Watermark overlay
            'low_quality' => false,  // Low quality placeholder
        ],
    ],
],
```

## SVG Response (`type: "svg"`)

```json
{
    "type": "svg",
    "tmp_path": "tmp/uploads/550e8400-e29b-41d4-a716-446655440000/icon.svg",
    "tmp_folder": "tmp/uploads/550e8400-e29b-41d4-a716-446655440000",
    "original_name": "icon.svg",
    "size": 512,
    "mime_type": "image/svg+xml",
    "extra": {}
}
```

### extra fields (svg)

`extra` is always an empty object `{}`. SVG does not support any processing options. Options sent in the request are silently ignored.

## Document Response (`type: "document"`)

```json
{
    "type": "document",
    "tmp_path": "tmp/uploads/550e8400-e29b-41d4-a716-446655440000/report.pdf",
    "tmp_folder": "tmp/uploads/550e8400-e29b-41d4-a716-446655440000",
    "original_name": "report.pdf",
    "size": 1048576,
    "mime_type": "application/pdf",
    "extra": {}
}
```

### extra fields (document)

`extra` is always an empty object `{}`. PDF does not support any processing options.

## File Size Validation

Maximum file sizes are validated per category. Exceeding the limit returns HTTP 422.

| Category | Env Variable | Default (KiB) |
|----------|-------------|----------------|
| default | `FILE_MANAGER_MAX_SIZE_DEFAULT` | 10240 |
| image | `FILE_MANAGER_MAX_SIZE_IMAGE` | 10240 |
| document | `FILE_MANAGER_MAX_SIZE_DOCUMENT` | 20480 |

Error response format:

```json
{
    "message": "File size (15.2 MiB) exceeds the maximum allowed size of 10240 KiB for image files."
}
```

## Error Response Format

All error responses follow this structure:

```json
{
    "message": "Human-readable error description."
}
```

HTTP status codes:

| Code | Meaning |
|------|---------|
| 201 | Upload successful |
| 401 | Missing or invalid API token |
| 422 | Validation error (size exceeded, unsupported type, bad base64) |
| 499 | Upload cancelled via cancel token |
