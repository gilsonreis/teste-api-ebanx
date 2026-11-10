---

description: "Task list for SPEC-02: Contrato de Repositório, Persistência Plugável e Resolução Dinâmica"
---

# Tasks: SPEC-02 — Contrato de Repositório, Persistência Plugável e Resolução Dinâmica

**Input**: Design documents from `/specs/002-repository-persistence/`

**Prerequisites**: `plan.md` (required), `spec.md` (required for user stories), `research.md`, `data-model.md`, `contracts/account-repository-contract.md`, `quickstart.md`

**Tests**: Incluídos obrigatoriamente conforme exigência explícita da `spec.md` (Seções 5, 6 e 7) e do `AGENTS.md` (cobertura integral dos cenários Gherkin para `cache`, `database`, `redis` e resolução de driver inválido).

**Organization**: As tarefas estão organizadas por história de usuário / driver de persistência para permitir implementação, teste e validação incremental e independente.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Pode ser executada em paralelo (arquivos distintos, sem dependências de tarefas incompletas)
- **[Story]**: Rótulo da história a que pertence a tarefa (`[US1]`, `[US2]`, `[US3]`)
- Caminhos exatos de arquivos incluídos em todas as descrições

## Path Conventions

- **Contracts**: `app/Repositories/Contracts/`
- **Implementations**: `app/Repositories/Implementations/`
- **Providers**: `app/Providers/`
- **Config**: `config/`
- **Migrations**: `database/migrations/`
- **Unit Tests**: `tests/Unit/Repositories/`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Preparar a estrutura canônica de diretórios exigida pelo plano de implementação (`AGENTS.md` §2 e `spec.md` §5).

- [X] T001 Ensure directories `app/Repositories/Contracts/`, `app/Repositories/Implementations/`, and `tests/Unit/Repositories/` exist per `specs/002-repository-persistence/plan.md`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Criar o contrato puro `AccountRepositoryInterface`, o arquivo de configuração `config/account.php` e a migration da tabela `accounts` antes de implementar os drivers concretos.

**⚠️ CRITICAL**: Nenhuma história de usuário deve ser iniciada antes da conclusão desta fase.

- [X] T002 [P] Create pure persistence interface `App\Repositories\Contracts\AccountRepositoryInterface` with `declare(strict_types=1);` and signatures `find(string $id): ?Account`, `save(Account $account): void`, and `reset(): void` in `app/Repositories/Contracts/AccountRepositoryInterface.php`
- [X] T003 [P] Create account storage configuration file with `declare(strict_types=1);` returning `['driver' => env('ACCOUNT_STORAGE_DRIVER', 'cache')]` in `config/account.php`
- [X] T004 [P] Create migration for the `accounts` table (`id` string primary key, `balance` decimal(15, 2) default 0, `timestamps`) with `declare(strict_types=1);` in `database/migrations/2026_11_10_000000_create_accounts_table.php`

**Checkpoint**: Contrato, configuração e schema relacional prontos — as implementações dos drivers (`US1`, `US2`, `US3`) podem ser iniciadas em paralelo.

---

## Phase 3: User Story 1 - Persistência em Cache (`CacheAccountRepository`) (Priority: P1) 🎯 MVP

**Goal**: Implementar o driver padrão `cache` (`CacheAccountRepository`) utilizando a facade `Illuminate\Support\Facades\Cache` com chaves `account:{id}` e índice `account:index`, suportando persistência, busca idempotente e expurgo completo via `reset()`.

**Independent Test**: Executar `php artisan test --filter=CacheAccountRepositoryTest` validando os 3 cenários Gherkin (`save` + `find`, `find` de conta inexistente retornando `null` e `reset` expurgando todas as contas) para o driver `cache`.

### Tests for User Story 1 ⚠️

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [X] T005 [P] [US1] Write unit tests for `CacheAccountRepository` covering `save()` and `find()` (`id` `"100"`, `balance` `20`), `find("999")` returning `null`, and `reset()` purging accounts `"100"` and `"200"` in `tests/Unit/Repositories/CacheAccountRepositoryTest.php`

### Implementation for User Story 1

- [X] T006 [US1] Implement `App\Repositories\Implementations\CacheAccountRepository` implementing `AccountRepositoryInterface` with `declare(strict_types=1);`, guard clauses, `account:{id}` serialization, and `account:index` tracking in `app/Repositories/Implementations/CacheAccountRepository.php`

**Checkpoint**: O driver `cache` (`CacheAccountRepository`) está funcional e testado isoladamente em memória.

---

## Phase 4: User Story 2 - Persistência Relacional SQLite (`EloquentAccountRepository`) (Priority: P1)

**Goal**: Implementar o driver `database` (`EloquentAccountRepository`) utilizando o Model Eloquent `App\Models\Account` sobre SQLite, garantindo criação/atualização idempotente de registros, busca por chave primária string e limpeza da tabela no `reset()`.

**Independent Test**: Executar `php artisan test --filter=EloquentAccountRepositoryTest` utilizando SQLite em memória (`:memory:`) para validar os 3 cenários Gherkin (`save` + `find`, `find` inexistente retornando `null` e `reset` removendo todas as contas) no driver `database`.

### Tests for User Story 2 ⚠️

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [X] T007 [P] [US2] Write unit tests for `EloquentAccountRepository` using SQLite `:memory:` covering `save()` and `find()` (`id` `"100"`, `balance` `20`), updating an existing account balance, `find("999")` returning `null`, and `reset()` deleting accounts `"100"` and `"200"` in `tests/Unit/Repositories/EloquentAccountRepositoryTest.php`

### Implementation for User Story 2

- [X] T008 [US2] Implement `App\Repositories\Implementations\EloquentAccountRepository` implementing `AccountRepositoryInterface` with `declare(strict_types=1);`, `Account::query()->find($id)`, `Account::query()->updateOrCreate(...)`, and `Account::query()->delete()` in `app/Repositories/Implementations/EloquentAccountRepository.php`

**Checkpoint**: Os drivers `cache` e `database` (SQLite) estão funcionais e validados independentemente.

---

## Phase 5: User Story 3 - Persistência em Redis e Resolução Dinâmica no Container (Priority: P1)

**Goal**: Implementar o driver `redis` (`RedisAccountRepository`) utilizando `Illuminate\Support\Facades\Redis` com chaves `accounts:{id}` e conjunto `accounts:index`, e configurar a resolução dinâmica via `match (config('account.driver', 'cache'))` no `AppServiceProvider`, garantindo falha antecipada (`\InvalidArgumentException`) para drivers inválidos.

**Independent Test**: Executar `php artisan test --filter=RedisAccountRepositoryTest` validando os 3 cenários Gherkin para o driver `redis` e o cenário Gherkin de resolução de driver inválido (`"invalid_driver"` lançando `\InvalidArgumentException`) no container do Laravel.

### Tests for User Story 3 ⚠️

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [X] T009 [P] [US3] Write unit tests for `RedisAccountRepository` (`save()`, `find()` existing/non-existent, `reset()`) and `AppServiceProvider` dynamic container resolution (`cache`, `database`, `redis`, and `"invalid_driver"` throwing `\InvalidArgumentException`) in `tests/Unit/Repositories/RedisAccountRepositoryTest.php`

### Implementation for User Story 3

- [X] T010 [P] [US3] Implement `App\Repositories\Implementations\RedisAccountRepository` implementing `AccountRepositoryInterface` with `declare(strict_types=1);`, guard clauses, `accounts:{id}` keys, and `accounts:index` Redis Set in `app/Repositories/Implementations/RedisAccountRepository.php`
- [X] T011 [US3] Register `AccountRepositoryInterface` binding in `App\Providers\AppServiceProvider::register()` with `declare(strict_types=1);` using `match (config('account.driver', 'cache'))` and throwing `\InvalidArgumentException` for unsupported drivers in `app/Providers/AppServiceProvider.php`

**Checkpoint**: Todos os três drivers (`cache`, `database`, `redis`) e o chaveamento dinâmico no `AppServiceProvider` estão completos e testados.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Validar cacheamento de configuração em produção, execução completa da suíte de testes e conformidade estrita com Laravel Pint e PHPStan/Larastan Nível 6 (`spec.md` §7 e `AGENTS.md` §6).

- [X] T012 Verify configuration cache compatibility by running `php artisan config:cache` followed by `php artisan config:clear` validating `config/account.php` and `app/Providers/AppServiceProvider.php`
- [X] T013 Run full test suite `php artisan test` to verify 100% pass rate across `tests/Unit/Repositories/CacheAccountRepositoryTest.php`, `tests/Unit/Repositories/EloquentAccountRepositoryTest.php`, `tests/Unit/Repositories/RedisAccountRepositoryTest.php`, and `tests/Unit/Models/AccountTest.php`
- [X] T014 [P] Run Laravel Pint code style check `vendor/bin/pint --test` across `app/`, `config/account.php`, `database/migrations/`, and `tests/`
- [X] T015 [P] Run PHPStan/Larastan static analysis `vendor/bin/phpstan analyse` at Level 6 using `phpstan.neon`

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: Sem dependências — início imediato (`T001`).
- **Foundational (Phase 2)**: Depende de `T001` — `T002`, `T003` e `T004` atuam em arquivos distintos, podem rodar em paralelo (`[P]`) e bloqueiam o início das histórias de usuário.
- **User Story 1 (Phase 3 - MVP)**: Depende de `T002` (`AccountRepositoryInterface`) — executa `T005` → `T006`.
- **User Story 2 (Phase 4)**: Depende de `T002` (`AccountRepositoryInterface`) e `T004` (migration `accounts`) — executa `T007` → `T008`. Independente de `US1`.
- **User Story 3 (Phase 5)**: `T009` e `T010` dependem apenas de `T002`; `T011` (`AppServiceProvider`) depende de `T003`, `T006`, `T008` e `T010` para referenciar as três classes concretas.
- **Polish (Phase 6)**: Depende da conclusão de todas as histórias (`T011`) — `T014` e `T015` podem rodar em paralelo (`[P]`).

### User Story Dependencies

- **User Story 1 (P1 - Cache Driver)**: Pode iniciar imediatamente após a Phase 2 (`T002`). Não possui dependência de outras histórias.
- **User Story 2 (P1 - Eloquent/SQLite Driver)**: Pode iniciar imediatamente após a Phase 2 (`T002`, `T004`), em paralelo com a `US1`.
- **User Story 3 (P1 - Redis Driver & Provider Resolution)**: Os testes e a implementação do `RedisAccountRepository` (`T009`, `T010`) podem iniciar em paralelo com `US1` e `US2` após `T002`; o binding final no `AppServiceProvider` (`T011`) conecta `US1`, `US2` e `US3`.

### Parallel Opportunities

- **Phase 2 (Foundational)**: `T002` (`AccountRepositoryInterface.php`), `T003` (`config/account.php`) e `T004` (`2026_11_10_000000_create_accounts_table.php`) podem ser executadas simultaneamente.
- **Cross-Story Parallelism (Phases 3, 4, 5)**: Os testes `T005`, `T007`, `T009` e as implementações `T006` (`CacheAccountRepository.php`), `T008` (`EloquentAccountRepository.php`) e `T010` (`RedisAccountRepository.php`) operam em arquivos totalmente independentes.
- **Phase 6 (Polish)**: `T014` (`vendor/bin/pint --test`) e `T015` (`vendor/bin/phpstan analyse`) podem rodar em paralelo.

---

## Parallel Example: Foundational & Drivers

```bash
# Phase 2 - Execução paralela de artefatos fundacionais em arquivos distintos:
Task T002: "Create AccountRepositoryInterface in app/Repositories/Contracts/AccountRepositoryInterface.php"
Task T003: "Create account config in config/account.php"
Task T004: "Create accounts table migration in database/migrations/2026_11_10_000000_create_accounts_table.php"

# Phases 3, 4, 5 - Execução paralela dos testes e drivers concretos:
Task T005: "Write unit tests for CacheAccountRepository in tests/Unit/Repositories/CacheAccountRepositoryTest.php"
Task T007: "Write unit tests for EloquentAccountRepository in tests/Unit/Repositories/EloquentAccountRepositoryTest.php"
Task T009: "Write unit tests for RedisAccountRepository in tests/Unit/Repositories/RedisAccountRepositoryTest.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 — Driver Padrão `cache`)

1. Concluir Phase 1 (`T001`) e Phase 2 (`T002`, `T003`, `T004`).
2. Concluir Phase 3 (`T005` → `T006`) entregando `CacheAccountRepository` funcional.
3. Validar isoladamente com `php artisan test --filter=CacheAccountRepositoryTest`.

### Incremental TDD Delivery (US1 → US2 → US3 → Polish)

1. **Foundation**: Interface + Config + Migration (`T001`–`T004`).
2. **US1 (Cache)**: `CacheAccountRepositoryTest` (`T005`) → `CacheAccountRepository` (`T006`).
3. **US2 (Database/SQLite)**: `EloquentAccountRepositoryTest` (`T007`) → `EloquentAccountRepository` (`T008`).
4. **US3 (Redis & Container Binding)**: `RedisAccountRepositoryTest` (`T009`) → `RedisAccountRepository` (`T010`) → `AppServiceProvider` (`T011`).
5. **Quality Gates**: `config:cache` (`T012`), PHPUnit (`T013`), Pint (`T014`) e PHPStan Nível 6 (`T015`).

