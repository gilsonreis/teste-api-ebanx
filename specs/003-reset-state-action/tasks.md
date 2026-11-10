# Tasks: SPEC-03 — Reset de Estado, Tríade do UseCase e Endpoint POST /reset

**Input**: Design documents from `/specs/003-reset-state-action/`
**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`, `contracts/reset-endpoint-contract.md`, `quickstart.md`
**Tests**: Inclusos conforme especificado nos cenários BDD e requisitos mandatórios do `AGENTS.md` (TDD / Test-First).

---

## Phase 1: Setup (Infraestrutura de Roteamento)

**Purpose**: Habilitar o pipeline stateless de API no Laravel 11 com rotas na raiz (`apiPrefix: ''`).

- [X] T001 Configure API routing with empty prefix in `bootstrap/app.php`
- [X] T002 [P] Create empty API routing file in `routes/api.php`

---

## Phase 2: Foundational (DTOs da Camada de Aplicação)

**Purpose**: Estruturar os Data Transfer Objects imutáveis da tríade do caso de uso antes da lógica de execução.

**⚠️ CRITICAL**: A implementação do caso de uso e dos testes depende diretamente da existência destes DTOs.

- [X] T003 [P] Implement immutable input DTO in `app/UseCases/Account/ResetState/ResetStateUseCaseInput.php`
- [X] T004 [P] Implement immutable output DTO in `app/UseCases/Account/ResetState/ResetStateUseCaseOutput.php`

**Checkpoint**: DTOs criados e tipados estritamente — implementação do caso de uso e testes pode prosseguir.

---

## Phase 3: User Story 1 - Reset State UseCase (Priority: P1) 🎯 MVP

**Goal**: Orquestrar a execução do método `reset()` de `AccountRepositoryInterface` através do `ResetStateUseCase`, retornando um DTO tipado de saída de forma isolada do transporte HTTP.

**Independent Test**: `php artisan test --filter=ResetStateUseCaseTest`

### Tests for User Story 1 (TDD) ⚠️
> **NOTE: Escrever o teste primeiro e verificar que falha antes de implementar o UseCase.**

- [X] T005 [P] [US1] Create unit test for UseCase with repository mock in `tests/Unit/UseCases/Account/ResetStateUseCaseTest.php`

### Implementation for User Story 1
- [X] T006 [US1] Implement ResetStateUseCase in `app/UseCases/Account/ResetState/ResetStateUseCase.php`

**Checkpoint**: `ResetStateUseCaseTest` passando com 100% de sucesso. O caso de uso está concluído e testado unitariamente.

---

## Phase 4: User Story 2 - HTTP Endpoint POST /reset (Priority: P1)

**Goal**: Expor a ação de reset através do endpoint HTTP `POST /reset` utilizando Single-Action Controller (`ResetController`), retornando código HTTP `200 OK` com cabeçalho `Content-Type: text/plain; charset=UTF-8` e corpo `'OK'`.

**Independent Test**: `php artisan test --filter=ResetEndpointTest`

### Tests for User Story 2 (TDD) ⚠️
> **NOTE: Escrever os testes de feature primeiro cobrindo base limpa, limpeza de contas pré-existentes, tolerância a payloads e método inválido.**

- [X] T007 [P] [US2] Create feature test for POST /reset endpoint in `tests/Feature/Account/ResetEndpointTest.php`

### Implementation for User Story 2
- [X] T008 [US2] Implement Single-Action ResetController in `app/Http/Controllers/Account/ResetController.php`
- [X] T009 [US2] Register route Route::post('/reset', ResetController::class) in `routes/api.php`

**Checkpoint**: `ResetEndpointTest` passando com 100% de sucesso. O endpoint HTTP está integrado e funcional.

---

## Phase 5: Polish & Cross-Cutting Concerns

**Purpose**: Validação de qualidade, análise estática e ausência de regressões na base de código.

- [X] T010 [P] Format code with Laravel Pint in entire codebase
- [X] T011 Run static analysis with Larastan / PHPStan Level 6
- [X] T012 Run complete test suite and execute quickstart validation scenarios per `specs/003-reset-state-action/quickstart.md`

---

## Dependencies & Execution Order

### Phase Dependencies
- **Setup (Phase 1)**: Sem dependências — execução imediata.
- **Foundational (Phase 2)**: Depende do Setup — bloqueia a implementação do caso de uso e dos testes.
- **User Story 1 (Phase 3)**: Depende da Phase 2 (DTOs disponíveis).
- **User Story 2 (Phase 4)**: Depende da Phase 3 (`ResetStateUseCase` implementado para ser injetado no controller).
- **Polish (Phase 5)**: Depende da conclusão das fases 3 e 4.

### Parallel Opportunities
- **Phase 1**: `T002` pode ser executada em paralelo com `T001`.
- **Phase 2**: `T003` (`ResetStateUseCaseInput`) e `T004` (`ResetStateUseCaseOutput`) podem ser criados em paralelo (arquivos distintos, sem dependência mútua).
- **Phase 3**: `T005` (teste unitário) é escrito primeiro; `T006` implementa o UseCase.
- **Phase 4**: `T007` (teste de feature) é escrito primeiro; `T008` (Controller) e `T009` (Registro da rota) completam a entrega.
- **Phase 5**: `T010` (Pint) pode rodar antes ou em paralelo à análise estática `T011`.

---

## Implementation Strategy

### MVP First (User Story 1 - Núcleo de Aplicação)
1. Completar Setup (Phase 1) e Foundational (Phase 2).
2. Escrever e rodar `ResetStateUseCaseTest` (Phase 3 - T005).
3. Implementar `ResetStateUseCase` (Phase 3 - T006) e validar aprovação do teste unitário.

### Entrega Completa (User Story 2 - Camada de Apresentação HTTP)
4. Escrever `ResetEndpointTest` (Phase 4 - T007).
5. Implementar `ResetController` (Phase 4 - T008) e mapear rota em `routes/api.php` (Phase 4 - T009).
6. Executar suíte completa, Pint e Larastan (Phase 5 - T010, T011, T012).

