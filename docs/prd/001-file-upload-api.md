# PRD: File Upload API

| Atribut            | Nilai                                              |
| ------------------ | -------------------------------------------------- |
| **ID**             | FILEMAN-001                                        |
| **Title**          | File Upload API dengan Tmp Storage & Flexible Options |
| **Status**         | Draft                                              |
| **Author**         | Marij Mokoginta                                    |
| **Created at**     | 2026-07-10                                         |

---

## 1. Ringkasan Eksekutif

Package `m2code/file-manager` saat ini tidak memiliki API endpoint untuk upload file — hanya ada endpoint GET `file/{disk}/{path}` untuk serving file dan Fluent API internal (`ImageUploader::make()`) untuk upload dari kode aplikasi. Fitur ini menambahkan HTTP POST endpoint upload yang:

- Menerima file multipart atau base64
- Memvalidasi ukuran file per jenis file
- Mendeteksi jenis file otomatis
- Merutekan ke handler yang sesuai dengan opsi yang fleksibel
- Menyimpan sementara di tmp directory (bukan permanent storage)
- Mengembalikan path tmp + data spesifik jenis file

---

## 2. Tujuan & Manfaat

| #   | Tujuan |
| --- | ------ |
| 1   | Menyediakan API upload yang bisa diakses via HTTP oleh frontend atau mobile app |
| 2   | Memisahkan proses upload (tmp) dari penyimpanan permanen, memberikan kontrol penuh ke client |
| 3   | Memberikan fleksibilitas opsi per jenis file (blurhash, optimize, watermark, dll) |
| 4   | Menyediakan validasi ukuran file yang konfigurabel |

---

## 3. Ruang Lingkup

### 3.1 In Scope (Phase 1 — MVP)

- **POST** `/upload` endpoint dengan multipart form-data
- Validasi ukuran file per kategori jenis file
- Deteksi jenis file otomatis (image, pdf, svg, etc.)
- Routing ke handler berbasis jenis file
- Opsi fleksibel via request body
- Default opsi untuk image (optimize=true, blurhash=true)
- Simpan ke tmp disk dengan UUID folder per request
- Response JSON berisi `tmp_path`, `tmp_folder`, `mime_type`, `size`, `type`, dan `extra` data spesifik jenis file
- Security middleware (token-based auth)
- Config validasi + tmp + default options dengan env override
- Register otomatis API route oleh ServiceProvider

### 3.2 In Scope (Phase 2 — Infrastructure)

- `FileMover` facade untuk pindahkan file dari tmp ke permanent
- `CleanTmpUploadsCommand` + auto-register scheduler untuk hapus tmp daily
- Retry mechanism pada upload

### 3.3 Out of Scope (Phase 3+)

- Cancel token mechanism
- Chunked/partial upload (TUS protocol)
- Frontend client SDK
- Thumbnail generation (existing `GenerateLowQualityAction` bisa digunakan, bukan fitur baru)
- Multi-file upload dalam satu request (batch)

---

## 4. Functional Requirements

### FR-1: Endpoint Upload

| Field       | Value                                 |
| ----------- | ------------------------------------- |
| Method      | POST                                  |
| Path        | `/upload`                             |
| Content-Type| `multipart/form-data` atau `application/json` |
| Auth        | `Authorization: Bearer <token>`       |
| Success     | 201 Created                           |
| Failure     | 422 Validation Error / 401 Unauthorized |

### FR-2: Request Body

| Field     | Type     | Required | Description |
| --------- | -------- | -------- | ----------- |
| `file`    | File     | Yes      | File yang diupload |
| `options` | Object   | No       | Opsi spesifik jenis file (key-value, boolean atau string) |

**Contoh request (multipart):**
```
POST /upload
Content-Type: multipart/form-data

file: (binary)
options[blurhash]: false
options[optimize]: true
```

**Contoh request (JSON base64):**
```json
{
    "file": "data:image/png;base64,iVBORw0KGgo...",
    "options": {
        "blurhash": false,
        "optimize": true
    }
}
```

### FR-3: Response Body (Generic)

```json
{
    "type": "image",
    "tmp_path": "tmp/uploads/550e8400-e29b-41d4-a716-446655440000/abc123.png",
    "tmp_folder": "tmp/uploads/550e8400-e29b-41d4-a716-446655440000",
    "original_name": "foto-profil.png",
    "size": 204800,
    "mime_type": "image/png",
    "extra": {}
}
```

### FR-4: Response Body (Image-Specific `extra`)

```json
{
    "type": "image",
    "tmp_path": "tmp/uploads/.../original.png",
    "tmp_folder": "tmp/uploads/...",
    "original_name": "foto.png",
    "size": 204800,
    "mime_type": "image/png",
    "extra": {
        "blurhash": "L6Df8^_4D%M{%MD%M{D%",
        "optimized_path": "tmp/uploads/.../optimized.avif",
        "watermark_path": "tmp/uploads/.../watermark.jpg",
        "low_quality_path": "tmp/uploads/.../low_quality.jpg"
    }
}
```

### FR-5: Validasi File Size

Ukuran file divalidasi terhadap konfigurasi `file-manager.validation.max_file_size`:

| Kategori   | MIME Pattern           | Default (KiB) | Env Variable |
| ---------- | ---------------------- | ------------- | ------------ |
| `default`  | (fallback)             | 10240         | `FILE_MANAGER_MAX_SIZE_DEFAULT` |
| `image`    | `image/*` (kecuali svg) | 10240         | `FILE_MANAGER_MAX_SIZE_IMAGE` |
| `document` | `application/pdf`, dll | 20480         | `FILE_MANAGER_MAX_SIZE_DOCUMENT` |

File yang melebihi batas akan ditolak dengan 422 dan pesan error yang jelas.

### FR-6: Deteksi Jenis File & Routing

| MIME Type          | Handler            | Category    |
| ------------------ | ------------------ | ----------- |
| `image/*` (bukan svg) | `ImageFileHandler`  | `image`     |
| `image/svg+xml`    | `SvgFileHandler`    | `svg`       |
| `application/pdf`  | `PdfFileHandler`    | `document`  |
| Lainnya            | (tidak didukung)    | -           |

Handler hanya memproses file yang `canHandle()` return `true`. Jika tidak ada handler yang cocok, return 400 dengan pesan "Unsupported file type".

### FR-7: Opsi per Jenis File

| Jenis File | Opsi yang Didukung | Default Value | Deskripsi |
| ---------- | ------------------ | ------------- | --------- |
| image      | `blurhash`         | `true`        | Generate BlurHash string |
| image      | `optimize`         | `true`        | Generate AVIF/WEBP optimized variant |
| image      | `watermark`        | `false`       | Apply watermark |
| image      | `low_quality`      | `false`       | Generate low quality placeholder |
| svg        | (tidak ada)        | -             | - |
| pdf        | (tidak ada)        | -             | - |

Opsi yang dikirim client tapi tidak didukung handler akan **diabaikan** (tidak menyebabkan error). Nilai opsi diambil dari: request body > default handler > false.

### FR-8: Tmp Storage

- Semua file disimpan ke tmp disk (default Laravel `local` disk, konfigurabel via `FILE_MANAGER_TMP_DISK`)
- Setiap request upload membuat subfolder dengan nama UUID v4 di bawah prefix `FILE_MANAGER_TMP_PREFIX`
- Format: `{prefix}/{uuid}/{filename}.{ext}`
- File tidak disimpan ke permanent storage melalui API ini

### FR-9: FileMover Facade

```php
use M2code\FileManager\Facades\FileMover;

// Pindahkan satu file
$permanentPath = FileMover::move($tmpPath, 'avatars');

// Pindahkan semua file dalam satu tmp folder
$paths = FileMover::moveAll($tmpFolder, 'avatars');
```

### FR-10: Scheduler Cleanup

- Command: `file-manager:clean-tmp`
- Menghapus semua file di tmp disk yang umurnya > `FILE_MANAGER_TMP_LIFETIME` (default 86400 detik)
- Auto-register di scheduler via ServiceProvider
- Client hanya perlu menjalankan `php artisan schedule:run` via cron

---

## 5. Non-Functional Requirements

### NFR-1: Security

- API harus dilindungi middleware otentikasi
- Token di-konfigurasi via `FILE_MANAGER_API_TOKEN` di `.env`
- Middleware mendukung multiple token (comma-separated)
- Request tanpa token atau token invalid: 401
- Origin validation opsional via CORS middleware (`FILE_MANAGER_ALLOWED_ORIGINS`)

### NFR-2: Performance

- File size validation terjadi sebelum file disimpan ke disk
- Jika MIME type tidak didukung, reject early sebelum upload selesai
- Target response time: < 1 detik untuk file < 5MB (tidak termasuk network latency)

### NFR-3: Reliability

- Retry mechanism dengan konfigurasi `FILE_MANAGER_RETRY_ENABLED`, `FILE_MANAGER_RETRY_MAX`, `FILE_MANAGER_RETRY_DELAY`
- Partial upload: jika varian gagal, original tetap disimpan (graceful degradation)

### NFR-4: Configurability

- Semua konfigurasi memiliki default value
- Semua konfigurasi bisa di-override via `.env`
- Semua konfigurasi bisa di-override via `config/file-manager.php` setelah publish
- API middleware bisa di-override client via config `FILE_MANAGER_API_MIDDLEWARE`

### NFR-5: Extensibility

- Menambah `FileTypeHandler` baru cukup dengan implement interface dan register di ServiceProvider
- Menambah driver baru (S3, etc.) tidak memerlukan perubahan di layer lain
- Opsi handler baru bisa ditambah tanpa ubah kontrak interface

---

## 6. Data Model

### 6.1 UploadRequest DTO

```
UploadRequest
├── file: mixed           # UploadedFile, base64 string, atau string mentah
└── options: array        # Key-value opsi (misal: ['blurhash' => true])
```

### 6.2 UploadResponse DTO

```
UploadResponse
├── type: string          # Kategori: 'image', 'svg', 'document'
├── tmp_path: string      # Path lengkap file original di tmp disk
├── tmp_folder: string    # UUID folder yang berisi semua file request ini
├── original_name: ?string# Nama file asli dari client
├── size: int             # Ukuran file dalam bytes
├── mime_type: string     # MIME type terdeteksi
└── extra: array          # Data spesifik jenis file (blurhash, variant paths, dll)
```

### 6.3 Modifikasi FileTypeHandler Interface

```php
interface FileTypeHandler
{
    public function canHandle(FileInput $input): bool;
    public function handleSave(FileInput $input, string $folder): FileOperationResult;

    // === Metode baru untuk upload API ===
    public function handleUpload(FileInput $input, string $folder, array $options): UploadResponse;
    public function supportedOptions(): array;
    public function defaultOptions(): array;
}
```

---

## 7. Konfigurasi

### 7.1 Konfigurasi Baru di `config/file-manager.php`

```php
'validation' => [
    'max_file_size' => [
        'default'  => env('FILE_MANAGER_MAX_SIZE_DEFAULT', 10240),
        'image'    => env('FILE_MANAGER_MAX_SIZE_IMAGE', 10240),
        'document' => env('FILE_MANAGER_MAX_SIZE_DOCUMENT', 20480),
    ],
],

'tmp' => [
    'disk'     => env('FILE_MANAGER_TMP_DISK', 'local'),
    'prefix'   => env('FILE_MANAGER_TMP_PREFIX', 'tmp/uploads'),
    'lifetime' => env('FILE_MANAGER_TMP_LIFETIME', 86400),
],

'api' => [
    'token'    => env('FILE_MANAGER_API_TOKEN'),
    'allowed_origins' => explode(',', env('FILE_MANAGER_ALLOWED_ORIGINS', '')),
    'middleware' => env('FILE_MANAGER_API_MIDDLEWARE', 'file-manager.api'),
],

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
```

### 7.2 Env Variables Summary

| Variable | Default | Deskripsi |
| -------- | ------- | --------- |
| `FILE_MANAGER_API_TOKEN` | - | Token untuk akses API (wajib diisi di production) |
| `FILE_MANAGER_ALLOWED_ORIGINS` | - | Origin yang diizinkan (comma-separated) |
| `FILE_MANAGER_API_MIDDLEWARE` | `file-manager.api` | Middleware class/alias untuk API |
| `FILE_MANAGER_MAX_SIZE_DEFAULT` | 10240 | Max size default (KiB) |
| `FILE_MANAGER_MAX_SIZE_IMAGE` | 10240 | Max size image (KiB) |
| `FILE_MANAGER_MAX_SIZE_DOCUMENT` | 20480 | Max size document (KiB) |
| `FILE_MANAGER_TMP_DISK` | `local` | Disk untuk tmp storage |
| `FILE_MANAGER_TMP_PREFIX` | `tmp/uploads` | Prefix folder tmp |
| `FILE_MANAGER_TMP_LIFETIME` | 86400 | Umur file tmp (detik) |
| `FILE_MANAGER_RETRY_ENABLED` | `true` | Enable retry mechanism |
| `FILE_MANAGER_RETRY_MAX` | 3 | Maksimum retry attempt |
| `FILE_MANAGER_RETRY_DELAY` | 100 | Delay antar retry (ms) |

---

## 8. Acceptance Criteria

### AC-1: Upload Image Sukses

- **Given** client mengirim POST `/upload` dengan file gambar PNG 2MB dan header `Authorization: Bearer <valid_token>` tanpa options
- **When** API memproses request
- **Then** response 201 dengan:
  - `type` = `"image"`
  - `tmp_path` berisi path file original di tmp
  - `extra.blurhash` berisi string BlurHash valid
  - `extra.optimized_path` berisi path varian teroptimasi
  - `extra.watermark_path` tidak ada (default false)
  - File original dan optimized ada di tmp disk

### AC-2: Upload dengan Opsi Dinonaktifkan

- **Given** client mengirim POST `/upload` dengan `options[blurhash]=false` dan `options[optimize]=false`
- **When** API memproses request
- **Then** response 201:
  - `extra.blurhash` = null
  - `extra.optimized_path` tidak ada
  - Hanya file original yang disimpan

### AC-3: Opsi Tidak Dikenal Diabaikan

- **Given** client mengirim POST `/upload` dengan `options[unknown_option]=true`
- **When** API memproses request
- **Then** response 201, opsi diabaikan tanpa error

### AC-4: File Size Melebihi Batas

- **Given** client mengirim file gambar 15MB ketika max size image = 10240 KiB
- **When** API memvalidasi request
- **Then** response 422 dengan pesan error "File size exceeds the maximum allowed size of 10240 KiB for image files"

### AC-5: Unauthorized

- **Given** client mengirim POST `/upload` tanpa token atau token invalid
- **When** API memproses request
- **Then** response 401 Unauthorized

### AC-6: Unsupported File Type

- **Given** client mengirim file dengan MIME `application/zip`
- **When** API memproses request
- **Then** response 400 dengan pesan "Unsupported file type"

### AC-7: FileMover Pindahkan File

- **Given** file sudah ada di tmp path `tmp/uploads/{uuid}/file.png`
- **When** client memanggil `FileMover::move('tmp/uploads/{uuid}/file.png', 'avatars')`
- **Then** file dipindahkan ke permanent disk `avatars/file.png`, file tmp dihapus, fungsi return path permanen

### AC-8: Scheduler Hapus Tmp

- **Given** ada file tmp yang umurnya > `FILE_MANAGER_TMP_LIFETIME`
- **When** `php artisan schedule:run` dijalankan (atau `scheduler:work`)
- **Then** file-file tersebut dihapus dari tmp disk

---

## 9. Dependensi

| Dependensi | Status | Keterangan |
| ---------- | ------ | ---------- |
| `FileTypeRouterService` | Ada | Perlu modifikasi agar mendukung `handleUpload()` options |
| `FileInputFactory` | Ada | Tidak perlu perubahan |
| `LocalFileSaver` | Ada | Perlu mendukung tmp disk terpisah |
| `ImageUploader` | Ada | Perlu mendukung `withOptions()` dari array |
| `ImageProcessor` | Ada | Tidak perlu perubahan |
| `FileNameGenerator` | Ada | Tidak perlu perubahan |
| `FileDeleter` | Ada | Digunakan oleh CleanTmpCommand |

---

## 10. Risk & Mitigasi

| Risk | Dampak | Mitigasi |
| ---- | ------ | -------- |
| Tmp disk penuh | Upload gagal, pengguna tidak bisa upload | Scheduler daily cleanup + monitoring disk usage |
| Token bocor | Akses tidak sah ke API | Rotate token via env variable, support multiple token untuk zero-downtime rotation |
| File malicious (SVG XXE, dll) | Keamanan server | SVG handler saat ini sudah aman (simpan sebagai string), tambah sanitasi tambahan jika perlu |
| Race condition pada tmp UUID | Folder bentrok | UUID v4 collision probability sangat rendah (~10^-37), aman |
| Varian processing gagal | Client tidak mendapat semua varian | Graceful degradation: original tetap disimpan, varian yang gagal tidak disimpan |
