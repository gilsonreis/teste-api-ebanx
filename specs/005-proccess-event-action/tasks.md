# Tasks: SPEC-05 — Processamento de Eventos Financeiros, Tríade do UseCase e Endpoint POST /event

**Input**: Design documents from `/specs/005-proccess-event-action/`
**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`, `contracts/event-endpoint-contract.md`, `quickstart.md`
**Tests**: Inclusos conforme especificado nos cenários BDD e diretrizes do `AGENTS.md` (TDD / Test-First).

---

## Phase 1: Setup (Camada de Validação HTTP)

**Purpose**: Criação do FormRequest responsável pela validação condicional rigorosa de `type`, `amount`, `origin` e `destination`, além da garantia de resposta 422 em JSON.

- [X] T001 Implement validation FormRequest ProcessEventRequest with conditional rules and failedValidation override in `app/Http/Requests/Account/ProcessEventRequest.php`

---

## Phase 2: Foundational (DTOs da Camada de Aplicação)

**Purpose**: Estruturar os Data Transfer Objects imutáveis da tríade do caso de uso antes da implementação da lógica de execução.

**⚠️ CRITICAL**: A implementação do caso de uso e dos testes depende diretamente da existência destes DTOs.

- [X] T002 [P] Implement immutable input DTO ProcessEventUseCaseInput in `app/UseCases/Account/ProcessEvent/ProcessEventUseCaseInput.php`
- [X] T003 [P] Implement immutable output DTO ProcessEventUseCaseOutput with toArray filtering in `app/UseCases/Account/ProcessEvent/ProcessEventUseCaseOutput.php`

**Checkpoint**: DTOs criados e tipados estritamente — implementação do caso de uso e dos testes pode prosseguir.

---

## Phase 3: User Story 1 - Process Event UseCase (Priority: P1) 🎯 MVP

**Goal**: Orquestrar a execução das operações `deposit`, `withdraw` e `transfer` via `AccountRepositoryInterface`, interagindo com os métodos semânticos do Model `Account` (`$account->deposit()`, `$account->withdraw()`), assegurando atomicidade estrita em transferências e lançando exceções puras de domínio (`AccountNotFoundException` e `InsufficientFundsException`).

**Independent Test**: `php artisan test --filter=ProcessEventUseCaseTest`

### Tests for User Story 1 (TDD) ⚠️
> **NOTE: Escrever os testes unitários primeiro e verificar que falham antes de implementar o UseCase.**

- [X] T004 [P] [US1] Create unit tests for ProcessEventUseCase in `tests/Unit/UseCases/Account/ProcessEventUseCaseTest.php`

### Implementation for User Story 1
- [X] T005 [US1] Implement ProcessEventUseCase with deposit, withdraw and atomic transfer logic in `app/UseCases/Account/ProcessEvent/ProcessEventUseCase.php`

**Checkpoint**: `ProcessEventUseCaseTest` passando com 100% de sucesso. O caso de uso está concluído e testado unitariamente de forma isolada.

---

## Phase 4: User Story 2 - HTTP Endpoint POST /event (Priority: P1)

**Goal**: Expor as operações de eventos financeiros via endpoint HTTP `POST /event` utilizando `ProcessEventRequest` e Single-Action Controller (`ProcessEventController`), retornando código HTTP `201 Created` com payload JSON no sucesso, `404 Not Found` com corpo `'0'` em `text/plain` em caso de conta inexistente ou saldo insuficiente, e `422 Unprocessable Content` em JSON em caso de payload inválido ou auto-transferência.

**Independent Test**: `php artisan test --filter=ProcessEventEndpointTest`

### Tests for User Story 2 (TDD) ⚠️
> **NOTE: Escrever os testes de feature primeiro cobrindo todos os cenários EBANX (depósito, saque, transferência, contas inexistentes, saldo insuficiente, validação e auto-transferência).**

- [X] T006 [P] [US2] Create feature test for POST /event endpoint covering all EBANX scenarios in `tests/Feature/Account/ProcessEventEndpointTest.php`

### Implementation for User Story 2
- [X] T007 [US2] Implement Single-Action ProcessEventController with exception mapping in `app/Http/Controllers/Account/ProcessEventController.php`
- [X] T008 [US2] Register route Route::post('/event', ProcessEventController::class) in `routes/api.php`

**Checkpoint**: `ProcessEventEndpointTest` passando com 100% de sucesso. O endpoint HTTP está integrado e funcional de ponta a ponta.

---

## Phase 5: Polish & Cross-Cutting Concerns

**Purpose**: Validação de qualidade, conformidade de estilo, análise estática e ausência de regressões na base de código.

- [X] T009 [P] Format code with Laravel Pint in entire codebase
- [X] T010 Run static analysis with Larastan / PHPStan Level 6
- [X] T011 Run complete test suite and execute quickstart validation scenarios per `specs/005-proccess-event-action/quickstart.md`

---

## Dependencies & Execution Order

### Phase Dependencies
- **Setup (Phase 1)**: Sem dependências — execução imediata de `T001`.
- **Foundational (Phase 2)**: Sem dependências de lógica — bloqueia a implementação do caso de uso e dos testes (`T002`, `T003`).
- **User Story 1 (Phase 3)**: Depende da Phase 2 (DTOs disponíveis).
- **User Story 2 (Phase 4)**: Depende da Phase 3 (`ProcessEventUseCase` pronto para injeção no controller) e de `ProcessEventRequest` (Phase 1).
- **Polish (Phase 5)**: Depende da conclusão das fases 3 e 4.

### User Story Dependencies
- **User Story 1 (P1)**: Pode iniciar após a Phase 2 (Foundational). Sem dependência de outras histórias.
- **User Story 2 (P1)**: Depende da User Story 1 (`ProcessEventUseCase` e DTOs prontos para injeção no controller).

### Parallel Opportunities
- **Phase 2**: `T002` (`ProcessEventUseCaseInput`) e `T003` (`ProcessEventUseCaseOutput`) podem ser criados em paralelo (arquivos distintos).
- **Phase 3**: `T004` (teste unitário) é escrito primeiro; `T005` implementa o UseCase.
- **Phase 4**: `T006` (teste de feature) é escrito primeiro; `T007` (Controller) e `T008` (Registro da rota) completam a entrega.
- **Phase 5**: `T009` (Pint) pode rodar antes de `T010` (PHPStan) e `T011` (Testes completos).

---

## Parallel Example: User Story 1 & 2

```bash
# Execução paralela dos DTOs na Phase 2:
Task: "Implement immutable input DTO ProcessEventUseCaseInput in app/UseCases/Account/ProcessEvent/ProcessEventUseCaseInput.php"
Task: "Implement immutable output DTO ProcessEventUseCaseOutput in app/UseCases/Account/ProcessEvent/ProcessEventUseCaseOutput.php"

# Execução TDD em cada User Story:
# 1. Escrever e falhar o teste unitário (T004), depois implementar o UseCase (T005)
# 2. Escrever e falhar o teste de feature (T006), depois implementar o Controller (T007) e rota (T008)
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)
1. Completar Phase 1: Setup (`ProcessEventRequest`).
2. Completar Phase 2: Foundational (`ProcessEventUseCaseInput` e `ProcessEventUseCaseOutput`).
3. Completar Phase 3: User Story 1 (Escrever `ProcessEventUseCaseTest` e implementar `ProcessEventUseCase`).
4. **VALIDAR**: Executar `php artisan test --filter=ProcessEventUseCaseTest` para garantir lógica de domínio 100% testada.

### Incremental Delivery (User Story 2)
1. Escrever teste de Feature `ProcessEventEndpointTest` cobrindo todos os cenários da especificação.
2. Implementar `ProcessEventController` capturando `AccountNotFoundException` e `InsufficientFundsException`.
3. Registrar a rota em `routes/api.php`.
4. Executar `php artisan test --filter=ProcessEventEndpointTest` e validar integração de ponta a ponta.
5. Executar Pint, Larastan e suíte completa de testes.

