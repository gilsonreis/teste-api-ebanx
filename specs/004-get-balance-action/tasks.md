# Tasks: SPEC-04 — Consulta de Saldo, Tríade do UseCase e Endpoint GET /balance

**Input**: Design documents from `/specs/004-get-balance-action/`
**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`, `contracts/balance-endpoint-contract.md`, `quickstart.md`
**Tests**: Inclusos conforme especificado nos cenários BDD e requisitos mandatórios do `AGENTS.md` (TDD / Test-First).

---

## Phase 1: Setup (Infraestrutura de Exceções & Validação)

**Purpose**: Criação da exceção de domínio desacoplada e do FormRequest com tratamento de erro forçado para 422 JSON.

- [X] T001 [P] Implement domain exception AccountNotFoundException in `app/Exceptions/AccountNotFoundException.php`
- [X] T002 [P] Implement validation FormRequest GetBalanceRequest with failedValidation override in `app/Http/Requests/Account/GetBalanceRequest.php`

---

## Phase 2: Foundational (DTOs da Camada de Aplicação)

**Purpose**: Estruturar os Data Transfer Objects imutáveis da tríade do caso de uso antes da lógica de execução.

**⚠️ CRITICAL**: A implementação do caso de uso e dos testes depende diretamente da existência destes DTOs.

- [X] T003 [P] Implement immutable input DTO in `app/UseCases/Account/GetBalance/GetBalanceUseCaseInput.php`
- [X] T004 [P] Implement immutable output DTO in `app/UseCases/Account/GetBalance/GetBalanceUseCaseOutput.php`

**Checkpoint**: DTOs criados e tipados estritamente — implementação do caso de uso e testes pode prosseguir.

---

## Phase 3: User Story 1 - Get Balance UseCase (Priority: P1) 🎯 MVP

**Goal**: Consultar o estado atual de uma conta através do método `find()` de `AccountRepositoryInterface`, retornando DTO tipado de saída no sucesso ou lançando `AccountNotFoundException` se a conta não existir, sem efeitos colaterais de escrita.

**Independent Test**: `php artisan test --filter=GetBalanceUseCaseTest`

### Tests for User Story 1 (TDD) ⚠️
> **NOTE: Escrever os testes primeiro e verificar que falham antes de implementar o UseCase.**

- [X] T005 [P] [US1] Create unit test for GetBalanceUseCase with repository mock in `tests/Unit/UseCases/Account/GetBalanceUseCaseTest.php`

### Implementation for User Story 1
- [X] T006 [US1] Implement GetBalanceUseCase in `app/UseCases/Account/GetBalance/GetBalanceUseCase.php`

**Checkpoint**: `GetBalanceUseCaseTest` passando com 100% de sucesso. O caso de uso está concluído e testado unitariamente de forma isolada.

---

## Phase 4: User Story 2 - HTTP Endpoint GET /balance (Priority: P1)

**Goal**: Expor a consulta de saldo via endpoint HTTP `GET /balance` utilizando `GetBalanceRequest` e Single-Action Controller (`GetBalanceController`), retornando código HTTP `200 OK` com saldo numérico em `text/plain; charset=UTF-8`, `404 Not Found` com corpo `'0'` caso a conta não exista, ou `422 Unprocessable Content` em JSON caso o parâmetro `account_id` seja omitido ou inválido.

**Independent Test**: `php artisan test --filter=GetBalanceEndpointTest`

### Tests for User Story 2 (TDD) ⚠️
> **NOTE: Escrever os testes de feature primeiro cobrindo conta existente, conta inexistente, parâmetro ausente (422) e idempotência estrita.**

- [X] T007 [P] [US2] Create feature test for GET /balance endpoint in `tests/Feature/Account/GetBalanceEndpointTest.php`

### Implementation for User Story 2
- [X] T008 [US2] Implement Single-Action GetBalanceController in `app/Http/Controllers/Account/GetBalanceController.php`
- [X] T009 [US2] Register route Route::get('/balance', GetBalanceController::class) in `routes/api.php`

**Checkpoint**: `GetBalanceEndpointTest` passando com 100% de sucesso. O endpoint HTTP está integrado e funcional de ponta a ponta.

---

## Phase 5: Polish & Cross-Cutting Concerns

**Purpose**: Validação de qualidade, análise estática e ausência de regressões na base de código.

- [X] T010 [P] Format code with Laravel Pint in entire codebase
- [X] T011 Run static analysis with Larastan / PHPStan Level 6
- [X] T012 Run complete test suite and execute quickstart validation scenarios per `specs/004-get-balance-action/quickstart.md`

---

## Dependencies & Execution Order

### Phase Dependencies
- **Setup (Phase 1)**: Sem dependências — execução imediata.
- **Foundational (Phase 2)**: Depende do Setup — bloqueia a implementação do caso de uso e dos testes.
- **User Story 1 (Phase 3)**: Depende da Phase 2 (DTOs disponíveis e exceção pronta).
- **User Story 2 (Phase 4)**: Depende da Phase 3 (`GetBalanceUseCase` pronto para injeção no controller) e de `GetBalanceRequest` (Phase 1).
- **Polish (Phase 5)**: Depende da conclusão das fases 3 e 4.

### User Story Dependencies
- **User Story 1 (P1)**: Pode iniciar após a Phase 2 (Foundational). Sem dependência de outras histórias.
- **User Story 2 (P1)**: Depende da User Story 1 (`GetBalanceUseCase` e DTOs prontos para injeção no controller).

### Parallel Opportunities
- **Phase 1**: `T001` (`AccountNotFoundException`) e `T002` (`GetBalanceRequest`) podem ser criados em paralelo (arquivos distintos).
- **Phase 2**: `T003` (`GetBalanceUseCaseInput`) e `T004` (`GetBalanceUseCaseOutput`) podem ser criados em paralelo.
- **Phase 3**: `T005` (teste unitário) é escrito primeiro; `T006` implementa o UseCase.
- **Phase 4**: `T007` (teste de feature) é escrito primeiro; `T008` (Controller) e `T009` (Registro da rota) completam a entrega.
- **Phase 5**: `T010` (Pint) pode rodar em paralelo ou antes de `T011` e `T012`.

---

## Parallel Example: User Story 1 & 2

```bash
# Executar Setup e DTOs em paralelo:
Task: "Implement domain exception AccountNotFoundException in app/Exceptions/AccountNotFoundException.php"
Task: "Implement validation FormRequest GetBalanceRequest in app/Http/Requests/Account/GetBalanceRequest.php"
Task: "Implement immutable input DTO in app/UseCases/Account/GetBalance/GetBalanceUseCaseInput.php"
Task: "Implement immutable output DTO in app/UseCases/Account/GetBalance/GetBalanceUseCaseOutput.php"

# Executar testes em paralelo antes da implementação:
Task: "Create unit test for GetBalanceUseCase with repository mock in tests/Unit/UseCases/Account/GetBalanceUseCaseTest.php"
Task: "Create feature test for GET /balance endpoint in tests/Feature/Account/GetBalanceEndpointTest.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 - Núcleo de Aplicação)
1. Completar Setup (Phase 1: `T001`, `T002`) e Foundational (Phase 2: `T003`, `T004`).
2. Escrever e rodar `GetBalanceUseCaseTest` (Phase 3: `T005`).
3. Implementar `GetBalanceUseCase` (Phase 3: `T006`) e validar aprovação do teste unitário.
4. **Checkpoint MVP**: O caso de uso consulta saldo e lança `AccountNotFoundException` com mock de repositório.

### Entrega Completa (User Story 2 - Camada de Apresentação HTTP)
5. Escrever `GetBalanceEndpointTest` (Phase 4: `T007`).
6. Implementar `GetBalanceController` (Phase 4: `T008`) e mapear rota em `routes/api.php` (Phase 4: `T009`).
7. Validar todos os testes de feature HTTP (200, 404, 422, idempotência).
8. Executar suíte completa, Pint e Larastan (Phase 5: `T010`, `T011`, `T012`).
