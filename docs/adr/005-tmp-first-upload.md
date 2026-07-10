# ADR-005: Tmp-First Upload & FileMover Separation

| Atribut            | Nilai                                              |
| ------------------ | -------------------------------------------------- |
| **ID**             | ADR-005                                            |
| **Title**          | Temporary-First Upload with Explicit FileMover Promotion |
| **Status**         | Accepted                                           |
| **Date**           | 2026-06-15                                         |
| **Deciders**       | Marij Mokoginta                                    |
| **Related ADRs**   | [ADR-001](001-upload-api-architecture.md) (Upload API) |

---

## 1. Context

The HTTP upload API (`POST /upload`) receives files from external clients (frontend SPAs, mobile apps). The naive approach would save uploaded files directly to permanent storage (e.g., `public/avatars/photo.png`). This creates problems:

1. **No validation gap**: The file is permanently stored before the client can validate it.
2. **Premature path commitment**: The client hasn't decided the final location (e.g., which user folder, which entity type).
3. **Orphan files**: If the client crashes or the user abandons the flow after upload, files remain in permanent storage with no owner.
4. **Atomicity issues**: If variant generation partially fails, orphan files exist in permanent storage.

---

## 2. Decision

All files from the HTTP upload API go to **temporary storage** first. A separate `FileMover` facade promotes files to permanent storage when the client is ready.

### Tmp Storage

```
{tmp_prefix}/
  {request_uuid}/
    original.png
    optimized.avif
    low_quality.jpg
```

- Each upload request gets a **UUID v4 folder** for isolation
- Tmp files older than `FILE_MANAGER_TMP_LIFETIME` (default 24h) are auto-deleted
- Configured via `FILE_MANAGER_TMP_DISK` and `FILE_MANAGER_TMP_PREFIX`

### FileMover Facade

```php
// Move single file: tmp → permanent
$path = FileMover::move('tmp/uploads/uuid/photo.png', 'avatars');

// Move entire UUID folder (all variants)
$results = FileMover::moveAll('tmp/uploads/uuid', 'avatars');
```

Key behaviors:
- Source files are **deleted** from tmp after successful move
- Filename is preserved
- Destination folder is specified by the client
- Throws `RuntimeException` if source not found

### Upload Response

```json
{
    "tmp_path": "tmp/uploads/uuid/original.png",
    "tmp_folder": "tmp/uploads/uuid",
    ...
}
```

---

## 3. Consequences

### Positive

- **Client controls permanent placement**: The client decides the folder based on user ID, entity type, or any business logic. The upload layer is completely decoupled from storage decisions.
- **Validation window**: Client can inspect the tmp file (check dimensions, run virus scan, validate content) before promoting.
- **No orphan files in permanent storage**: If the client abandons the flow, tmp cleanup handles it. Permanent storage only contains intentionally placed files.
- **Atomic folder moves**: `moveAll()` moves all variants of a request in one operation.
- **Simpler error recovery**: If variant generation partially fails, only tmp has orphan files — and those are auto-cleaned.

### Negative

- **Extra step for the client**: The client MUST call `FileMover::move()` after upload. Forgetting this means the file is deleted by the scheduler. Mitigated by documentation and the `file-manager-api-integration` skill.
- **Tmp disk usage**: Files accumulate in tmp until moved or cleaned. Mitigated by scheduled cleanup (daily) and configurable lifetime.
- **No database tracking**: There's no database record linking upload requests to tmp folders. Recovery from a crash requires filesystem knowledge. Mitigated by UUID folders (findable by timestamp).

### Trade-offs

- **Simplicity vs. flexibility**: Direct-to-permanent would be simpler for the client. Tmp-first adds a step but provides full control. Chosen: flexibility for the package's target audience (applications with complex storage logic).
- **Immediate vs. delayed URL availability**: With tmp-first, the file URL is not available until after `FileMover::move()`. This is a feature, not a bug — it prevents linking to files that may be deleted.

---

## 4. Alternatives Considered

| Alternative | Rejected Because |
|-------------|-----------------|
| Direct-to-permanent with configurable path template | Path template is too rigid; can't handle dynamic user/entity IDs |
| Database-tracked tmp with status column | Adds infrastructure dependency; UUID folders are simpler and self-documenting |
| Two-phase commit (tmp → permanent with rollback) | Over-engineering; filesystem operations are not truly atomic anyway |
| No tmp; client uploads directly to S3 via presigned URL | Shifts complexity to client; loses server-side validation and variant generation |

---

## 5. Interactions with Other ADRs

- **ADR-001 (Upload API)**: Defines the HTTP endpoint that produces tmp files. ADR-005 defines what happens after.
- **ADR-003 (Variants)**: Variants are stored in tmp alongside the original. `moveAll()` moves them together.
- **ADR-004 (DDD)**: `FileMover` is a Domain contract. `LocalFileMover` is its driver implementation.
