# Phase 0 Research: Encapsulamento de Regras de Saldo, Invariantes e Mutações no Model Account

**Feature**: `001-model-invariants`  
**Date**: 2026-11-09  
**Spec**: `specs/001-model-invariants/spec.md`

---

## 1. Uso do Eloquent Model como Entidade de Domínio vs POPO Puro

- **Decision**: Utilizar `App\Models\Account` herdando diretamente de `Illuminate\Database\Eloquent\Model` com regras de negócio (`deposit`, `withdraw`) encapsuladas na própria classe e sem conexão obrigatória a banco de dados físico durante instanciação em memória.
- **Rationale**: O manual arquitetural (`AGENTS.md`, Seção 1.4 e Seção 3.3) determina explicitamente que o Model Eloquent padrão do Laravel centralize as regras de consistência, cálculos e transições de estado, evitando modelos anêmicos ou camadas redundantes de POPOs/Entidades fora do padrão do framework.
- **Alternatives considered**:
  - *POPO de domínio puro (`App\Domain\Entities\Account`) separado do Eloquent Model*: Rejeitado por violar diretamente a diretriz 4 do `AGENTS.md` (YAGNI e vedação a camadas redundantes de POPOs).
  - *Cálculo de saldo dentro dos UseCases/Services*: Rejeitado por caracterizar o antipadrão de Modelo Anêmico vedado na Seção 3.3 do `AGENTS.md`.

---

## 2. Tipagem, Casts e Atributos Default no Eloquent (`Laravel 13` + `Larastan Level 6`)

- **Decision**:
  - Declarar `declare(strict_types=1);` no topo de todos os arquivos PHP.
  - Configurar `$incrementing = false`, `$keyType = 'string'` e `$fillable = ['id', 'balance']`.
  - Definir `$attributes = ['balance' => 0]` para assegurar que qualquer instância criada sem `balance` explícito inicie com saldo `0`.
  - Definir o cast `'balance' => 'float'` via método `casts(): array` (padrão moderno do Laravel 11+/13, ou propriedade `$casts`) e documentar `@property string $id` e `@property int|float $balance` via PHPDoc na classe `Account` para compatibilidade irrestrita com `phpstan/larastan` em nível 6 (`phpstan.neon`).
- **Rationale**: Em Eloquent, quando um Model não possui tabela física inspecionável no banco durante a análise estática, o Larastan requer anotações `@property` explícitas para validar acessos a `$this->id` e `$this->balance` no nível 6 sem falsos positivos. Além disso, `$attributes = ['balance' => 0]` garante a invariante de inicialização mesmo sem passar pelo banco de dados.
- **Alternatives considered**:
  - *Inicializar `balance` apenas no construtor*: Sobrescrever `__construct` em Models Eloquent é desencorajado pelo próprio Laravel; o array `$attributes` nativo resolve a inicialização padrão com segurança em qualquer forma de instanciação (`new Account()`, `Account::make()`, etc.).

---

## 3. Tratamento de Exceções de Domínio e Guard Clauses

- **Decision**:
  - Lançar `\InvalidArgumentException` nativo do PHP imediatamente (guard clause) caso `$amount <= 0` tanto em `deposit(int|float $amount): void` quanto em `withdraw(int|float $amount): void`.
  - Criar `App\Exceptions\InsufficientFundsException` estendendo `\Exception` (sem dependências de `Symfony\Component\HttpKernel` ou `Illuminate\Http`), lançada em `withdraw()` quando `$this->balance < $amount`.
- **Rationale**: Cumpre a diretriz 5.1 (`Guard Clauses e Saída Antecipada`, sem `else`/`elseif`) e a Seção 3 da `spec.md`, mantendo a camada de domínio 100% agnóstica de protocolo HTTP.
- **Alternatives considered**:
  - *Retornar `bool` nos métodos `deposit`/`withdraw`*: Rejeitado porque a especificação exige assinatura `: void` e interrupção imediata de fluxo via exceções tipadas.

---

## 4. Isolamento da Suíte de Testes Unitários (`tests/Unit/Models/AccountTest.php`)

- **Decision**: Estender `PHPUnit\Framework\TestCase` (ou `Tests\TestCase` sem traits de banco de dados como `RefreshDatabase`) em `tests/Unit/Models/AccountTest.php`, instanciando `new Account(['id' => '100', 'balance' => 20])` diretamente em memória.
- **Rationale**: A Seção 5 da `spec.md` exige que a suíte unitária valide integralmente o comportamento do Model sem acoplamento a banco de dados físico ou HTTP, garantindo execução em poucos milissegundos.
- **Alternatives considered**:
  - *Usar `RefreshDatabase` ou SQLite em memória para testar o Model*: Desnecessário e mais lento, pois `Account` opera puramente em memória nesta especificação.

