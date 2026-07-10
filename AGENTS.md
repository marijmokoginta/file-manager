# m2code/file-manager — Agent Guidelines

## Overview

`m2code/file-manager` is a modular, DDD-based Laravel package for file management. It supports multi-driver storage, image processing (BlurHash, AVIF/WebP, watermark, low quality), multiple file types (image, SVG, PDF), and input-agnostic file handling (UploadedFile, base64, SVG string).

PHP 8.2+ / Laravel 10-13 / 82 tests passing.

## Architecture: DDD Layer Map

```
Domain/          ← Contracts, ValueObjects, Domain Services (pure PHP, zero framework)
Application/     ← Use cases, Handlers, ImageProcessor, Uploader, FileInput
Drivers/         ← Concrete implementations (LocalFileSaver, LocalFileDeleter, etc.)
Infrastructure/  ← URL generators, external integrations
Http/            ← Controllers, Middleware, FormRequests (I/O boundary)
DTO/             ← Data Transfer Objects
Facades/         ← Laravel facade wrappers
Console/         ← Artisan commands
Core/            ← Resolvers (driver selection logic)
Support/         ← Helpers, utilities
config/          ← Configuration
```

### Layer Rules (strict)

| Layer | Can depend on | Must NOT contain |
|-------|--------------|-----------------|
| Domain | Nothing | Framework imports, I/O, side effects |
| Application | Domain | Direct filesystem/HTTP (use Domain contracts) |
| Drivers | Domain contracts | Business logic |
| Infrastructure | Domain contracts | Business logic |
| Http | Application, DTO | Domain logic |
| DTO | Domain ValueObjects | Business logic |

### Dependency Direction

```
Http → Application → Domain ← Drivers
                         ← Infrastructure
```

Domain is at the center. Everything depends on it. It depends on nothing.

## Code Conventions

### PHP 8.2+ Features

- **Readonly classes**: Use for DTOs and ValueObjects (`ImageUploadResult`, `FileVariant`, `UploadResponse`, `FileOperationResult`)
- **Match expressions**: Prefer over switch statements
- **Named arguments**: Use when calling methods with 3+ parameters or boolean flags
- **Null-safe operator**: Required when accessing variant paths (`$result->variants->get('original')?->path`)
- **Constructor promotion**: Use for simple service dependencies

### Naming Conventions

| Element | Convention | Example |
|---------|-----------|---------|
| Interfaces | Descriptive noun or verb | `FileSaver`, `FileTypeHandler` |
| Concrete implementations | `{Driver}{Interface}` | `LocalFileSaver` |
| Actions | VerbNoun | `GenerateBlurAction` |
| Handlers | `{Type}FileHandler` | `ImageFileHandler` |
| DTOs | Noun + Result/Response | `ImageUploadResult`, `UploadResponse` |
| Resolvers | `{Concern}Resolver` | `FileDriverResolver` |
| Facades | Noun (matching service) | `FileManager`, `FileUrl` |
| Value Objects | Noun | `FileVariant`, `FileMeta` |

### ServiceProvider Binding

All bindings happen in `FileManagerServiceProvider::register()`. Rules:
- Bind contracts to resolvers (never hardcode driver class)
- Use `->bind()` for non-singleton services, `->singleton()` for services with state or caching
- Facade accessors use container bindings, never `new` directly
- Register Artisan commands in `boot()`

## Testing

### Run tests

```bash
vendor/bin/phpunit -c phpunit.xml
```

### Run a single test

```bash
vendor/bin/phpunit -c phpunit.xml --filter test_image_upload
```

### Test conventions

- **Base class**: `M2code\FileManager\tests\TestCase` extends `Orchestra\Testbench\TestCase`
- **Disk faking**: Always use `Storage::fake('public')` / `Storage::fake('tmp')` — never write to real disk
- **Image mocking**: Use `UploadedFile::fake()->image()` or `UploadedFile::fake()->createWithContent()` for SVG/PDF
- **Feature tests**: Test end-to-end flows (upload → variants → URLs → delete)
- **Unit tests**: Test isolated logic (DTO serialization, value objects)
- **Assertions**: Always assert file existence via `Storage::disk('...')->assertExists()` / `assertMissing()`
- **PHPUnit 11 attributes**: Use `#[Test]` attribute, not `@test` docblock

### Test structure

```
tests/
├── TestCase.php          ← Base test case
├── Feature/              ← Integration tests (full app boot)
│   ├── FileUploadTest.php
│   ├── FileUploadApiTest.php
│   ├── FileMoverTest.php
│   ├── FileSaverTest.php
│   ├── FileDeletionTest.php
│   ├── FileVariantsTest.php
│   ├── OptimizedVariantTest.php
│   └── CleanTmpUploadsCommandTest.php
└── Unit/                 ← Isolated unit tests
    ├── UploadServiceTest.php
    └── UploadResponseTest.php
```

## Common Development Patterns

### Adding a new file type handler

1. Create handler class in `src/Application/FileRouter/` implementing `FileTypeHandler`
2. Implement `canHandle()`, `handleSave()`, `handleUpload()`, `supportedOptions()`, `defaultOptions()`
3. Register in `FileManagerServiceProvider::register()` by adding to the `FileTypeRouterService` constructor array
4. If the handler needs custom processing, add a dedicated processor in `src/Application/`
5. Add feature tests for all MIME types the handler supports

### Adding a new storage driver

1. Implement `FileSaver` in `src/Drivers/{Name}/`
2. Implement `FileDeleter` in `src/Drivers/{Name}/`
3. Implement `FileUrlGenerator` in `src/Infrastructure/UrlGenerator/`
4. Add driver config to `config/file-manager.php` under `drivers`, `deleters`, `url_generators`
5. Update resolvers (`FileDriverResolver`, `FileDeleterResolver`, `FileUrlGeneratorResolver`) to support the new driver key
6. Add tests using the new driver config

### Adding a new image processing action

1. Create action class in `src/Application/Image/Actions/` extending `ImageAction`
2. Implement `execute($file): string` — receives file path, returns output file path
3. Register in `FileManagerServiceProvider::register()` via `->bind()`
4. Add the action to `ImageProcessor` as a new method (e.g., `withThumbnail()`)
5. Add to `ImageUploader` fluent API
6. Add tests for the action in isolation and in the full upload pipeline

### Adding a new config option

1. Add the key with a sensible default in `config/file-manager.php`
2. If configurable via env, add `env('KEY', default)` 
3. Access in code via `Config::get('file-manager.key')` — never use `env()` outside config files
4. Document in README.md under Environment Variables table
5. If it affects the API, update the relevant skill in `resources/boost/skills/`

## Documentation Files

| File | Audience | Purpose |
|------|----------|---------|
| `AGENTS.md` | AI agents | Project context for development |
| `README.md` | Client developers | Installation, usage, configuration |
| `docs/prd/` | Maintainers | Product requirements |
| `docs/adr/` | Maintainers | Architecture decisions |
| `resources/boost/guidelines/core.blade.php` | Client AI agents | Always-loaded context for Boost users |
| `resources/boost/skills/` | Client AI agents | On-demand skills for Boost users |

## Git Conventions

- **Branch naming**: `feature/`, `fix/`, `docs/`, `refactor/` prefixes
- **Commit format**: `type: description` (lowercase, no period at end)
  - `feat:` — new feature
  - `fix:` — bug fix
  - `docs:` — documentation
  - `refactor:` — code change (no feature, no fix)
  - `test:` — test addition or update
  - `chore:` — config, dependencies, tooling
- **No merge commits** in feature branches — rebase onto `develop` before PR
- **PR target**: `develop` → `staging` → `main`

## Key Files to Read First

When working on a new task, read these files for context:
1. `src/FileManagerServiceProvider.php` — all bindings, the composition root
2. `src/config/file-manager.php` — all configuration options
3. `src/Domain/Contracts/` — the contracts that define the package's capabilities
4. `README.md` — the public API surface
