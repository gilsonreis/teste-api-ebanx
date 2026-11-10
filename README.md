# EBANX Software Engineer Take-Home API

A high-performance financial ledger API built for the EBANX Software Engineering assessment. The system handles account lifecycle management, strict read-only idempotent balance inquiries, and financial transaction settlement (`deposit`, `withdraw`, and `transfer`). 

The application is deployed live on **Laravel Cloud** and has achieved **100% compliance** across all verification suites on the official **EBANX Ipkiss test runner**.

The solution was engineered using **Spec-Driven Development (SDD)** powered by the **GitHub Spec Kit** ecosystem, rich domain encapsulation on Eloquent models (preventing anemic models and avoiding redundant POPOs), decoupled persistence via inversion of control (interchangeable storage drivers), and automated CI quality gates (Laravel Pint, Larastan, and PHPUnit).

---

## 🚀 Live Environments & Endpoints

- **Production API (Laravel Cloud):** [https://teste-api-ebanx-production-dsddze.laravel.cloud](https://teste-api-ebanx-production-dsddze.laravel.cloud)
- **Interactive API Documentation (Scalar / Swagger):** [https://teste-api-ebanx-production-dsddze.laravel.cloud/docs](https://teste-api-ebanx-production-dsddze.laravel.cloud/docs)
- **OpenAPI 3.0 Specification (YAML):** [https://teste-api-ebanx-production-dsddze.laravel.cloud/docs/openapi.yaml](https://teste-api-ebanx-production-dsddze.laravel.cloud/docs/openapi.yaml)

---

## 📐 Methodology: Spec-Driven Development (SDD) via GitHub Spec Kit

Development did not start with arbitrary code writing. It was executed strictly under a **Spec-First approach** using the **GitHub Spec Kit** (`.specify/`), where all functional and technical milestones were formally specified and versioned (`SPEC-01` to `SPEC-06`) prior to coding.

The SDD lifecycle adhered to the following workflow commands:

| Command | Role in the SDD Lifecycle |
| :--- | :--- |
| **`/speckit.clarify`** | Eliminates domain ambiguities, inspects edge cases, resolves potential HTTP status conflicts, and establishes prerequisites before drafting the architecture. |
| **`/speckit.plan`** | Architects the solution structure, defines strict data contracts (DTOs), and lays out class responsibilities adhering to `agents.md`. |
| **`/speckit.tasks`** | Deconstructs the engineering strategy into ordered, atomic units of work (unit tests, domain entities, repository drivers, use cases, and controllers). |
| **`/speckit.analyze`** | Inspects consistency and verifies that the technical specifications align with domain invariants and existing tests, preventing regressions. |
| **`/speckit.implement`** | Executes test-driven code implementation, strictly validating 100% of Gherkin acceptance scenarios before marking tasks as completed. |

---

## 🏛️ Architecture & Directory Layout

The project follows **Clean Architecture** and **Screaming Architecture** principles, packaging each business action cohesively with strict boundaries:

```text
app/
├── Exceptions/                           # Domain exceptions completely decoupled from HTTP
│   ├── AccountNotFoundException.php
│   └── InsufficientFundsException.php
├── Http/
│   ├── Controllers/                      # Single-Action Controllers (__invoke)
│   │   ├── Account/
│   │   │   ├── GetBalanceController.php
│   │   │   ├── ProcessEventController.php
│   │   │   └── ResetController.php
│   │   └── Docs/
│   │       └── DocumentationController.php
│   └── Requests/                         # Strict FormRequest HTTP validation
│       └── Account/
│           ├── GetBalanceRequest.php
│           └── ProcessEventRequest.php
├── Models/
│   └── Account.php                       # Rich Domain Entity (protects balance invariants)
├── Providers/
│   └── AppServiceProvider.php            # Dynamic repository binding via match expression
├── Repositories/
│   ├── Contracts/                        # Pure storage contracts (Interfaces)
│   │   └── AccountRepositoryInterface.php
│   └── Implementations/                  # Pluggable persistence drivers
│       ├── CacheAccountRepository.php
│       ├── EloquentAccountRepository.php
│       └── RedisAccountRepository.php
└── UseCases/                             # Isolated action triads
    └── Account/
        ├── GetBalance/                   # GetBalanceUseCase, Input, Output
        ├── ProcessEvent/                 # ProcessEventUseCase, Input, Output
        └── ResetState/                   # ResetStateUseCase, Input, Output
```

### 1. Inversion of Control & Pluggable Storage Drivers
No controller or use case has direct knowledge of database drivers, cache facades, or low-level file storage. All persistence flows through `AccountRepositoryInterface`:

- **`cache` driver (`CacheAccountRepository`):** In-memory/file caching using Laravel's native Cache manager.
- **`database` driver (`EloquentAccountRepository`):** Relational persistence using SQLite/PostgreSQL/MySQL with the `Account` Eloquent model and dedicated migrations.
- **`redis` driver (`RedisAccountRepository`):** High-throughput key-value persistence leveraging Redis keys and a Set index (`accounts:index`) for atomic bulk flushes on reset.

Driver switching happens dynamically at runtime through `.env` (`ACCOUNT_STORAGE_DRIVER`) and is resolved via a PHP 8 `match` expression inside `AppServiceProvider`. It is fully compatible with production config optimization (`php artisan config:cache`):

```php
$this->app->bind(AccountRepositoryInterface::class, function () {
    return match (config('account.driver', 'cache')) {
        'database' => new EloquentAccountRepository(),
        'redis'    => new RedisAccountRepository(),
        default    => new CacheAccountRepository(),
    };
});
```

### 2. Rich Domain Entity vs. Anemic Models
Instead of leaking business invariants across controllers or introducing unnecessary POPO wrappers, the `Account` Eloquent Model serves as the single source of truth for domain state:
- `deposit(float|int $amount)`: Enforces strictly positive values and increments internal balance.
- `withdraw(float|int $amount)`: Enforces positive values, checks balance sufficiency, and throws `InsufficientFundsException` if the debit would breach balance invariants.

### 3. Action Triads & Immutable DTOs
Every use case is isolated within its dedicated namespace:
- `*UseCase.php`: Single entry-point orchestrator via `execute()`.
- `*UseCaseInput.php`: Immutable (`readonly`) DTO for strictly typed input boundaries.
- `*UseCaseOutput.php`: Immutable DTO exposing domain-safe serializations (`toArray()`, `toValue()`).

---

## 🚦 Endpoint Specifications & Compliance

| Method | URI | Description | Success Status | Media Type | Failure Handling |
| :--- | :--- | :--- | :--- | :--- | :--- |
| `POST` | `/reset` | Flushes all account states and restores pristine state | `200 OK` | `text/plain` (`OK`) | — |
| `GET` | `/balance?account_id={id}` | Reads account balance with strict idempotency | `200 OK` | `text/plain` (e.g. `20`) | `404 0` in `text/plain` |
| `POST` | `/event` | Settles financial events (`deposit`, `withdraw`, `transfer`) | `201 Created` | `application/json` | `404 0` in `text/plain` |

---

## 📖 OpenAPI 3.0 & Interactive Documentation

The system provides a formal, contract-first **OpenAPI 3.0** specification hosted at `docs/openapi.yaml`, detailing all route schemas, request bodies, and polymorphic responses.

Interactive documentation is exposed at `/docs` using **Scalar** loaded from a modern CDN, delivering zero-dependency client exploration without bloating PHP runtime dependencies (e.g., avoiding heavy Swagger generators such as `darkaonline/l5-swagger`).

---

## 🛡️ Quality Gates & CI Pipeline (GitHub Actions)

Code integrity is enforced on every Pull Request targeting `main` through automated quality gates:

1. **Linting & Formatting:** **Laravel Pint** (`vendor/bin/pint --test`) enforcing PSR-12 and Laravel code style conventions.
2. **Static Analysis:** **PHPStan / Larastan** (`vendor/bin/phpstan analyse --memory-limit=2G`) configured at **Level 6** for strict type guarantees and early bug prevention.
3. **Automated Testing:** **PHPUnit** (`php artisan test`) covering unit tests for models, repository implementations, use cases, and full HTTP feature flows.
4. **Continuous Delivery:** Integrated with **Laravel Cloud**, triggering automatic zero-downtime deployment once all GitHub Actions pass.

---

## 🛠️ Local Development & Setup

### 1. Clone the repository and install dependencies
```bash
git clone git@github.com:gilsonreis/teste-api-ebanx.git
cd teste-api-ebanx
composer install
```

### 2. Setup environment variables
```bash
cp .env.example .env
php artisan key:generate
```

### 3. Configure storage driver
In your `.env`:
```env
# Supported drivers: cache, database, redis
ACCOUNT_STORAGE_DRIVER=cache
```

*(If running with `database`, run migrations via `php artisan migrate`).*

### 4. Run Quality Checks
```bash
# Code Style Check
vendor/bin/pint --test

# Static Analysis (Level 6)
vendor/bin/phpstan analyse

# Test Suite
php artisan test
```

### 5. Start the Local Server
```bash
php artisan serve
```
Access the application at `http://localhost:8000` and the interactive documentation at `http://localhost:8000/docs`.
