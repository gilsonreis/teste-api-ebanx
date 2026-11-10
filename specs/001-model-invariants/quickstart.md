# Quickstart & Validation Guide: SPEC-01 (Model Account Invariants)

**Feature**: `001-model-invariants`  
**Spec**: `specs/001-model-invariants/spec.md`  
**Data Model**: `specs/001-model-invariants/data-model.md`  
**Contract**: `specs/001-model-invariants/contracts/account-model-contract.md`

---

## 1. Pré-requisitos

- PHP `^8.3` instalado e ativo no ambiente local.
- Dependências do Composer instaladas (`composer install`).
- Nenhuma configuração de banco de dados externo é necessária para esta feature, pois os testes unitários do Model operam 100% em memória.

---

## 2. Comandos de Validação e Execução

Após a implementação das tarefas definidas para a `SPEC-01`, execute a sequência abaixo a partir da raiz do projeto para validar todos os critérios de aceitação:

### 2.1 Executar a Suíte de Testes Unitários do Model `Account`

```bash
php artisan test --filter=AccountTest
```

**Resultado esperado**:
- Todos os 7 cenários de negócio especificados na seção Gherkin da `spec.md` devem passar (`PASSTests\Unit\Models\AccountTest`):
  1. Criação de conta com atributos válidos (`id` = `"100"`, `balance` = `20`).
  2. Depósito com valor positivo incrementa o saldo (`10 + 15 = 25`).
  3. Depósito com valor zero ou negativo lança `InvalidArgumentException` e preserva o saldo intacto.
  4. Saque com saldo suficiente debita o montante (`20 - 5 = 15`).
  5. Saque do valor integral zera o saldo (`15 - 15 = 0`).
  6. Saque com saldo insuficiente lança `App\Exceptions\InsufficientFundsException` e preserva o saldo intacto.
  7. Saque com valor zero ou negativo lança `InvalidArgumentException` e preserva o saldo inalterado.

### 2.2 Validar Padronização de Estilo (Laravel Pint)

```bash
vendor/bin/pint --test
```

**Resultado esperado**:
- Código de saída `0` (`PASS`), sem desvios de formatação ou ausência de `declare(strict_types=1);`.

### 2.3 Validar Análise Estática de Tipos (Larastan / PHPStan Nível 6)

```bash
vendor/bin/phpstan analyse
```

**Resultado esperado**:
- `[OK] No errors` ao analisar `app/` e `tests/` em nível 6 conforme `phpstan.neon`.

