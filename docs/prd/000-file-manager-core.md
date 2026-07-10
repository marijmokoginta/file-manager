# PRD: File Manager — Core Package

| Atribut        | Nilai                                              |
| -------------- | -------------------------------------------------- |
| **ID**         | FILEMAN-000                                        |
| **Title**      | File Manager Core Package — Vision, Scope & Architecture |
| **Status**     | Live                                               |
| **Author**     | Marij Mokoginta                                    |
| **Created at** | 2025-04-01                                         |
| **Updated at** | 2026-07-10                                         |

---

## 1. Executive Summary

`m2code/file-manager` is a modular, clean-architecture Laravel package for file management — upload, process, store, serve, and delete files across multiple storage drivers and file types. It is built with Domain-Driven Design (DDD) principles, unlike standard Laravel packages that follow the framework's default MVC conventions.

The package serves two primary audiences:
1. **Client developers** — use the package via facades, fluent APIs, and HTTP endpoints in their Laravel applications
2. **Package maintainers** — extend the package with new file type handlers, storage drivers, and image processing actions

---

## 2. Vision

> Provide a single, extensible file management layer that works across any storage backend, any file type, and any input source — without tying the user to a specific architecture or framework convention.

### Core principles

- **DDD-first**: Domain layer defines contracts. Application layer implements use cases. Infrastructure layer provides concrete implementations. No business logic leaks into framework-specific code.
- **Multi-driver**: Storage, deletion, URL generation, and file moving are each swappable independently.
- **Input-agnostic**: Files arrive as `UploadedFile`, base64 data URI, or raw string — the package normalizes them transparently.
- **Progressive processing**: Images can generate zero or more variants. Each variant is optional and independently requested.
- **Tmp-first upload**: HTTP uploads land in temporary storage. The client controls when and where files become permanent. This decouples upload from business logic.

---

## 3. Scope

### 3.1 In Scope

| Feature | Status |
|---------|--------|
| Multi-driver architecture (LocalFileSaver, LocalFileDeleter, LocalFileMover, LocalFileUrlGenerator) | Done |
| File type routing (Image, SVG, PDF) via `FileTypeRouterService` | Done |
| Input abstraction (UploadedFile, base64, SVG string) via `FileInputFactory` | Done |
| Image processing pipeline (BlurHash, AVIF/WebP optimization, watermark, low quality) | Done |
| Structured variants system (`FileVariants` collection + `FileVariant` value objects) | Done |
| Fluent upload API (`ImageUploader::make()->blur()->optimize()->upload()`) | Done |
| HTTP upload endpoint (`POST /upload`) with bearer token auth | Done |
| Tmp-first storage with `FileMover` facade | Done |
| File deletion (single, batch, by variants) | Done |
| Signed URL generation | Done |
| Scheduled tmp cleanup (`file-manager:clean-tmp`) | Done |
| Retry mechanism for uploads | Done |
| Cancel token for in-flight uploads | Done |
| Laravel Boost integration (guidelines + agent skills) | Done |
| Internal MCP server for development | Done |

### 3.2 Out of Scope (Future)

| Feature | Phase |
|---------|-------|
| S3 / Cloud drivers | Phase 4 |
| Video file handler | Phase 4 |
| Chunked / resumable upload (TUS) | Phase 5 |
| Image transformation on-read (dynamic resize) | Phase 5 |
| Webhook / event system | Phase 5 |
| Per-client API token management | Phase 5 |

---

## 4. Architecture

### 4.1 Layer Map (DDD)

```
┌─────────────────────────────────────────────┐
│ HTTP Layer                                   │
│   Controllers, Middleware, FormRequests      │
│   (handles I/O, delegates to Application)    │
├─────────────────────────────────────────────┤
│ Application Layer                            │
│   Services, Handlers, ImageProcessor,        │
│   Uploader, FileInput abstraction            │
│   (orchestrates use cases)                   │
├─────────────────────────────────────────────┤
│ Domain Layer                                 │
│   Contracts (interfaces), ValueObjects,      │
│   Domain Services (FileNameGenerator)        │
│   (defines what, not how)                    │
├─────────────────────────────────────────────┤
│ Drivers / Infrastructure                     │
│   LocalFileSaver, LocalFileDeleter,          │
│   LocalFileUrlGenerator, LocalFileMover     │
│   (concrete implementations of contracts)    │
└─────────────────────────────────────────────┘
```

### 4.2 Key Design Decisions

| Decision | Rationale | ADR |
|----------|-----------|-----|
| DDD over standard Laravel structure | Domain stays framework-agnostic; drivers are swappable without touching business logic | ADR-004 |
| FileInput abstraction | Single interface for all input types; handlers don't need type-checking | ADR-002 |
| Structured variants over hardcoded fields | Extensible; new variant types don't require DTO changes | ADR-003 |
| Tmp-first upload | Decouples upload from storage decisions; client controls permanent placement | ADR-005 |
| Handler-based file routing | Open/Closed Principle; add file types without modifying core router | ADR-001 |

---

## 5. Public API Surface

### 5.1 Facades

| Facade | Binding | Methods |
|--------|---------|---------|
| `FileManager` | `file-manager` | `save()`, `delete()`, `deleteMany()`, `deleteVariants()` |
| `FileMover` | `file-mover` | `move()`, `moveAll()` |
| `FileUrl` | `file-url` | `getUrl()`, `getSignedUrl()` |

### 5.2 Fluent API

```php
ImageUploader::make()
    ->blur()
    ->optimize('avif')
    ->watermark()
    ->lowQuality()
    ->withOptions([...])
    ->upload($file, $folder);
```

### 5.3 HTTP Endpoints

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| `POST` | `/upload` | Bearer token | Upload file to tmp storage |
| `POST` | `/upload/cancel` | Bearer token | Cancel in-flight upload |
| `GET` | `/file/{disk}/{path}` | Optional signed URL | Serve file |

### 5.4 Artisan Commands

| Command | Purpose |
|---------|---------|
| `file-manager:clean-tmp` | Delete expired temporary uploads |
| `file-manager:test-upload` | Smoke-test the package |
| `file-manager:mcp` | Start MCP server for AI-assisted development |

---

## 6. Non-Functional Requirements

### NFR-1: Extensibility

- New file type handlers: implement `FileTypeHandler`, register in ServiceProvider
- New storage drivers: implement `FileSaver`/`FileDeleter`/`FileUrlGenerator`, add to config
- New image processing actions: extend `ImageAction`, register in ServiceProvider
- No core file modifications required for any extension

### NFR-2: Backward Compatibility

- Public API (facades, fluent methods) must not break within major versions
- DTO property access must remain stable
- Config keys must not be renamed without deprecation notice

### NFR-3: Testability

- All domain contracts have at least one test-covered implementation
- 82 tests covering features, unit, and integration
- Test suite runs via `vendor/bin/phpunit -c phpunit.xml`

### NFR-4: PHP & Laravel Compatibility

- PHP 8.2+ (uses readonly classes, match expressions, named arguments)
- Laravel 10, 11, 12, 13

---

## 7. Feature Roadmap

| Phase | Deliverable | Status |
|-------|-------------|--------|
| 1 | Core upload + save + URL generation | Done |
| 2 | Image processing pipeline (blurhash, low quality, watermark) | Done |
| 2.5 | Variants system, deletion, AVIF optimization | Done |
| 3 | HTTP upload API, FileMover, tmp cleanup, cancel token | Done |
| 3.5 | Base64/SVG input, Boost integration, MCP server | Done |
| 4 | S3/Firebase drivers, video handler | Planned |
| 5 | TUS upload, dynamic image resize, events | Backlog |

---

## 8. Dependencies

| Dependency | Version | Purpose |
|------------|---------|---------|
| `intervention/image` | ^3 | Image processing via Imagick driver |
| `kornrunner/blurhash` | ^1.1 | BlurHash string generation |
| `ext-imagick` | * | Required for all image processing features |
| `illuminate/*` | ^10\|^11\|^12\|^13 | Laravel framework integration |

---

## 9. Risks & Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| Imagick not installed | All image features fail | Clear error messages; detection in setup flow; fallback where possible |
| Tmp disk fills up | Uploads fail | Scheduled cleanup; configurable lifetime; monitoring recommendation in docs |
| Breaking API change | Client upgrades break | Semantic versioning; deprecation notices before removal |
| SVG/PDF security (XXE, malicious content) | Server compromise | SVG saved as string (no XML parsing); PDF handled as binary blob |

---

## 10. Success Metrics

- [x] Developer can install and upload a file in under 5 minutes (measured by README Quick Start)
- [x] 82 tests passing with zero skipped
- [x] All public API methods have docblock type hints
- [ ] S3 driver implemented and tested (Phase 4)
- [ ] Community contributions accepted via standard PR flow (Phase 4+)
