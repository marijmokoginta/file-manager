# ADR-003: Structured Variants System

| Atribut            | Nilai                                              |
| ------------------ | -------------------------------------------------- |
| **ID**             | ADR-003                                            |
| **Title**          | Structured Variants System via FileVariants Collection |
| **Status**         | Accepted                                           |
| **Date**           | 2026-03-05                                         |
| **Deciders**       | Marij Mokoginta                                    |
| **Related PRD**    | [PRD FILEMAN-000](../prd/000-file-manager-core.md) |

---

## 1. Context

The initial implementation of image processing returned variant paths as hardcoded properties on the result DTO:

```php
// BEFORE: hardcoded fields
$result->path;           // original
$result->lowQualityPath; // low quality
// watermark? not supported without DTO change
```

This approach has fundamental problems:
1. **Adding a variant requires changing the DTO** — every new variant type means a new property, a new `__get` magic method case, and potentially breaking existing property access.
2. **No standard way to iterate variants** — clients must know all variant names at compile time.
3. **No collection semantics** — can't map, filter, or get paths as a batch.
4. **Null ambiguity** — is a variant `null` because it wasn't requested, or because processing failed?

---

## 2. Decision

Replace hardcoded properties with a `FileVariants` collection of `FileVariant` value objects.

### Value Object

```php
readonly class FileVariant
{
    public function __construct(
        public string $type,  // 'original', 'optimized', 'low_quality', 'watermark'
        public string $path,
    ) {}
}
```

### Collection

```php
class FileVariants
{
    public function add(FileVariant $variant): void;
    public function get(string $type): ?FileVariant;
    public function all(): array;            // FileVariant[]
    public function paths(): array;          // string[]
}
```

### Result DTO

```php
readonly class ImageUploadResult
{
    public function __construct(
        public FileVariants $variants,
        public ?string $blurhash = null,
    ) {}
}
```

Backward compatibility is maintained via `__get` magic:

```php
public function __get(string $name): ?string
{
    return match ($name) {
        'path'           => $this->variantPath('original'),
        'lowQualityPath' => $this->variantPath('low_quality'),
        'watermarkPath'  => $this->variantPath('watermark'),
        'optimizedPath'  => $this->variantPath('optimized'),
        default          => null,
    };
}
```

### Variant Type Convention

All variant types use `snake_case` keys:
- `original` — always present
- `optimized` — AVIF/WebP version
- `low_quality` — JPEG placeholder
- `watermark` — watermarked copy

The key `optimized` is used regardless of whether the actual format is AVIF or WebP. This keeps the key format-agnostic.

---

## 3. Consequences

### Positive

- **Open for extension**: Adding a new variant type (e.g., `thumbnail`, `social_preview`) requires no DTO changes — just add it to the collection in the processor.
- **Iterable**: `$result->variants->paths()` gives all paths for batch deletion.
- **Self-documenting**: The `type` key makes it clear what each variant represents.
- **Backward compatible**: Old code using `$result->path` continues to work via `__get`.
- **Deletion integration**: `FileManager::deleteVariants($result->variants)` is a one-liner.

### Negative

- **Slightly more verbose**: `$result->variants->get('original')?->path` vs `$result->path`. Mitigated by null-safe operator and backward-compatible accessors.
- **No compile-time variant name checking**: `$result->variants->get('optimized')` is a string key. Typos are runtime errors. Mitigated by convention constants in tests.

### Trade-offs

- **Collection vs. typed DTO**: A typed DTO with named properties provides compile-time safety. A collection provides runtime flexibility. Chosen: collection for extensibility, with backward-compatible property access for ergonomics.
- **Always-valid vs. partial**: Each request may generate a different subset of variants. The collection naturally represents this. A typed DTO with nullable fields would also work but is harder to extend.

---

## 4. Alternatives Considered

| Alternative | Rejected Because |
|-------------|-----------------|
| Keep hardcoded fields, add new ones as needed | Violates OCP; requires DTO changes for every new variant |
| Array of `[$type => $path]` | No type safety; no collection methods; string keys error-prone |
| Separate result classes per variant combination | Combinatorial explosion; impossible to maintain for 4+ variants |
| Enum-based variant types | Adds complexity without clear benefit over string keys with convention |

---

## 5. Compliance

- `ImageUploadResult` uses `FileVariants` as its primary variant container
- All handlers that produce variants use the `FileVariants` collection
- `ImageProcessor::process()` returns `FileVariants`
- Backward-compatible `__get` accessors exist for `path`, `optimizedPath`, `lowQualityPath`, `watermarkPath`
- Deletion via `FileManager::deleteVariants(FileVariants)` is tested
