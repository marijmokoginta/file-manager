---
name: file-manager-api-integration
description: Integrates the m2code/file-manager HTTP upload API for frontend and mobile applications. Use when user asks to "add file upload API", "create upload endpoint", "integrate file upload", "upload file from React", "upload file from mobile app", "file upload HTTP API", "receive files via API", or "set up file upload for frontend". Covers POST /upload endpoint, bearer token authentication, multipart and base64 JSON request formats, cancel token mechanism, and the full tmp-to-permanent file lifecycle via FileMover.
---

# HTTP Upload API Integration

## Overview

The package registers a `POST /upload` endpoint for receiving files over HTTP. Files are stored in a **temporary location** (tmp disk). The client must use `FileMover` to promote files to permanent storage. This two-step flow gives the client full control over where and when files are permanently stored.

See [references/endpoint-reference.md](references/endpoint-reference.md) for complete request/response specifications.
See [references/api-response-formats.md](references/api-response-formats.md) for response schemas per file type.

## Prerequisites

1. Package must be installed and configured (`file-manager-setup` skill)
2. `FILE_MANAGER_API_TOKEN` must be set in `.env` for production (optional for local dev)
3. The scheduler must be running to clean expired tmp files

## Authentication

All API endpoints require a `Bearer` token in the `Authorization` header:

```
Authorization: Bearer your-secret-token
```

Configure the token in `.env`:

```env
FILE_MANAGER_API_TOKEN=your-secret-token
```

Multiple tokens are supported for zero-downtime key rotation:

```env
FILE_MANAGER_API_TOKEN=v1-token,v2-token
```

If `FILE_MANAGER_API_TOKEN` is empty or not set, the endpoint is **open** (no authentication required). This is acceptable for local development only.

## Core Flow: Upload → Move → URL

The complete file lifecycle through the HTTP API:

```
1. Client sends file → POST /upload
2. Server saves to tmp disk, returns tmp_path + tmp_folder
3. Client validates/processes as needed
4. Client calls FileMover::move() to promote to permanent storage
5. Client calls FileUrl::getUrl() to get the public URL
```

**Critical**: Files left in tmp storage beyond `FILE_MANAGER_TMP_LIFETIME` (default 24 hours) are automatically deleted by the scheduler. Always move files to permanent storage promptly.

## POST /upload

### Multipart Request (file upload)

```
POST /upload
Authorization: Bearer your-secret-token
Content-Type: multipart/form-data

file: (binary)
options[blurhash]: false
options[watermark]: true
cancel_token: optional-tracking-id
```

### JSON Request (base64)

```json
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
| `file` | File or String | Yes | The file (multipart) or base64 data URI (JSON) |
| `options` | Object | No | Processing options per file type (see below) |
| `cancel_token` | String | No | Unique ID for cancelling an in-flight upload |

### Options by File Type

| File Type | Option | Type | Default | Description |
|-----------|--------|------|---------|-------------|
| image | `blurhash` | bool | `true` | Generate BlurHash string |
| image | `optimize` | bool | `true` | Generate AVIF/WebP optimized variant |
| image | `watermark` | bool | `false` | Apply watermark overlay |
| image | `low_quality` | bool | `false` | Generate low quality placeholder |
| svg | — | — | — | No options supported |
| document | — | — | — | No options supported |

Options not recognized by the target file type handler are **silently ignored**. Unknown options do not cause errors.

Default values can be changed in `config/file-manager.php` under `upload.default_options.image`.

### Response (201 Created)

Generic response structure:

```json
{
    "type": "image",
    "tmp_path": "tmp/uploads/550e8400-e29b-41d4-a716-446655440000/original.png",
    "tmp_folder": "tmp/uploads/550e8400-e29b-41d4-a716-446655440000",
    "original_name": "photo.png",
    "size": 204800,
    "mime_type": "image/png",
    "extra": {
        "blurhash": "L6Df8^_4D%M{%MD%M{D%",
        "optimized_path": "tmp/uploads/550e8400.../optimized.avif"
    }
}
```

See [references/api-response-formats.md](references/api-response-formats.md) for per-type response details.

### Error Responses

| Status | Condition | Message |
|--------|-----------|---------|
| 401 | Missing or invalid token | "Unauthorized. Invalid or missing API token." |
| 422 | File exceeds max size | "File size (X MiB) exceeds the maximum allowed size of Y KiB for Z files." |
| 422 | Unsupported file type | "Unsupported file type: application/zip" |
| 422 | Invalid base64 format | "Invalid base64 data URI format." |
| 499 | Upload cancelled via cancel token | "Upload cancelled: token" |

## Cancel Token

Uploads can be cancelled mid-flight. This is useful for aborting large uploads when the user navigates away.

### Flow

1. Generate a unique token (UUID) on the client
2. Include it as `cancel_token` in the upload request body, or as `X-Cancel-Token` header
3. To cancel, call the cancel endpoint with the same token

### POST /upload/cancel

```
POST /upload/cancel
Authorization: Bearer your-secret-token

{
    "cancel_token": "my-upload-uuid"
}
```

Response:

```json
{
    "message": "Upload cancelled.",
    "cancel_token": "my-upload-uuid"
}
```

After cancellation:
- Subsequent uploads with the same token receive HTTP 499
- Tokens expire after 10 minutes
- Different tokens do not interfere with each other

## FileMover: Promoting to Permanent Storage

After receiving the upload response, move files from tmp to permanent storage:

```php
use M2code\FileManager\Facades\FileMover;

// Move a single file
$permanentPath = FileMover::move(
    tmpPath: 'tmp/uploads/550e8400/photo.png',
    destinationFolder: 'avatars',
);

// Move all files in a tmp folder (all variants at once)
$results = FileMover::moveAll(
    tmpFolder: 'tmp/uploads/550e8400',
    destinationFolder: 'avatars',
);
// Returns: ['tmp/uploads/550e8400/original.png' => 'avatars/original.png', ...]
```

Important behaviors:
- Source files are **deleted** from tmp after successful move
- Filename is preserved during move
- Throws `RuntimeException` if source file not found
- Optional third parameter specifies a different target disk

## File URL Generation

After moving to permanent storage, generate URLs:

```php
use M2code\FileManager\Facades\FileUrl;

$url = FileUrl::getUrl('avatars/photo.png');
$signedUrl = FileUrl::getSignedUrl('avatars/photo.png', now()->addMinutes(5));
```

## Integration Example: Laravel Controller

```php
use M2code\FileManager\Application\UploadService;
use M2code\FileManager\Facades\FileMover;
use M2code\FileManager\Facades\FileUrl;

class AvatarController extends Controller
{
    public function store(Request $request, UploadService $uploadService)
    {
        // 1. Upload
        $response = $uploadService->upload(
            $request->file('avatar'),
            $request->input('options', [])
        );

        // 2. Move to permanent
        $path = FileMover::move(
            $response->tmpPath,
            'avatars/' . $request->user()->id
        );

        // 3. Generate URL
        $url = FileUrl::getUrl($path);

        return response()->json([
            'path' => $path,
            'url' => $url,
            'blurhash' => $response->extra['blurhash'] ?? null,
        ], 201);
    }
}
```

## Integration Example: Direct HTTP Call from Frontend

```javascript
// Multipart upload
const form = new FormData();
form.append('file', fileInput.files[0]);
form.append('options[blurhash]', 'false');
form.append('options[watermark]', 'true');

const response = await fetch('/upload', {
    method: 'POST',
    headers: { 'Authorization': 'Bearer YOUR_TOKEN' },
    body: form,
});

const data = await response.json();
// data.tmp_path, data.tmp_folder, data.extra
```

## Common Mistakes

1. **Not moving files to permanent storage** — Files in tmp are auto-deleted after `FILE_MANAGER_TMP_LIFETIME` (default 24h). Always call `FileMover::move()`.

2. **Hardcoding the token in client code** — The token should be in environment variables, never committed to version control. For SPAs, proxy through your backend.

3. **Not handling 499 (cancelled upload)** — If using cancel tokens, handle the 499 status code in client error handling.

4. **Assuming `extra` fields always exist** — Check for key existence before accessing. Disabled options do not appear in `extra`.

5. **Not setting up the scheduler** — Without `schedule:run`, tmp files accumulate indefinitely, consuming disk space.
