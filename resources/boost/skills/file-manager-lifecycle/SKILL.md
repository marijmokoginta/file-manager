---
name: file-manager-lifecycle
description: Manages the complete file lifecycle after upload: moving files from temporary to permanent storage, generating public and signed URLs, and deleting files individually, in batch, or by variants. Use when user asks to "move uploaded file", "promote file to permanent", "generate file URL", "create signed URL", "delete file", "remove uploaded file", "clean up file variants", or "manage file lifecycle". Covers FileMover, FileUrl, and FileManager facades.
---

# File Lifecycle Management

## Overview

After a file is uploaded (either via the `ImageUploader` fluent API or the HTTP `POST /upload` endpoint), it needs lifecycle management:

1. **Move** from temporary to permanent storage
2. **Generate URLs** for serving the file
3. **Delete** files when no longer needed

Three facades handle these operations: `FileMover`, `FileUrl`, and `FileManager`.

## FileMover: Moving Files

Files uploaded via the HTTP API are in temporary storage. Use `FileMover` to promote them to permanent storage. Source files are deleted from tmp after a successful move.

### Move a single file

```php
use M2code\FileManager\Facades\FileMover;

$permanentPath = FileMover::move(
    tmpPath: 'tmp/uploads/550e8400/photo.png',
    destinationFolder: 'avatars',
);
// Returns: "avatars/photo.png"
```

### Move all files in a tmp folder

When the upload generates multiple variants, use `moveAll()` to move the entire UUID folder:

```php
$results = FileMover::moveAll(
    tmpFolder: 'tmp/uploads/550e8400',
    destinationFolder: 'avatars',
);
// Returns: [
//     'tmp/uploads/550e8400/original.png' => 'avatars/original.png',
//     'tmp/uploads/550e8400/optimized.avif' => 'avatars/optimized.avif',
// ]
```

### Move to a specific disk

Both methods accept an optional third parameter for the target filesystem disk:

```php
$path = FileMover::move(
    tmpPath: 'tmp/uploads/photo.png',
    destinationFolder: 'avatars',
    disk: 's3',
);
```

### Error handling

- If the source file is not found, a `RuntimeException` is thrown with the message matching "Source file not found".
- After a successful move, the source file is deleted from the tmp disk.

### Important behaviors

- Filename is preserved during move
- Each move is atomic per file
- `moveAll()` returns a map of original paths to new paths

## FileUrl: Generating URLs

Generate public and signed URLs for files stored on the configured disk.

### Public URL

```php
use M2code\FileManager\Facades\FileUrl;

$url = FileUrl::getUrl('avatars/photo.png');
// Returns: "http://example.com/file/public/avatars/photo.png"
```

The URL format depends on the configured driver. For the local driver, it generates a route to `GET /file/{disk}/{path}` with the path base64-encoded.

### Signed URL (temporary access)

Generate a time-limited signed URL for private files:

```php
use M2code\FileManager\Facades\FileUrl;

$signedUrl = FileUrl::getSignedUrl(
    path: 'avatars/photo.png',
    expiresAt: now()->addMinutes(5),
);
```

After expiration, accessing the signed URL returns HTTP 403.

### Typical URL generation flow

```php
// After uploading and moving:
$path = FileMover::move('tmp/uploads/uuid/photo.png', 'avatars');
$url = FileUrl::getUrl($path);
$signedUrl = FileUrl::getSignedUrl($path, now()->addHours(24));

// Store path in database, return URLs to frontend
$model->update([
    'avatar_path' => $path,        // permanent reference
    'avatar_url' => $url,          // for immediate display
]);
```

## FileManager: Deleting Files

Delete files individually, in batch, or by variants collection.

```php
use M2code\FileManager\Facades\FileManager;
```

### Delete a single file

```php
$success = FileManager::delete('avatars/old-photo.png');
// Returns: bool (true on success, false if file doesn't exist)
```

Deletion is idempotent — deleting a non-existent file returns `false` without throwing an error.

### Delete multiple files

```php
$results = FileManager::deleteMany([
    'avatars/a.jpg',
    'avatars/b.jpg',
    'avatars/c.jpg',
]);
// Returns: ['avatars/a.jpg' => true, 'avatars/b.jpg' => true, 'avatars/c.jpg' => false]
```

Returns a map of path to boolean (true = deleted, false = not found or failed).

### Delete all variants of an upload

The most common deletion pattern — removes all variants generated from a single upload:

```php
// Upload
$result = ImageUploader::make()
    ->blur()
    ->optimize('avif')
    ->upload($file, 'avatars');

// Later, delete everything
FileManager::deleteVariants($result->variants);
```

This internally calls `$variants->paths()` to get all variant paths, then `deleteMany()`.

### Complete deletion example

```php
// When deleting a user's avatar:
$user = User::find($id);

if ($user->avatar_path) {
    // Delete the file
    FileManager::delete($user->avatar_path);

    // If you also stored variant paths, delete those too
    if ($user->avatar_variants) {
        FileManager::deleteMany($user->avatar_variants);
    }
}

$user->update([
    'avatar_path' => null,
    'avatar_variants' => null,
]);
```

## Complete Lifecycle Example

```php
use M2code\FileManager\Application\Uploader\ImageUploader;
use M2code\FileManager\Facades\FileManager;
use M2code\FileManager\Facades\FileMover;
use M2code\FileManager\Facades\FileUrl;

// 1. Upload (programmatic)
$result = ImageUploader::make()
    ->blur()
    ->optimize('avif')
    ->upload($request->file('photo'), 'profiles');

// 2. Store paths and metadata
$profile->update([
    'avatar_path' => $result->variants->get('optimized')?->path
        ?? $result->variants->get('original')?->path,
    'avatar_blurhash' => $result->blurhash,
]);

// 3. Generate URLs for display
$url = FileUrl::getUrl($profile->avatar_path);
$signedUrl = FileUrl::getSignedUrl($profile->avatar_path, now()->addDay());

// 4. When replacing the avatar, delete old files first
if ($profile->getOriginal('avatar_path')) {
    FileManager::deleteVariants($result->variants); // delete previous upload's variants
}
```

## Scheduled Cleanup

Files in temporary storage that are never moved to permanent storage are automatically deleted. The `file-manager:clean-tmp` command runs daily via Laravel's scheduler.

```bash
# Manual run
php artisan file-manager:clean-tmp

# Development (continuous)
php artisan schedule:work
```

Tmp file lifetime is configured via:

```env
FILE_MANAGER_TMP_LIFETIME=86400  # seconds (24 hours)
```

## Common Mistakes

1. **Not moving files after HTTP upload** — The most common mistake. Files in tmp are auto-deleted. Always call `FileMover::move()` after receiving the upload response.

2. **Storing URLs instead of paths** — URLs can change (domain, disk, driver). Store the `path` in the database and generate URLs dynamically using `FileUrl::getUrl()`.

3. **Not deleting old files when replacing** — When updating a file field, delete the old file before or after saving the new one to avoid orphan files.

4. **Calling `moveAll()` with the wrong folder** — The `tmpFolder` parameter should be the full UUID folder from the upload response, not just the prefix.
