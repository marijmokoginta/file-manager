# ADR-001: Arsitektur File Upload API

| Atribut            | Nilai                                              |
| ------------------ | -------------------------------------------------- |
| **ID**             | ADR-001                                            |
| **Title**          | Arsitektur File Upload API — Tmp-First, Handler-Based, Config-Driven |
| **Status**         | Proposed                                           |
| **Date**           | 2026-07-10                                         |
| **Deciders**       | Marij Mokoginta                                    |
| **Related PRD**    | [PRD FILEMAN-001](../prd/001-file-upload-api.md)   |

---

## 1. Context

Package `m2code/file-manager` saat ini hanya memiliki Fluent API internal (`ImageUploader::make()->upload()`) untuk upload file dari kode aplikasi dan satu endpoint GET `file/{disk}/{path}` untuk serving file. Tidak ada HTTP endpoint untuk menerima upload dari client eksternal (frontend SPA, mobile app).

Kebutuhan yang muncul:

1. **API upload via HTTP** yang bisa diakses oleh frontend dan mobile app
2. **Pemisahan temporary storage dan permanent storage** — client ingin kontrol penuh kapan dan di mana file disimpan permanen
3. **Fleksibilitas opsi per jenis file** — client bisa enable/disable blurhash, optimize, watermark via request
4. **Validasi ukuran file yang konfigurabel**
5. **Keamanan endpoint** — tidak bisa diakses sembarang client
6. **Auto-cleanup tmp** — file sementara yang tidak dipindahkan akan dihapus otomatis

---

## 2. Decision

### 2.1 Tmp-First Storage Approach

**Keputusan**: File hasil upload API disimpan ke temporary disk (`FILE_MANAGER_TMP_DISK`) dengan struktur folder UUID per-request. Client bertanggung jawab memanggil `FileMover` facade untuk memindahkan file ke permanent storage.

**Folder structure**:
```
{tmp_prefix}/
  {request_uuid}/
    original.png
    optimized.avif
    low_quality.jpg
```

**Alasan**:
- Decouple upload dari business logic penyimpanan
- Client bisa menentukan lokasi permanen sesuai konteks bisnis (berdasarkan user ID, entity type, dll)
- Client bisa memproses file lebih lanjut sebelum menyimpan permanen (validasi konten, virus scan, dll)
- Jika upload gagal di tengah jalan, tidak ada orphan file di permanent storage
- Scheduler cleanup mencegah akumulasi file tmp yang tidak dipindahkan

**Dampak**:
- Perlu disk/tempat penyimpanan terpisah untuk tmp (bisa sama dengan `local` disk, beda prefix)
- Client wajib implement `FileMover::move()` untuk memindahkan file — ada risk file tidak dipindahkan
- Ukuran tmp storage perlu dimonitor

---

### 2.2 Handler-Based Routing dengan Flexible Options

**Keputusan**: Memperluas `FileTypeHandler` interface dengan tiga metode baru:
- `handleUpload(FileInput, string $folder, array $options): UploadResponse`
- `supportedOptions(): array`
- `defaultOptions(): array`

Setiap handler mendefinisikan sendiri opsi yang didukung dan default value-nya. Opsi yang tidak dikenal dalam request **diabaikan** tanpa error — ini menghindari breaking change saat ada opsi baru.

**Alasan**:
- Open/Closed Principle: menambah file type baru = buat handler baru + register, tidak perlu ubah core
- Setiap handler tahu opsi apa yang relevan untuk jenis file-nya
- Client bisa mengirim opsi apapun tanpa takut error — responsibilitas validasi ada di handler

**Contoh flow**:
```
Request: { file: image.png, options: { blurhash: false, optimize: true, unknown: true } }
  → FileInputFactory::from() → MIME: image/png
  → ImageFileHandler::canHandle() → true
  → ImageFileHandler::defaultOptions() → { blurhash: true, optimize: true, watermark: false, low_quality: false }
  → Merge: request options override defaults → { blurhash: false, optimize: true, watermark: false, low_quality: false }
  → Opsi 'unknown' diabaikan (tidak ada di supportedOptions)
  → handleUpload() → ImageUploader::withOptions(merged)
  → UploadResponse dengan extra: { blurhash: null, optimized_path: '/tmp/.../opt.avif' }
```

**Dampak**:
- `ImageUploader` perlu metode `withOptions(array)` sebagai alternatif dari fluent chaining
- `UploadService` bertanggung jawab untuk merge options (request > default)
- Menambah handler baru = cukup implement 3 metode baru, handler lama tetap kompatibel

---

### 2.3 UUID Folder per Request

**Keputusan**: Setiap upload request membuat folder baru dengan nama UUID v4 di bawah prefix tmp.

**Alasan**:
- Isolasi: file dari request berbeda tidak bercampur, memudahkan cleanup per request
- Atomic move: `FileMover::moveAll(uuid_folder, permanent_folder)` bisa memindahkan semua file request sekaligus
- Collision-safe: UUID v4 collision probability sangat rendah (10^-37)
- Tidak perlu tracking database untuk mapping request → files

**Alternatif yang ditolak**:
- Folder berbasis timestamp: risk collision pada high-concurrency
- Folder berbasis user ID: tidak applicable karena API upload terjadi sebelum user diketahui (misal di registration flow)

---

### 2.4 Token-Based API Auth

**Keputusan**: Middleware custom `file-manager.api` yang memvalidasi `Authorization: Bearer <token>` terhadap config `FILE_MANAGER_API_TOKEN`.

**Alasan**:
- **Simpel**: Client hanya perlu menyimpan satu token, tidak perlu session/cookie
- **SPA + Mobile ready**: Bearer token bekerja di semua platform tanpa konfigurasi tambahan
- **Configurable**: Token di-set via env, bisa dirotasi tanpa deploy kode
- **Multiple token support**: `FILE_MANAGER_API_TOKEN` bisa berisi comma-separated token untuk rotasi zero-downtime

**Alternatif yang ditolak**:

| Alternatif | Alasan ditolak |
|---|---|
| `auth:sanctum` | Memerlukan session/cookie — tidak cocok untuk mobile app dan SPA terpisah |
| Signed URL | Tidak cocok untuk POST request dengan file besar; expire-based tidak fleksibel |
| OAuth2 / JWT | Over-engineering untuk use case internal service-to-service |
| IP whitelist | Tidak cocok untuk mobile app dengan IP dinamis |
| No auth (public) | Risk abuse (anonymous upload, storage exhaustion attack) |

**Dampak**:
- Middleware custom perlu dibuat dan di-register
- Client harus menyimpan token di environment/secret manager
- API endpoint ini tidak cocok untuk public-facing tanpa proxy/gateway tambahan — jika perlu public, tambahkan rate limiting

---

### 2.5 Config-Driven Defaults dengan Env Override

**Keputusan**: Semua nilai konfigurasi didefinisikan dengan default value di `config/file-manager.php` dan bisa di-override via `.env` variable.

**Alasan**:
- Konvensi Laravel: `config/service.php` + `env()` adalah pattern standar
- Client bisa mengubah default value tanpa menyentuh kode package
- Default value yang masuk akal mengurangi beban konfigurasi untuk use case umum
- Config di-publish ke client, sehingga client bisa custom lebih lanjut

---

### 2.6 Auto-Register Scheduler di ServiceProvider

**Keputusan**: Scheduler `file-manager:clean-tmp` di-register otomatis di `FileManagerServiceProvider::boot()` via `$this->callAfterResolving(Schedule::class, ...)`.

**Alasan**:
- Zero-config untuk client: setelah install package, scheduler langsung aktif
- Client hanya perlu setup cronjob `php artisan schedule:run` seperti biasa
- Tidak perlu dokumentasi setup tambahan untuk scheduler

**Dampak**:
- Scheduler berjalan di semua environment (termasuk development) kecuali client menonaktifkannya
- Jika client sudah punya banyak scheduled task, ini bisa menambah overhead kecil — mitigasi: bisa dinonaktifkan via config

---

### 2.7 Retry Mechanism pada Upload

**Keputusan**: Upload logic dibungkus dalam retry loop dengan konfigurasi `FILE_MANAGER_RETRY_ENABLED`, `FILE_MANAGER_RETRY_MAX`, `FILE_MANAGER_RETRY_DELAY`.

**Alasan**:
- Transient failure pada disk I/O atau processing bisa di-recover tanpa client perlu re-upload
- Retry limit mencegah infinite loop
- Bisa dinonaktifkan via config

**Dampak**:
- Response time bisa lebih lambat jika terjadi retry
- Client harus meng-handle kemungkinan timeout pada sisi mereka

---

## 3. Consequences

### Positive

- Client dapat kontrol penuh atas penyimpanan permanen file
- File type baru dapat ditambahkan tanpa mengubah core
- Opsi handler fleksibel dan backward-compatible
- Tidak ada orphan file di permanent storage
- Security sederhana namun efektif untuk internal/machine-to-machine communication
- Konfigurasi fully override-able tanpa menyentuh kode package

### Negative

- Client wajib implement `FileMover::move()` — jika lupa, file tetap di tmp dan akhirnya dihapus scheduler
- Tidak ada tracking database untuk tmp files — recovery dari crash bergantung pada filesystem
- Bearer token shared: jika satu client bocor, semua client terdampak (mitigasi: rotasi token + future: per-client token)
- Tidak ada built-in rate limiting — client perlu menambahkan sendiri jika diperlukan

### Trade-offs

- **Simplicity vs. Security granularity**: Token-based auth simpel tapi kurang granular dibanding OAuth. Diterima karena use case saat ini internal/controlled client.
- **Tmp-first vs. Direct-to-permanent**: Tmp-first menambah step untuk client, tapi memberikan fleksibilitas maksimal. Untuk use case simple, client bisa langsung `FileMover::move()` setelah upload.

---

## 4. Compliance

### Terhadap PRD FILEMAN-001

| FR | Terpenuhi | Catatan |
| -- | --------- | ------- |
| FR-1: Endpoint Upload | Ya | POST `/upload` dengan token auth |
| FR-2: Request Body | Ya | Multipart + JSON base64, options object |
| FR-3/4: Response Body | Ya | Generic + image-specific `extra` |
| FR-5: Validasi File Size | Ya | Config-driven dengan env override |
| FR-6: Deteksi & Routing | Ya | Via `FileTypeRouterService` + handler |
| FR-7: Opsi per Jenis File | Ya | Default + override + unknown ignore |
| FR-8: Tmp Storage | Ya | UUID folder per request |
| FR-9: FileMover Facade | Ya | Interface + Local implementation |
| FR-10: Scheduler Cleanup | Ya | Auto-register di ServiceProvider |

### Terhadap NFR

| NFR | Status | Catatan |
| --- | ------ | ------- |
| Security | Ya | Token middleware |
| Performance | Design | Validasi early, reject unsupported type early |
| Reliability | Ya | Retry mechanism |
| Configurability | Ya | All configs have env override |
| Extensibility | Ya | Open-closed handler pattern |

---

## 5. Implementation Phases

| Phase | Deliverables | Est. Effort |
| ----- | ------------ | ----------- |
| Phase 1: Core API | UploadService, UploadController, UploadRequest/Response DTO, modifikasi FileTypeHandler + ImageFileHandler, ImageUploader::withOptions(), config validasi + tmp, auth middleware, routes | 3-5 hari |
| Phase 2: Infrastructure | FileMover interface + LocalFileMover + Facade, CleanTmpUploadsCommand, auto-register scheduler, retry mechanism | 1-2 hari |
| Phase 3: Advanced | Cancel token, chunked/partial upload, per-client token management | TBD |

---

## 6. Open Questions

1. **Apakah perlu support multi-file upload dalam satu request (array of files)?** — Saat ini out of scope, bisa ditambah sebagai batch endpoint terpisah.
2. **Apakah perlu varian image yang di-cache (regenerate kalau tidak ada)?** — Saat ini setiap upload selalu generate ulang, caching bisa ditambah di layer `ImageProcessor`.
3. **Apakah perlu webhook/event setelah file dipindahkan ke permanent?** — Bisa ditambahkan sebagai Laravel Event di FileMover.
4. **Apakah file di tmp yang sudah dipindahkan harus dihapus otomatis oleh FileMover atau manual oleh client?** — Diputuskan: FileMover otomatis menghapus tmp setelah move sukses.
