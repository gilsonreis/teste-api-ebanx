# Implementation Plan: SPEC-01 — Encapsulamento de Regras de Saldo, Invariantes e Mutações no Model Account

**Branch**: `001-model-invariants` (`feature/spec-01-model-invariants`) | **Date**: 2026-11-09 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/001-model-invariants/spec.md`

---

## Summary

Implementar o Model Eloquent `App\Models\Account` (`app/Models/Account.php`) como o centro de invariantes de domínio para contas financeiras e a exceção de domínio `App\Exceptions\InsufficientFundsException` (`app/Exceptions/InsufficientFundsException.php`), acompanhados da suíte de testes unitários `tests/Unit/Models/AccountTest.php`. O Model encapsula as operações semânticas `deposit(int|float $amount): void` e `withdraw(int|float $amount): void`, garantindo positividade estrita dos montantes (`\InvalidArgumentException`), proteção contra saldo insuficiente (`InsufficientFundsException`), inicialização padrão (`balance = 0`), chave primária alfanumérica (`$incrementing = false`, `$keyType = 'string'`) e ausência total de acoplamento com persistência ou HTTP.

---

## Technical Context

**Language/Version**: PHP `^8.3` (`declare(strict_types=1);` obrigatório em todos os arquivos)

**Primary Dependencies**: Laravel Framework `^13.17` (`Illuminate\Database\Eloquent\Model`)

**Storage**: N/A (execução estritamente em memória nesta especificação; persistência delegada futuramente à camada de Repositórios/UseCases)

**Testing**: PHPUnit `^12.5.12` (`php artisan test --filter=AccountTest`), Laravel Pint `^1.27` (`vendor/bin/pint --test`), Larastan/PHPStan `^3.14` Nível 6 (`vendor/bin/phpstan analyse`)

**Target Platform**: Servidor Linux/macOS com runtime PHP 8.3+

**Project Type**: Web Service / Backend API (Laravel) — Camada de Domínio (Model & Exceptions)

**Performance Goals**: Execução em memória em sub-milissegundos (`< 1ms` por mutação/consulta de instância, sem I/O)

**Constraints**:
- Proibido criar POPOs/Entidades paralelas fora do Eloquent Model (`AGENTS.md` §1.4).
- Proibido acoplar status HTTP ou payloads JSON à `InsufficientFundsException` (`AGENTS.md` §2 e `spec.md` §3).
- Proibido utilizar estruturas `else` / `elseif`; obrigatório uso de Guard Clauses (`AGENTS.md` §5.1).
- Conformidade com PHPStan Nível 6 (`phpstan.neon`) e Laravel Pint.

**Scale/Scope**: 3 arquivos de entrega (`app/Exceptions/InsufficientFundsException.php`, `app/Models/Account.php`, `tests/Unit/Models/AccountTest.php`) cobrindo 7 cenários Gherkin.

---

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

Como `.specify/memory/constitution.md` encontra-se com placeholders genéricos, os gates constitucionais regentes do projeto são extraídos da especificação canônica em `AGENTS.md`:

| Gate Arquitetural (`AGENTS.md`) | Status Pré-Pesquisa | Status Pós-Design (Phase 1) | Verificação Técnica |
| :--- | :--- | :--- | :--- |
| **1. Spec-Driven Development (SDD)** | PASS | PASS | Escopo reflete estritamente os requisitos e os 7 cenários Gherkin de `specs/001-model-invariants/spec.md`. |
| **2. YAGNI & Simplicidade Pragmática** | PASS | PASS | Sem métodos ou abstrações especulativas além de `deposit`, `withdraw` e configuração Eloquent de `id`/`balance`. |
| **3. Model Eloquent como Centro de Invariantes** | PASS | PASS | `App\Models\Account` estende `Illuminate\Database\Eloquent\Model` e encapsula toda a regra de negócio sem POPOs paralelos. |
| **4. Exceções de Domínio Agnósticas de HTTP** | PASS | PASS | `App\Exceptions\InsufficientFundsException` herda diretamente de `\Exception` sem códigos HTTP. |
| **5. Guard Clauses & Saída Antecipada** | PASS | PASS | Validações `$amount <= 0` e `$this->balance < $amount` disparam exceções imediatamente sem blocos `else`/`elseif`. |
| **6. Tipagem Estrita (`declare(strict_types=1);`)** | PASS | PASS | Todos os arquivos projetados exigem `declare(strict_types=1);`, tipos explícitos e PHPDoc compatível com Larastan nível 6. |

---

## Project Structure

### Documentation (this feature)

```text
specs/001-model-invariants/
├── spec.md                              # Especificação da feature (SPEC-01)
├── plan.md                              # Este plano de implementação (/speckit-plan)
├── research.md                          # Decisões arquiteturais e técnicas (Phase 0)
├── data-model.md                        # Modelo de dados, atributos e transições (Phase 1)
├── quickstart.md                        # Guia de validação e comandos de verificação (Phase 1)
├── contracts/
│   └── account-model-contract.md        # Contrato público da classe Account e exceções (Phase 1)
└── tasks.md                             # Lista de tarefas (gerada na próxima etapa via /speckit-tasks)
```

### Source Code (repository root)

```text
app/
├── Exceptions/
│   └── InsufficientFundsException.php   # Exceção de domínio agnóstica de HTTP
└── Models/
    └── Account.php                      # Eloquent Model com invariantes deposit() e withdraw()

tests/
└── Unit/
    └── Models/
        └── AccountTest.php              # Suíte unitária cobrindo os 7 cenários Gherkin em memória
```

**Structure Decision**: Segue rigorosamente a Estrutura Canônica de Diretórios definida na Seção 2 do `AGENTS.md` e na Seção 4 da `spec.md`, posicionando o Model em `app/Models/Account.php`, a exceção de domínio em `app/Exceptions/InsufficientFundsException.php` e os testes unitários isolados em `tests/Unit/Models/AccountTest.php`.

---

## Complexity Tracking

Nenhuma violação constitucional ou arquitetural identificada. Tabela não aplicável.
