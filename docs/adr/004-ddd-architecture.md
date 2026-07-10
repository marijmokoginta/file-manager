# ADR-004: DDD Architecture for a Laravel Package

| Atribut            | Nilai                                              |
| ------------------ | -------------------------------------------------- |
| **ID**             | ADR-004                                            |
| **Title**          | Domain-Driven Design Architecture over Standard Laravel Conventions |
| **Status**         | Accepted                                           |
| **Date**           | 2025-04-01                                         |
| **Deciders**       | Marij Mokoginta                                    |
| **Related PRD**    | [PRD FILEMAN-000](../prd/000-file-manager-core.md) |

---

## 1. Context

Most Laravel packages follow the framework's default conventions: controllers in `Http/Controllers`, models in `Models`, services wherever convenient. This works for application code but creates problems for a **multi-driver, extensible package**:

1. **Coupling to Laravel**: If business logic lives in controllers or depends on facades directly, the package cannot be tested without a full Laravel boot. Swapping drivers becomes invasive.
2. **Unclear boundaries**: Without explicit layers, it's unclear where to add new file types, drivers, or processing actions.
3. **Testing difficulty**: Framework-coupled code requires `orchestra/testbench` even for unit tests. Pure domain logic should be testable with plain PHPUnit.

The alternative is Domain-Driven Design (DDD) with clear layer separation.

---

## 2. Decision

Adopt DDD architecture with four explicit layers:

```
src/
├── Domain/              ← Contracts, ValueObjects, Domain Services
├── Application/         ← Use cases, Handlers, Processors, FileInput
├── Drivers/             ← Concrete implementations of Domain contracts
├── Infrastructure/      ← URL generators, external integrations
├── Http/                ← Controllers, Middleware (I/O boundary)
├── DTO/                 ← Data Transfer Objects (cross-cutting)
├── Facades/             ← Laravel facade wrappers
├── Console/             ← Artisan commands
├── Core/                ← Resolvers (driver selection logic)
├── Support/             ← Helpers, utilities
└── config/              ← Configuration
```

### Layer Rules

| Layer | Can depend on | Cannot contain |
|-------|--------------|----------------|
| **Domain** | Nothing (pure PHP) | Framework references, I/O, side effects |
| **Application** | Domain | Direct filesystem/HTTP calls (use contracts) |
| **Drivers** | Domain contracts | Business logic (only implements contracts) |
| **Infrastructure** | Domain contracts | Business logic |
| **Http** | Application, DTO | Domain logic (delegates to Application) |
| **DTO** | Domain ValueObjects | Business logic |
| **Facades** | Application services (via container) | Logic (thin proxy) |

### Dependency Flow

```
Http → Application → Domain ← Drivers
                         ← Infrastructure
```

Domain is at the center. Everything depends on it. It depends on nothing.

### Key Patterns

1. **Contracts over concretions**: `FileSaver` is an interface in Domain. `LocalFileSaver` is an implementation in Drivers. The ServiceProvider binds the contract to the configured driver.

2. **ServiceProvider as composition root**: All bindings happen in `FileManagerServiceProvider::register()`. The container resolves dependencies. No `new` keyword for services outside the provider.

3. **Resolvers for driver selection**: `FileDriverResolver`, `FileDeleterResolver`, `FileUrlGeneratorResolver` read config and return the appropriate driver instance. This is the Strategy pattern applied to infrastructure.

4. **Actions for image processing**: Each image processing variant (blurhash, optimize, watermark, low quality) is a separate Action class implementing `ImageAction`. The `ImageProcessor` orchestrates them.

---

## 3. Consequences

### Positive

- **Testable in isolation**: Domain contracts can be mocked. Application services can be tested with fake drivers. No full Laravel boot needed for most tests.
- **Swappable drivers**: Changing storage from local to S3 means implementing `FileSaver` + `FileDeleter` + `FileUrlGenerator` and updating config. Zero changes to Domain or Application.
- **Clear extension points**: "Add a new file type" = implement `FileTypeHandler`. "Add a new driver" = implement 3 contracts. No ambiguity.
- **Framework-agnostic core**: The Domain and Application layers have zero Laravel imports. They could theoretically be used in a Symfony or standalone PHP project.

### Negative

- **More files**: DDD means more directories and interfaces. A simple feature touches 3-4 files. Mitigated by clear conventions (documented in AGENTS.md).
- **Learning curve**: Developers familiar with standard Laravel packages need to understand the layer rules. Mitigated by AGENTS.md, PRDs, and ADRs.
- **Over-engineering for simple cases**: For a package with only one driver and one file type, DDD is excessive. But this package targets multi-driver, multi-type from the start.

### Trade-offs

- **File count vs. clarity**: Fewer files is not always simpler. Explicit layers prevent accidental coupling. Chosen: clarity over file count.
- **Interface overhead vs. flexibility**: Every driver needs 3 interfaces. But S3 and Firebase drivers (planned) justify the abstraction now rather than refactoring later.

---

## 4. Alternatives Considered

| Alternative | Rejected Because |
|-------------|-----------------|
| Standard Laravel package structure (no layers) | Hard to swap drivers; business logic couples to facades; testing requires full boot |
| Hexagonal / Ports & Adapters | Overkill for a package of this size; DDD with contracts achieves the same decoupling with less ceremony |
| Single service class with if/else for drivers | Violates OCP; every new driver requires modifying core service |

---

## 5. Compliance Check

- [x] Domain layer has zero `use Illuminate\*` imports
- [x] All external I/O goes through contracts defined in Domain
- [x] ServiceProvider is the only place where contracts are bound to concretions
- [x] HTTP controllers delegate to Application services, never contain business logic
- [x] Tests use fake disks and mock contracts where appropriate
