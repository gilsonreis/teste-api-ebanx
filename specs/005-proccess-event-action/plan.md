# Implementation Plan: SPEC-05 — Processamento de Eventos Financeiros, Tríade do UseCase e Endpoint POST /event

**Branch**: `005-proccess-event-action` (`feature/spec-05-proccess-event-action`) | **Date**: 2026-11-10 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/005-proccess-event-action/spec.md`

---

## Summary

Implementar a ação transacional central da aplicação (`ProcessEvent`), responsável pelo processamento dos eventos financeiros `deposit`, `withdraw` e `transfer` através do endpoint `POST /event`. A solução compreende a camada de validação HTTP `ProcessEventRequest` com regras condicionais (`required_if`, `gt:0`, `different:origin`) e tratamento de erro 422 em JSON sem depender do cabeçalho `Accept: application/json`, a tríade coesa do caso de uso em `app/UseCases/Account/ProcessEvent/` (`ProcessEventUseCase`, `ProcessEventUseCaseInput`, `ProcessEventUseCaseOutput`), o Single-Action Controller `ProcessEventController` (`app/Http/Controllers/Account/ProcessEventController.php`), a orquestração dos métodos semânticos do Model `Account` (`$account->deposit()`, `$account->withdraw()`), a persistência desacoplada via `AccountRepositoryInterface`, o registro da rota `POST /event` em `routes/api.php` e a cobertura completa por testes unitários e de feature HTTP cobrindo fluxos de sucesso (201 Created em JSON), erros de negócio (404 Not Found com corpo literal `0` em `text/plain` para contas inexistentes ou saldo insuficiente) e falhas de validação (422 JSON).

---

## Technical Context

**Language/Version**: PHP `^8.3` (`declare(strict_types=1);` obrigatório em todos os arquivos)

**Primary Dependencies**: Laravel Framework `^13.17` (`Illuminate\Http\JsonResponse`, `Illuminate\Http\Response`, `Illuminate\Http\Exceptions\HttpResponseException`, `Illuminate\Foundation\Http\FormRequest`, `Illuminate\Support\Facades\Route`)

**Storage**: Persistência desacoplada via `App\Repositories\Contracts\AccountRepositoryInterface` (métodos `find(string $id)` e `save(Account $account)`), suportando dinamicamente os drivers `cache`, `database` e `redis`

**Testing**: PHPUnit `^12.5.12` (`php artisan test`), Laravel Pint `^1.27` (`vendor/bin/pint --test`), Larastan/PHPStan `^3.14` Nível 6 (`vendor/bin/phpstan analyse`)

**Target Platform**: Servidor Linux/macOS com runtime PHP 8.3+ (compatível com Laravel Cloud)

**Project Type**: Web Service / Backend API (Laravel) — Camada de Apresentação HTTP (Controller & FormRequest) e Camada de Aplicação (UseCases & DTOs)

**Performance Goals**: Tempo de resposta do endpoint `< 15ms` em execuções locais/testes com repositórios em memória ou cache

**Constraints**:
- Single-Action Controller usando o método mágico `__invoke` (`AGENTS.md` §3.1).
- Validação condicional robusta via `ProcessEventRequest` (`required_if`, `different:origin`), garantindo retorno 422 em JSON mesmo sem `Accept: application/json`.
- Tríade do caso de uso (`UseCase`, `Input`, `Output`) coesa em `app/UseCases/Account/ProcessEvent/` (`AGENTS.md` §3.2).
- DTOs imutáveis declarados com `readonly` e tipagem estrita global (`AGENTS.md` §3.2 e §5.4).
- Inversão de dependência total: o UseCase depende exclusivamente de `AccountRepositoryInterface` (`AGENTS.md` §1.5 e §3.2).
- Mutações de estado encapsuladas nos métodos semânticos do Model Eloquent `Account` (`$account->deposit()`, `$account->withdraw()`), coibindo modelo anêmico (`AGENTS.md` §1.4 e §3.3).
- Atomicidade estrita na transferência: origem buscada e debitada antes de qualquer alteração na conta de destino (`AGENTS.md` §5.2).
- Respostas HTTP em formato exato: `201 Created` com JSON no sucesso; `404 Not Found` com corpo literal `0` em `text/plain` em falhas de negócio (`AccountNotFoundException` ou `InsufficientFundsException`); `422 Unprocessable Content` em falhas de validação.
- Registro de rota em `routes/api.php` operando na raiz com `apiPrefix: ''`.

**Scale/Scope**: 1 FormRequest, 1 Controller invokable, 1 Caso de Uso, 2 DTOs (Input e Output), atualização de `routes/api.php`, 1 teste unitário do caso de uso e 1 teste de feature do endpoint HTTP.

---

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

Como `.specify/memory/constitution.md` contém apenas placeholders de template, os gates arquiteturais e de governança são validados contra a especificação canônica do projeto em `AGENTS.md`:

| Gate Arquitetural (`AGENTS.md`) | Status Pré-Pesquisa | Status Pós-Design (Phase 1) | Verificação Técnica |
| :--- | :--- | :--- | :--- |
| **1. Spec-Driven Development (SDD)** | PASS | PASS | Implementa estritamente o endpoint `POST /event`, a tríade `ProcessEvent` e os cenários BDD descritos em `specs/005-proccess-event-action/spec.md`. |
| **2. YAGNI & Simplicidade Pragmática** | PASS | PASS | DTO de saída unificado com método semântico `toArray()`, sem proliferação de classes auxiliares redundantes. |
| **3. Fronteiras Explícitas via DTOs (Input/Output)** | PASS | PASS | UseCase recebe `ProcessEventUseCaseInput` e retorna `ProcessEventUseCaseOutput`; nenhuma instância de `Request` atinge a aplicação. |
| **4. Validação Antecipada via FormRequest** | PASS | PASS | `ProcessEventRequest` valida tipos, condicionalidades por evento (`deposit`, `withdraw`, `transfer`) e auto-transferência (`different:origin`). |
| **5. Model Eloquent como Centro de Invariantes** | PASS | PASS | Mutações de saldo executadas exclusivamente via `$account->deposit()` e `$account->withdraw()`, defendendo regras de saldo positivo e fundos suficientes. |
| **6. Atomicidade em Operações Compostas** | PASS | PASS | Na transferência, origem é validada e debitada antes de tocar na conta de destino; qualquer falha interrompe o fluxo sem persistência. |
| **7. Single-Action Controllers (`__invoke`)** | PASS | PASS | `ProcessEventController` implementa exclusivamente `__invoke`, operando apenas como adaptador HTTP e tradutor de erros. |
| **8. Persistência Desacoplada (Repository Pattern)** | PASS | PASS | UseCase depende exclusivamente do contrato `AccountRepositoryInterface`, preservando suporte plugável a cache, banco ou Redis. |
| **9. Tipagem Estrita (`declare(strict_types=1);`)** | PASS | PASS | Todos os arquivos criados e editados possuem tipagem estrita declarada e cumprem os requisitos do PHPStan Nível 6. |

---

## Project Structure

### Documentation (this feature)

```text
specs/005-proccess-event-action/
├── spec.md                                   # Especificação da feature (SPEC-05)
├── plan.md                                   # Este plano de implementação (/speckit-plan)
├── research.md                               # Decisões de arquitetura, atomicidade e validação (Phase 0)
├── data-model.md                             # DTOs da tríade, entidade e fluxos sequenciais (Phase 1)
├── quickstart.md                             # Guia de validação e testes rápidos (Phase 1)
├── contracts/
│   └── event-endpoint-contract.md            # Contrato formal do endpoint POST /event (Phase 1)
└── tasks.md                                  # Lista de tarefas (gerada na próxima etapa via /speckit-tasks)
```

### Source Code (repository root)

```text
routes/
└── api.php                                   # Definição da rota Route::post('/event', ProcessEventController::class)

app/
├── Http/
│   ├── Controllers/
│   │   └── Account/
│   │       └── ProcessEventController.php    # Single-Action Controller (__invoke) com try/catch
│   └── Requests/
│       └── Account/
│           └── ProcessEventRequest.php       # Validação condicional com override de failedValidation (422 JSON)
└── UseCases/
    └── Account/
        └── ProcessEvent/
            ├── ProcessEventUseCase.php       # Orquestrador das operações financeiras e atomicidade
            ├── ProcessEventUseCaseInput.php  # Input DTO imutável
            └── ProcessEventUseCaseOutput.php # Output DTO imutável com toArray() condicional

tests/
├── Unit/
│   └── UseCases/
│       └── Account/
│           └── ProcessEventUseCaseTest.php   # Teste unitário isolado com mock do repositório
└── Feature/
    └── Account/
        └── ProcessEventEndpointTest.php      # Teste de Feature HTTP (201 Created JSON, 404 text/plain '0', 422 JSON)
```

**Structure Decision**: Adotada rigorosamente a estrutura canônica do projeto definida no `AGENTS.md` (§2 e §3), com controller Single-Action em `app/Http/Controllers/Account/`, `FormRequest` em `app/Http/Requests/Account/`, tríade do caso de uso em `app/UseCases/Account/ProcessEvent/`, mutações no Model em `app/Models/Account.php` e testes espelhados em `tests/Unit/` e `tests/Feature/`.

---

## Complexity Tracking

| Violação | Justificativa Técnica | Alternativa Mais Simples Rejeitada |
| :--- | :--- | :--- |
| *Nenhuma violação identificada* | Todas as decisões seguem estritamente as convenções mínimas exigidas pelo `AGENTS.md`. | N/A |
