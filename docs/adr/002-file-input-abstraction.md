# ADR-002: FileInput Abstraction — Input-Agnostic File Handling

| Atribut            | Nilai                                              |
| ------------------ | -------------------------------------------------- |
| **ID**             | ADR-002                                            |
| **Title**          | FileInput Abstraction for Input-Agnostic File Handling |
| **Status**         | Accepted                                           |
| **Date**           | 2026-01-20                                         |
| **Deciders**       | Marij Mokoginta                                    |
| **Related PRD**    | [PRD FILEMAN-000](../prd/000-file-manager-core.md) |

---

## 1. Context

The `m2code/file-manager` package needed to accept files from multiple sources:
1. Standard Laravel `UploadedFile` instances (from HTTP requests)
2. Base64-encoded data URIs (from API JSON payloads or frontend canvas exports)
3. Raw SVG strings (from icon generators or inline SVG editors)

Without an abstraction layer, every handler (`ImageFileHandler`, `SvgFileHandler`, `PdfFileHandler`) would need to implement its own type detection and content extraction logic. This leads to:
- Duplicated MIME detection code
- Inconsistent error handling for invalid inputs
- Tight coupling between file input format and processing logic

---

## 2. Decision

Introduce a `FileInput` interface and a `FileInputFactory` that normalizes all input types into a uniform interface *before* any handler processes the file.

### Interface

```php
interface FileInput
{
    public function getContent(): string;
    public function getMimeType(): string;
    public function getExtension(): string;
    public function getClientOriginalName(): ?string;
}
```

### Implementations

| Class | Input Type | Detection |
|-------|-----------|-----------|
| `UploadedFileInput` | `Illuminate\Http\UploadedFile` | `instanceof` |
| `Base64FileInput` | `data:{mime};base64,{content}` | String starts with `data:` |
| `SvgStringFileInput` | `<svg>...</svg>` | String contains `<svg` tag |

### Factory

```php
class FileInputFactory
{
    public static function from(mixed $input): FileInput;
}
```

Detection order:
1. `UploadedFile` instance → `UploadedFileInput`
2. Base64 data URI string → `Base64FileInput`
3. Raw SVG string → `SvgStringFileInput`
4. Otherwise → `RuntimeException`

---

## 3. Consequences

### Positive

- **Single responsibility**: Handlers process `FileInput`, not raw mixed types. No `instanceof` checks in handler logic.
- **Extensible**: Adding a new input type (e.g., stream, remote URL) only requires a new `FileInput` implementation + factory update. Handlers unchanged.
- **Consistent error handling**: Invalid inputs are rejected at the factory level, before reaching any handler.
- **Testable**: Each `FileInput` implementation can be unit-tested in isolation.

### Negative

- **Detection order matters**: The factory checks types sequentially. An SVG that happens to be a string starting with `data:` would be misdetected as base64. Mitigated by validation in `Base64FileInput`.
- **String-based SVG detection is fragile**: The check for `<svg` tag could produce false positives. Mitigated by the fact that the input would fail later in the handler pipeline if the MIME doesn't match.
- **Additional indirection**: Developers reading the code must understand the factory pattern. Documented in AGENTS.md.

### Trade-offs

- **Simplicity vs. flexibility**: A simple type-check in each handler would be fewer files. But the abstraction pays off as input types grow. Chosen: flexibility.
- **Detect vs. explicit**: An alternative would be requiring the caller to specify the input type explicitly. Chosen: auto-detection for ergonomics, accepting the minor risk of misdetection.

---

## 4. Alternatives Considered

| Alternative | Rejected Because |
|-------------|-----------------|
| No abstraction; each handler does its own type detection | Code duplication; inconsistent error messages; harder to add new types |
| Separate upload methods per type (`uploadFile()`, `uploadBase64()`, `uploadSvg()`) | Leaky abstraction; caller must know input type; violates ISP |
| Single `FileInput` class with internal `match` | Mixes concerns; harder to test individual input types |

---

## 5. Compliance

- All handlers (`ImageFileHandler`, `SvgFileHandler`, `PdfFileHandler`) accept `FileInput` exclusively
- `FileInputFactory` is the single entry point for input normalization
- No handler contains `instanceof` checks against concrete input types
- All `FileInput` implementations have unit test coverage
