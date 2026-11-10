---

description: "Task list for SPEC-01: Encapsulamento de Regras de Saldo, Invariantes e Mutações no Model Account"
---

# Tasks: SPEC-01 — Encapsulamento de Regras de Saldo, Invariantes e Mutações no Model Account

**Input**: Design documents from `/specs/001-model-invariants/`

**Prerequisites**: `plan.md` (required), `spec.md` (required for user stories), `research.md`, `data-model.md`, `contracts/account-model-contract.md`, `quickstart.md`

**Tests**: Incluídos obrigatoriamente conforme exigência explícita da `spec.md` (TDD / escrita prévia de testes unitários automatizados cobrindo os 7 cenários Gherkin em memória).

**Organization**: As tarefas estão agrupadas por história de usuário / capacidade de domínio para permitir implementação e validação incremental e independente.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Pode ser executada em paralelo (arquivos distintos, sem dependências pendentes)
- **[Story]**: Rótulo da história a que pertence a tarefa (`[US1]`, `[US2]`, `[US3]`)
- Caminhos exatos de arquivos incluídos em todas as descrições

## Path Conventions

- **Models**: `app/Models/`
- **Domain Exceptions**: `app/Exceptions/`
- **Unit Tests**: `tests/Unit/Models/`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Preparar a estrutura de diretórios exigida pelo plano de implementação.

- [X] T001 Ensure directories `app/Exceptions/` and `tests/Unit/Models/` exist for `app/Exceptions/InsufficientFundsException.php` and `tests/Unit/Models/AccountTest.php`

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Criar a exceção de domínio e o esqueleto da suíte de testes unitários antes de iniciar as histórias do Model `Account`.

**⚠️ CRITICAL**: Nenhuma história de usuário deve ser iniciada antes da conclusão desta fase.

- [X] T002 [P] Create domain exception `App\Exceptions\InsufficientFundsException` extending `\Exception` with `declare(strict_types=1);` and no HTTP coupling in `app/Exceptions/InsufficientFundsException.php`
- [X] T003 [P] Create unit test class skeleton `Tests\Unit\Models\AccountTest` with `declare(strict_types=1);` in `tests/Unit/Models/AccountTest.php`

**Checkpoint**: Infraestrutura base pronta — implementação incremental das histórias de usuário pode começar.

---

## Phase 3: User Story 1 - Identificação, Atributos e Estado Inicial da Conta (Priority: P1) 🎯 MVP

**Goal**: Garantir que o Model Eloquent `App\Models\Account` seja instanciável em memória com chave primária `id` alfanumérica (`string`, sem auto-incremento), atributo `balance` inicializado em `0` por padrão com cast para `float`, `$fillable = ['id', 'balance']` e leitura idempotente sem efeitos colaterais.

**Independent Test**: Executar `php artisan test --filter=AccountTest` validando o Cenário Gherkin 1 ("Criar conta com atributos válidos") e a inicialização padrão de `balance` em memória.

### Tests for User Story 1 ⚠️

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [X] T004 [US1] Write unit test for creating an `Account` instance with `id` `"100"` and `balance` `20` (plus default balance `0` and idempotent attribute reads) in `tests/Unit/Models/AccountTest.php`

### Implementation for User Story 1

- [X] T005 [US1] Create `App\Models\Account` extending `Illuminate\Database\Eloquent\Model` with `declare(strict_types=1);`, `$incrementing = false`, `$keyType = 'string'`, `$fillable = ['id', 'balance']`, `$attributes = ['balance' => 0]`, `casts()` returning `['balance' => 'float']`, and PHPDoc `@property` annotations in `app/Models/Account.php`

**Checkpoint**: `Account` pode ser instanciada e consultada de forma idempotente em memória com tipagem estrita.

---

## Phase 4: User Story 2 - Operação de Depósito e Invariantes de Positividade (Priority: P1)

**Goal**: Encapsular a operação `deposit(int|float $amount): void` no Model `Account`, incrementando o saldo apenas para valores estritamente positivos (`$amount > 0`) e rejeitando valores `<= 0` com `\InvalidArgumentException` sem alterar o saldo.

**Independent Test**: Executar `php artisan test --filter=AccountTest` validando os Cenários Gherkin 2 ("Depósito com valor positivo incrementa saldo") e 3 ("Depósito com valor não positivo deve ser rejeitado").

### Tests for User Story 2 ⚠️

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [X] T006 [US2] Write unit tests for `deposit()` with positive amount (`10` + `15` = `25`) and non-positive amounts (`0` and negative values throwing `\InvalidArgumentException` while preserving balance `10`) in `tests/Unit/Models/AccountTest.php`

### Implementation for User Story 2

- [X] T007 [US2] Implement `deposit(int|float $amount): void` with guard clause throwing `\InvalidArgumentException` when `$amount <= 0` and incrementing `$this->balance` in `app/Models/Account.php`

**Checkpoint**: Operação `deposit()` validada isoladamente para fluxos felizes e de borda.

---

## Phase 5: User Story 3 - Operação de Saque e Proteção contra Saldo Insuficiente (Priority: P1)

**Goal**: Encapsular a operação `withdraw(int|float $amount): void` no Model `Account`, garantindo validação de positividade (`\InvalidArgumentException` se `$amount <= 0`), verificação de fundos suficientes (`App\Exceptions\InsufficientFundsException` se `$this->balance < $amount`) e dedução exata do saldo interno (incluindo saque integral que zera o saldo).

**Independent Test**: Executar `php artisan test --filter=AccountTest` validando os Cenários Gherkin 4 ("Saque com saldo suficiente debita o montante"), 5 ("Saque do valor integral zera o saldo"), 6 ("Saque com saldo insuficiente dispara exceção de domínio") e 7 ("Saque com valor não positivo deve ser rejeitado").

### Tests for User Story 3 ⚠️

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [X] T008 [US3] Write unit tests for `withdraw()` covering partial withdrawal (`20` - `5` = `15`), full withdrawal (`15` - `15` = `0`), insufficient funds (`10` - `15` throwing `App\Exceptions\InsufficientFundsException` and keeping balance `10`), and non-positive amounts (`0` and negative values throwing `\InvalidArgumentException` and keeping balance `20`) in `tests/Unit/Models/AccountTest.php`

### Implementation for User Story 3

- [X] T009 [US3] Implement `withdraw(int|float $amount): void` with guard clauses for `$amount <= 0` (`\InvalidArgumentException`) and `$this->balance < $amount` (`App\Exceptions\InsufficientFundsException`) before decrementing `$this->balance` in `app/Models/Account.php`

**Checkpoint**: Todas as invariantes de domínio do Model `Account` e os 7 cenários Gherkin estão implementados e testados.

---

## Phase 6: Polish & Cross-Cutting Concerns

**Purpose**: Garantir conformidade integral com o Checklist de Validação Técnica e Aceite da `spec.md` e do `AGENTS.md`.

- [X] T010 Run unit test suite `php artisan test --filter=AccountTest` to verify 100% pass rate for `tests/Unit/Models/AccountTest.php`
- [X] T011 [P] Run Laravel Pint style check `vendor/bin/pint --test` on `app/Exceptions/InsufficientFundsException.php`, `app/Models/Account.php`, and `tests/Unit/Models/AccountTest.php`
- [X] T012 [P] Run PHPStan/Larastan static analysis `vendor/bin/phpstan analyse` using `phpstan.neon` across `app/` and `tests/`

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: Sem dependências — início imediato (`T001`).
- **Foundational (Phase 2)**: Depende de `T001` — `T002` e `T003` podem rodar em paralelo (`[P]`) e bloqueiam o início das histórias de usuário.
- **User Story 1 (Phase 3 - MVP)**: Depende da Phase 2 (`T002`, `T003`) — cria o Model `app/Models/Account.php` (`T004` → `T005`).
- **User Story 2 (Phase 4)**: Depende da User Story 1 (`T005`) por estender o mesmo arquivo `app/Models/Account.php` e `tests/Unit/Models/AccountTest.php` (`T006` → `T007`).
- **User Story 3 (Phase 5)**: Depende da User Story 1 (`T005`) e de `T002` (`InsufficientFundsException`), sendo executada sequencialmente após US2 (`T008` → `T009`) por atuar nos mesmos arquivos `app/Models/Account.php` e `tests/Unit/Models/AccountTest.php`.
- **Polish (Phase 6)**: Depende da conclusão de todas as histórias (`T009`) — `T011` e `T012` podem rodar em paralelo (`[P]`).

### Parallel Opportunities

- **Phase 2 (Foundational)**: `T002` (`app/Exceptions/InsufficientFundsException.php`) e `T003` (`tests/Unit/Models/AccountTest.php`) atuam em arquivos distintos e podem ser executadas em paralelo.
- **Phase 6 (Polish)**: `T011` (`vendor/bin/pint --test`) e `T012` (`vendor/bin/phpstan analyse`) são verificações somente-leitura independentes e podem rodar em paralelo após `T010`.

---

## Parallel Example: Foundational & Polish Phases

```bash
# Phase 2 - Execução paralela de arquivos independentes:
Task T002: "Create domain exception App\Exceptions\InsufficientFundsException in app/Exceptions/InsufficientFundsException.php"
Task T003: "Create unit test class skeleton Tests\Unit\Models\AccountTest in tests/Unit/Models/AccountTest.php"

# Phase 6 - Execução paralela dos quality gates:
Task T011: "Run Laravel Pint style check vendor/bin/pint --test"
Task T012: "Run PHPStan/Larastan static analysis vendor/bin/phpstan analyse"
```

---

## Implementation Strategy

### MVP First (User Story 1)

1. Concluir Phase 1 (`T001`) e Phase 2 (`T002`, `T003`).
2. Concluir Phase 3 (`T004` → `T005`) para disponibilizar a entidade `Account` com configuração Eloquent e estado inicial íntegro.
3. Validar isoladamente com `php artisan test --filter=AccountTest`.

### Incremental TDD Delivery (US1 → US2 → US3)

1. **US1**: Teste de instanciação/atributos (`T004`) → Implementação base do Model (`T005`).
2. **US2**: Testes de `deposit()` (`T006`) → Implementação de `deposit()` com guard clause (`T007`).
3. **US3**: Testes de `withdraw()` (`T008`) → Implementação de `withdraw()` com guard clauses (`T009`).
4. **Quality Gates**: PHPUnit (`T010`), Pint (`T011`) e Larastan nível 6 (`T012`).

