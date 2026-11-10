# Implementation Plan: SPEC-02 — Contrato de Repositório, Persistência Plugável e Resolução Dinâmica

**Branch**: `002-repository-persistence` (`feature/spec-02-repository-persistence`) | **Date**: 2026-11-10 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/002-repository-persistence/spec.md`

---

## Summary

Definir o contrato universal de persistência `App\Repositories\Contracts\AccountRepositoryInterface` (`find`, `save`, `reset`) e implementar três drivers concretos intercambiáveis em tempo de execução — `CacheAccountRepository` (`cache`), `EloquentAccountRepository` (`database` / SQLite) e `RedisAccountRepository` (`redis`) — acompanhados do arquivo de configuração `config/account.php`, da migration `create_accounts_table` e do chaveamento dinâmico via expressão `match` no `AppServiceProvider`. Inclui suíte completa de testes unitários isolados para cada driver e para a resolução no Service Container.

---

## Technical Context

**Language/Version**: PHP `^8.3` (`declare(strict_types=1);` obrigatório em todos os arquivos)

**Primary Dependencies**: Laravel Framework `^13.17` (`Illuminate\Support\Facades\Cache`, `Illuminate\Database\Eloquent\Model`, `Illuminate\Support\Facades\Redis`, `Illuminate\Support\ServiceProvider`)

**Storage**: Plugável via `config('account.driver')` suportando 3 mecanismos:
- `cache`: Laravel Cache Facade (`array` em testes, `file`/`database`/`redis` em runtime)
- `database`: SQLite via Eloquent Model `App\Models\Account` (`:memory:` em testes)
- `redis`: Redis via `Illuminate\Support\Facades\Redis` com indexação em Set (`accounts:index`)

**Testing**: PHPUnit `^12.5.12` (`php artisan test`), Laravel Pint `^1.27` (`vendor/bin/pint --test`), Larastan/PHPStan `^3.14` Nível 6 (`vendor/bin/phpstan analyse`)

**Target Platform**: Servidor Linux/macOS com runtime PHP 8.3+ (compatível com Laravel Cloud)

**Project Type**: Web Service / Backend API (Laravel) — Camada de Persistência & Infraestrutura (Repositories, Config, Migration, ServiceProvider)

**Performance Goals**: Operações de leitura e escrita em memória/cache/SQLite in-memory em sub-milissegundos (`< 5ms` por operação em testes), compatibilidade total com `php artisan config:cache`.

**Constraints**:
- Proibido chamar `env()` fora de arquivos em `config/` (`AGENTS.md` §1.6 e `spec.md` §4).
- Resolução de dependência no `AppServiceProvider` deve usar exclusivamente `config('account.driver', 'cache')` com estrutura `match`, lançando `\InvalidArgumentException` para drivers desconhecidos (`AGENTS.md` §3.4 e `spec.md` §4.2).
- Proibido usar blocos `else` / `elseif`; obrigatório uso de Guard Clauses (`AGENTS.md` §5.1).
- Idempotência estrita em `find(string $id): ?Account` sem efeitos colaterais (`AGENTS.md` §5.3 e `spec.md` §2).
- Conformidade irrestrita com PHPStan Nível 6 (`phpstan.neon`) e Laravel Pint.

**Scale/Scope**: 1 interface de contrato, 3 implementações concretas de repositório, 1 arquivo de configuração (`config/account.php`), 1 migration (`create_accounts_table`), atualização do `AppServiceProvider` e 3 arquivos de testes unitários cobrindo todos os cenários Gherkin.

---

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

Como `.specify/memory/constitution.md` contém apenas placeholders de template, os gates arquiteturais e de governança são validados contra a especificação canônica do projeto em `AGENTS.md`:

| Gate Arquitetural (`AGENTS.md`) | Status Pré-Pesquisa | Status Pós-Design (Phase 1) | Verificação Técnica |
| :--- | :--- | :--- | :--- |
| **1. Spec-Driven Development (SDD)** | PASS | PASS | Implementa estritamente o contrato `AccountRepositoryInterface`, os 3 drivers e os cenários BDD de `specs/002-repository-persistence/spec.md`. |
| **2. YAGNI & Simplicidade Pragmática** | PASS | PASS | Interface restrita aos 3 métodos essenciais (`find`, `save`, `reset`), sem métodos especulativos de paginação ou filtros não solicitados. |
| **3. Persistência Plugável (Repository Pattern)** | PASS | PASS | Domínio isolado de mecanismos físicos por `AccountRepositoryInterface`, com resolução dinâmica via `match` no `AppServiceProvider`. |
| **4. Segurança de Configuração (`config:cache`)** | PASS | PASS | `env('ACCOUNT_STORAGE_DRIVER', 'cache')` restrito exclusivamente a `config/account.php`; runtime consulta apenas `config('account.driver')`. |
| **5. Guard Clauses & Saída Antecipada** | PASS | PASS | Métodos `find`, `save` e `reset` nos 3 drivers utilizam retorno antecipado sem `else`/`elseif`. |
| **6. Tipagem Estrita (`declare(strict_types=1);`)** | PASS | PASS | Todos os arquivos de código, configuração, migration e testes declaram `declare(strict_types=1);` e passam no Larastan Nível 6. |

---

## Project Structure

### Documentation (this feature)

```text
specs/002-repository-persistence/
├── spec.md                                   # Especificação da feature (SPEC-02)
├── plan.md                                   # Este plano de implementação (/speckit-plan)
├── research.md                               # Decisões arquiteturais e técnicas (Phase 0)
├── data-model.md                             # Esquema de armazenamento por driver e tabela SQLite (Phase 1)
├── quickstart.md                             # Guia de validação e execução dos testes e config:cache (Phase 1)
├── contracts/
│   └── account-repository-contract.md        # Contrato formal de AccountRepositoryInterface e bindings (Phase 1)
└── tasks.md                                  # Lista de tarefas (gerada na próxima etapa via /speckit-tasks)
```

### Source Code (repository root)

```text
config/
└── account.php                               # Mapeamento do driver (ACCOUNT_STORAGE_DRIVER -> cache|database|redis)

database/
└── migrations/
    └── 2026_11_10_000000_create_accounts_table.php  # Tabela accounts (id string PK, balance decimal, timestamps)

app/
├── Models/
│   └── Account.php                           # Entidade de domínio existente (implementada na SPEC-01)
├── Providers/
│   └── AppServiceProvider.php                # Binding dinâmico AccountRepositoryInterface via match(config('account.driver'))
└── Repositories/
    ├── Contracts/
    │   └── AccountRepositoryInterface.php    # Interface pura (find, save, reset)
    └── Implementations/
        ├── CacheAccountRepository.php        # Driver 'cache' via Cache Facade
        ├── EloquentAccountRepository.php     # Driver 'database' via Eloquent Model Account (SQLite)
        └── RedisAccountRepository.php        # Driver 'redis' via Redis Facade + Set index

tests/
└── Unit/
    └── Repositories/
        ├── CacheAccountRepositoryTest.php    # Testes unitários do driver Cache + resolução do container
        ├── EloquentAccountRepositoryTest.php # Testes unitários do driver Eloquent sobre SQLite :memory:
        └── RedisAccountRepositoryTest.php    # Testes unitários do driver Redis + validação de driver inválido
```

**Structure Decision**: Segue integralmente o layout definido na Seção 2 do `AGENTS.md` e na Seção 5 da `spec.md`, mantendo contratos em `app/Repositories/Contracts/`, implementações em `app/Repositories/Implementations/`, configuração dedicada em `config/account.php` e testes categorizados em `tests/Unit/Repositories/`.

---

## Complexity Tracking

Nenhuma violação constitucional ou arquitetural identificada. Tabela não aplicável.
