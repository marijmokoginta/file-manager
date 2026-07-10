# API Endpoint Reference

## Base URL

All endpoints are registered under the application root by the `FileManagerServiceProvider`. No prefix is added automatically.

## Endpoints

### POST /upload

Upload a file to temporary storage.

**Authentication**: Bearer token (see `FILE_MANAGER_API_TOKEN`)

**Content-Type**: `multipart/form-data` or `application/json`

**Request body**:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `file` | File\|string | Yes | Binary file (multipart) or base64 data URI (JSON) |
| `options` | object | No | Processing options keyed by option name |
| `cancel_token` | string | No | Unique ID for cancellation; also accepted via `X-Cancel-Token` header |

**Response**: 201 Created — see [api-response-formats.md](api-response-formats.md)

**Errors**: 401, 422, 499

### POST /upload/cancel

Cancel an in-flight upload by its cancel token.

**Authentication**: Bearer token

**Request body**:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `cancel_token` | string | Yes | The cancel token to invalidate |

**Response**: 200 OK

```json
{
    "message": "Upload cancelled.",
    "cancel_token": "my-upload-uuid"
}
```

**Errors**: 422 (missing `cancel_token`)

### GET /file/{disk}/{path}

Serve a file from the specified disk. The `{path}` parameter is **base64-encoded**.

**Authentication**: None by default. Supports Laravel signed URL validation via `signature` and `expires` query parameters.

**Response**: The raw file content with the correct `Content-Type` header.

**Errors**: 403 (invalid/expired signed URL), 404 (file not found or invalid path encoding)

## Route Names

| Route | Name |
|-------|------|
| `GET /file/{disk}/{path}` | `file-manager.serve` |
| `POST /upload` | `file-manager.upload` |
| `POST /upload/cancel` | `file-manager.upload.cancel` |

## Middleware

The upload endpoints are protected by middleware configured via `FILE_MANAGER_API_MIDDLEWARE` (default: `file-manager.api`). This is a custom middleware registered as `file-manager.api` by the ServiceProvider.

To use a different middleware (e.g., Laravel Sanctum):

```env
FILE_MANAGER_API_MIDDLEWARE=auth:sanctum
```

Custom middleware classes are also supported:

```env
FILE_MANAGER_API_MIDDLEWARE=App\Http\Middleware\CustomAuth
```

## CORS

Allowed origins can be configured via:

```env
FILE_MANAGER_ALLOWED_ORIGINS=https://app.example.com,https://admin.example.com
```

This is handled by the middleware layer, not by the package directly. Configure your CORS middleware separately if needed.

## Rate Limiting

The package does not include built-in rate limiting. Add Laravel's throttle middleware if needed:

```php
// In a service provider or routes file
Route::post('upload', [UploadController::class, 'upload'])
    ->middleware(['file-manager.api', 'throttle:60,1']);
```
