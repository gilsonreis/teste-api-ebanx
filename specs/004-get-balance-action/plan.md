# Implementation Plan: SPEC-04 — Consulta de Saldo, Tríade do UseCase e Endpoint GET /balance

**Branch**: `004-get-balance-action` (`feature/spec-04-get-balance-action`) | **Date**: 2026-11-10 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/004-get-balance-action/spec.md`

---

## Summary

Implementar a ação de consulta de saldo (`GetBalance`), responsável por ler de forma idempotente o estado de uma conta bancária através de `AccountRepositoryInterface::find()`. A solução compreende a exceção de domínio desacoplada `AccountNotFoundException` (`app/Exceptions/AccountNotFoundException.php`), a camada de validação HTTP `GetBalanceRequest` com tratamento explícito para retorno 422 em JSON mesmo sem o cabeçalho `Accept: application/json`, a tríade coesa do caso de uso em `app/UseCases/Account/GetBalance/` (`GetBalanceUseCase`, `GetBalanceUseCaseInput`, `GetBalanceUseCaseOutput`), o Single-Action Controller `GetBalanceController` (`app/Http/Controllers/Account/GetBalanceController.php`), o registro da rota `GET /balance` em `routes/api.php` e a cobertura completa por testes unitários e de feature HTTP cobrindo cenários de sucesso (200 com saldo em `text/plain`), conta inexistente (404 com corpo `0`), erro de validação (422) e garantia de idempotência.

---

## Technical Context

**Language/Version**: PHP `^8.3` (`declare(strict_types=1);` obrigatório em todos os arquivos)

**Primary Dependencies**: Laravel Framework `^13.17` (`Illuminate\Http\Response`, `Illuminate\Http\Exceptions\HttpResponseException`, `Illuminate\Foundation\Http\FormRequest`, `Illuminate\Support\Facades\Route`)

**Storage**: Leitura desacoplada via `App\Repositories\Contracts\AccountRepositoryInterface::find(string $id)` (suportando drivers `cache`, `database` e `redis` configurados na SPEC-02)

**Testing**: PHPUnit `^12.5.12` (`php artisan test`), Laravel Pint `^1.27` (`vendor/bin/pint --test`), Larastan/PHPStan `^3.14` Nível 6 (`vendor/bin/phpstan analyse`)

**Target Platform**: Servidor Linux/macOS com runtime PHP 8.3+ (compatível com Laravel Cloud)

**Project Type**: Web Service / Backend API (Laravel) — Camada de Apresentação HTTP (Controller & FormRequest) e Camada de Aplicação (UseCases & DTOs)

**Performance Goals**: Tempo de resposta do endpoint `< 10ms` em leituras locais/testes, operando de forma estritamente idempotente.

**Constraints**:
- Single-Action Controller usando o método mágico `__invoke` (`AGENTS.md` §3.1).
- Validação isolada via `GetBalanceRequest`, garantindo retorno 422 em JSON mesmo sem `Accept: application/json`.
- Tríade do caso de uso (`UseCase`, `Input`, `Output`) coesa em `app/UseCases/Account/GetBalance/` (`AGENTS.md` §3.2).
- DTOs imutáveis declarados com `readonly` e tipagem estrita global (`AGENTS.md` §3.2 e §5.4).
- Inversão de dependência total: o UseCase depende exclusivamente de `AccountRepositoryInterface` (`AGENTS.md` §1.5 e §3.2).
- Exceção de domínio `AccountNotFoundException` desacoplada de status HTTP (`AGENTS.md` §3.1).
- Idempotência estrita em leituras: sem mutação de saldo nem criação de contas (`AGENTS.md` §5.3).
- Respostas HTTP em formato exato: `200` com saldo numérico em `text/plain; charset=UTF-8` e `404` com corpo `0`.

**Scale/Scope**: 1 Exceção de domínio, 1 FormRequest, 1 Controller invokable, 1 Caso de Uso, 2 DTOs (Input e Output), atualização de `routes/api.php`, 1 teste unitário do caso de uso e 1 teste de feature do endpoint HTTP.

---

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

Como `.specify/memory/constitution.md` contém apenas placeholders de template, os gates arquiteturais e de governança são validados contra a especificação canônica do projeto em `AGENTS.md`:

| Gate Arquitetural (`AGENTS.md`) | Status Pré-Pesquisa | Status Pós-Design (Phase 1) | Verificação Técnica |
| :--- | :--- | :--- | :--- |
| **1. Spec-Driven Development (SDD)** | PASS | PASS | Implementa estritamente o endpoint `GET /balance`, o caso de uso e os cenários BDD descritos em `specs/004-get-balance-action/spec.md`. |
| **2. YAGNI & Simplicidade Pragmática** | PASS | PASS | Consulta pura sem abstrações adicionais ou camadas intermediárias desnecessárias. |
| **3. Fronteiras Explícitas via DTOs (Input/Output)** | PASS | PASS | UseCase recebe `GetBalanceUseCaseInput` e retorna `GetBalanceUseCaseOutput`; nenhuma instância de `Request` atinge o caso de uso. |
| **4. Validação Antecipada via FormRequest** | PASS | PASS | `GetBalanceRequest` valida a presença e integridade do parâmetro `account_id` antes do controller. |
| **5. Single-Action Controllers (`__invoke`)** | PASS | PASS | `GetBalanceController` implementa exclusivamente `__invoke`, operando apenas como adaptador de transporte e tradutor de erros. |
| **6. Persistência Desacoplada (Repository Pattern)** | PASS | PASS | O caso de uso depende puramente de `AccountRepositoryInterface::find()`, sem conhecimento do mecanismo físico de armazenamento. |
| **7. Idempotência de Leitura (GET)** | PASS | PASS | A operação executa somente leituras, sem efeitos colaterais de gravação ou alteração de estado. |
| **8. Tipagem Estrita (`declare(strict_types=1);`)** | PASS | PASS | Todos os arquivos criados e editados declaram tipagem estrita e cumprem os requisitos do PHPStan Nível 6. |

---

## Project Structure

### Documentation (this feature)

```text
specs/004-get-balance-action/
├── spec.md                                   # Especificação da feature (SPEC-04)
├── plan.md                                   # Este plano de implementação (/speckit-plan)
├── research.md                               # Decisões de arquitetura e validação 422 (Phase 0)
├── data-model.md                             # DTOs da tríade, entidade e fluxo sequencial (Phase 1)
├── quickstart.md                             # Guia de validação e testes rápidos (Phase 1)
├── contracts/
│   └── balance-endpoint-contract.md          # Contrato formal do endpoint GET /balance (Phase 1)
└── tasks.md                                  # Lista de tarefas (gerada na próxima etapa via /speckit-tasks)
```

### Source Code (repository root)

```text
routes/
└── api.php                                   # Definição da rota Route::get('/balance', GetBalanceController::class)

app/
├── Exceptions/
│   └── AccountNotFoundException.php          # Exceção pura de domínio (sem acoplamento HTTP)
├── Http/
│   ├── Controllers/
│   │   └── Account/
│   │       └── GetBalanceController.php      # Single-Action Controller (__invoke) com try/catch
│   └── Requests/
│       └── Account/
│           └── GetBalanceRequest.php         # Validação de account_id com override de failedValidation (422 JSON)
└── UseCases/
    └── Account/
        └── GetBalance/
            ├── GetBalanceUseCase.php         # Orquestrador da leitura via repositório
            ├── GetBalanceUseCaseInput.php    # Input DTO imutável
            └── GetBalanceUseCaseOutput.php   # Output DTO imutável

tests/
├── Unit/
│   └── UseCases/
│       └── Account/
│           └── GetBalanceUseCaseTest.php     # Teste unitário isolado com mock do repositório
└── Feature/
    └── Account/
        └── GetBalanceEndpointTest.php        # Teste de Feature HTTP (200 OK com saldo, 404 com '0', 422 JSON, idempotência)
```

**Structure Decision**: Adotada a estrutura padrão do projeto definida no `AGENTS.md` (§2 e §3), com controller Single-Action em `app/Http/Controllers/Account/`, `FormRequest` em `app/Http/Requests/Account/`, tríade do caso de uso em `app/UseCases/Account/GetBalance/`, exceção de domínio em `app/Exceptions/` e testes espelhados em `tests/Unit/` e `tests/Feature/`.

---

## Complexity Tracking

| Violação | Justificativa Técnica | Alternativa Mais Simples Rejeitada |
| :--- | :--- | :--- |
| *Nenhuma violação identificada* | Todas as decisões seguem estritamente as convenções mínimas exigidas pelo `AGENTS.md`. | N/A |
