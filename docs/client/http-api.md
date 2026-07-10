# HTTP Upload API

The package registers HTTP endpoints for receiving files from frontend and mobile applications. Files are stored in **temporary storage**; use `FileMover` to promote them to permanent storage.

## Endpoints

| Method | Path | Name |
|--------|------|------|
| `POST` | `/upload` | `file-manager.upload` |
| `POST` | `/upload/cancel` | `file-manager.upload.cancel` |
| `GET` | `/file/{disk}/{path}` | `file-manager.serve` |

## Authentication

The upload endpoints are protected by bearer token authentication. Configure in `.env`:

```env
FILE_MANAGER_API_TOKEN=your-secret-token
```

Multiple tokens (comma-separated) for zero-downtime rotation:

```env
FILE_MANAGER_API_TOKEN=v1-token,v2-token
```

Requests without a valid token receive `401 Unauthorized`. If no token is configured, the endpoint is open.

The middleware can be changed via:

```env
FILE_MANAGER_API_MIDDLEWARE=auth:sanctum
```

## POST /upload

### Multipart Request

```
POST /upload
Authorization: Bearer your-secret-token
Content-Type: multipart/form-data

file: (binary)
options[blurhash]: false
options[watermark]: true
cancel_token: optional-tracking-id
```

### JSON Request (Base64)

```json
POST /upload
Authorization: Bearer your-secret-token
Content-Type: application/json

{
    "file": "data:image/png;base64,iVBORw0KGgo...",
    "options": {
        "blurhash": false,
        "watermark": true
    },
    "cancel_token": "optional-tracking-id"
}
```

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `file` | File or String | Yes | File (multipart) or base64 data URI (JSON) |
| `options` | Object | No | Processing options per file type |
| `cancel_token` | String | No | ID for cancelling an in-flight upload; also sent via `X-Cancel-Token` header |

### Options by File Type

| Type | Option | Type | Default | Description |
|------|--------|------|---------|-------------|
| image | `blurhash` | bool | `true` | Generate BlurHash |
| image | `optimize` | bool | `true` | AVIF/WebP optimized variant |
| image | `watermark` | bool | `false` | Watermark overlay |
| image | `low_quality` | bool | `false` | Low quality placeholder |
| svg | — | — | — | No options supported |
| document | — | — | — | No options supported |

Unknown options are silently ignored. Defaults can be changed in `config/file-manager.php` under `upload.default_options`.

### Response (201 Created)

Generic structure:

```json
{
    "type": "image",
    "tmp_path": "tmp/uploads/{uuid}/original.png",
    "tmp_folder": "tmp/uploads/{uuid}",
    "original_name": "photo.png",
    "size": 204800,
    "mime_type": "image/png",
    "extra": {
        "blurhash": "L6Df8^_4D%M{%MD%M{D%",
        "optimized_path": "tmp/uploads/{uuid}/optimized.avif"
    }
}
```

| Field | Always Present | Description |
|-------|---------------|-------------|
| `type` | Yes | `image`, `svg`, or `document` |
| `tmp_path` | Yes | Path to original file in tmp storage |
| `tmp_folder` | Yes | UUID folder containing all files from this request |
| `original_name` | Yes | Original filename (null if unknown) |
| `size` | Yes | File size in bytes |
| `mime_type` | Yes | Detected MIME type |
| `extra` | Yes | Type-specific data (empty object `{}` for svg/document) |

**Image-specific `extra` fields** (only present when the option is enabled):

| Field | Option | Description |
|-------|--------|-------------|
| `blurhash` | `blurhash` | BlurHash string |
| `optimized_path` | `optimize` | Path to AVIF/WebP variant in tmp |
| `watermark_path` | `watermark` | Path to watermarked variant in tmp |
| `low_quality_path` | `low_quality` | Path to low quality JPEG in tmp |

### Error Responses

| Status | Condition |
|--------|-----------|
| 401 | Missing or invalid token |
| 422 | File exceeds max size, unsupported type, or invalid base64 |
| 499 | Upload cancelled via cancel token |

## File Size Validation

Maximum sizes are configured per category in KiB:

```env
FILE_MANAGER_MAX_SIZE_DEFAULT=10240   # 10 MiB
FILE_MANAGER_MAX_SIZE_IMAGE=10240     # 10 MiB
FILE_MANAGER_MAX_SIZE_DOCUMENT=20480  # 20 MiB
```

## Cancel Token

```bash
# Cancel an in-flight upload
POST /upload/cancel
Authorization: Bearer your-secret-token

{
    "cancel_token": "my-upload-uuid"
}
```

- Include `cancel_token` in the upload request body, or send via `X-Cancel-Token` header
- After cancellation, subsequent uploads with that token receive HTTP 499
- Tokens expire after 10 minutes
- Different tokens do not interfere

## Configuration Reference

Full `config/file-manager.php` after publishing:

```php
return [
    // Storage Drivers
    'default_driver'  => env('FILE_MANAGER_DRIVER', 'local'),
    'drivers' => [
        'local' => [
            'class' => M2code\FileManager\Drivers\Local\LocalFileSaver::class,
            'disk'  => env('FILE_MANAGER_DISK', 'public'),
        ],
    ],
    'default_deleter' => env('FILE_MANAGER_DELETER', env('FILE_MANAGER_DRIVER', 'local')),
    'deleters' => [
        'local' => [
            'class' => M2code\FileManager\Drivers\Local\LocalFileDeleter::class,
            'disk'  => env('FILE_MANAGER_DISK', 'public'),
        ],
    ],
    'default_url_generator' => env('FILE_MANAGER_DRIVER', 'local'),
    'url_generators' => [
        'local' => [
            'class' => M2code\FileManager\Infrastructure\UrlGenerator\LocalFileUrlGenerator::class,
            'disk'  => env('FILE_MANAGER_DISK', 'public'),
        ],
    ],

    // File Size Validation (KiB)
    'validation' => [
        'max_file_size' => [
            'default'  => env('FILE_MANAGER_MAX_SIZE_DEFAULT', 10240),
            'image'    => env('FILE_MANAGER_MAX_SIZE_IMAGE', 10240),
            'document' => env('FILE_MANAGER_MAX_SIZE_DOCUMENT', 20480),
        ],
    ],

    // Temporary Storage
    'tmp' => [
        'disk'     => env('FILE_MANAGER_TMP_DISK', 'local'),
        'prefix'   => env('FILE_MANAGER_TMP_PREFIX', 'tmp/uploads'),
        'lifetime' => env('FILE_MANAGER_TMP_LIFETIME', 86400),
    ],

    // API Security
    'api' => [
        'token'           => env('FILE_MANAGER_API_TOKEN'),
        'allowed_origins' => explode(',', env('FILE_MANAGER_ALLOWED_ORIGINS', '')),
        'middleware'      => env('FILE_MANAGER_API_MIDDLEWARE', 'file-manager.api'),
    ],

    // Upload Defaults & Retry
    'upload' => [
        'default_options' => [
            'image' => [
                'optimize'    => true,
                'blurhash'    => true,
                'watermark'   => false,
                'low_quality' => false,
            ],
        ],
        'retry' => [
            'enabled'      => env('FILE_MANAGER_RETRY_ENABLED', true),
            'max_attempts' => env('FILE_MANAGER_RETRY_MAX', 3),
            'delay'        => env('FILE_MANAGER_RETRY_DELAY', 100),
        ],
    ],
];
```

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `FILE_MANAGER_DRIVER` | `local` | Active storage driver |
| `FILE_MANAGER_DISK` | `public` | Laravel filesystem disk for permanent storage |
| `FILE_MANAGER_DELETER` | (falls back to `FILE_MANAGER_DRIVER`) | Active deletion driver |
| `FILE_MANAGER_API_TOKEN` | — | Bearer token(s) for upload API (comma-separated) |
| `FILE_MANAGER_API_MIDDLEWARE` | `file-manager.api` | Middleware alias for API routes |
| `FILE_MANAGER_ALLOWED_ORIGINS` | — | Comma-separated CORS origin whitelist |
| `FILE_MANAGER_MAX_SIZE_DEFAULT` | `10240` | Default max upload size (KiB) |
| `FILE_MANAGER_MAX_SIZE_IMAGE` | `10240` | Max image upload size (KiB) |
| `FILE_MANAGER_MAX_SIZE_DOCUMENT` | `20480` | Max document upload size (KiB) |
| `FILE_MANAGER_TMP_DISK` | `local` | Disk for temporary uploads |
| `FILE_MANAGER_TMP_PREFIX` | `tmp/uploads` | Directory prefix in tmp disk |
| `FILE_MANAGER_TMP_LIFETIME` | `86400` | Tmp file lifetime in seconds |
| `FILE_MANAGER_RETRY_ENABLED` | `true` | Enable upload retry |
| `FILE_MANAGER_RETRY_MAX` | `3` | Max retry attempts |
| `FILE_MANAGER_RETRY_DELAY` | `100` | Delay between retries (ms) |

## Artisan Commands

| Command | Description |
|---------|-------------|
| `file-manager:clean-tmp` | Delete expired temporary uploads |
| `file-manager:test-upload` | Smoke-test the package |
