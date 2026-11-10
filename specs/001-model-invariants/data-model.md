# Data Model: Account & Domain Exceptions

**Feature**: `001-model-invariants`  
**Date**: 2026-11-09  
**Spec**: `specs/001-model-invariants/spec.md`

---

## 1. Entidade `App\Models\Account`

Herda diretamente de `Illuminate\Database\Eloquent\Model`. Opera em memória como centro de invariantes de domínio para contas financeiras.

### 1.1 Propriedades de Configuração do Eloquent

| Propriedade / Método | Tipo | Valor | Finalidade |
| :--- | :--- | :--- | :--- |
| `$incrementing` | `bool` | `false` | Desativa auto-incremento numérico da chave primária. |
| `$keyType` | `string` | `'string'` | Define o tipo da chave primária como `string` alfanumérica. |
| `$fillable` | `list<string>` | `['id', 'balance']` | Permite atribuição em massa controlada apenas para `id` e `balance`. |
| `$attributes` | `array<string, mixed>` | `['balance' => 0]` | Garante que o saldo padrão seja `0` caso omitido na instanciação. |
| `casts()` / `$casts` | `array<string, string>` | `['balance' => 'float']` | Assegura representação numérica consistente de `balance` via Eloquent. |

### 1.2 Atributos de Domínio

| Atributo | Tipo | Obrigatório | Valor Padrão | Regras de Validação / Invariantes |
| :--- | :--- | :--- | :--- | :--- |
| `id` | `string` | Sim | — | Identificador alfanumérico único da conta (ex: `"100"`). |
| `balance` | `int\|float` (cast `float`) | Sim | `0` (`0.0`) | Nunca pode tornar-se negativo após uma operação de negócio (`balance >= 0`). |

---

## 2. Transições de Estado e Invariantes de Negócio

```text
[Instanciação: balance = B (default 0)]
       │
       ├──► deposit(amount)
       │      ├─ Se amount <= 0 ──► Lança \InvalidArgumentException (balance permanece B)
       │      └─ Se amount > 0  ──► balance = B + amount
       │
       └──► withdraw(amount)
              ├─ Se amount <= 0        ──► Lança \InvalidArgumentException (balance permanece B)
              ├─ Se balance < amount   ──► Lança App\Exceptions\InsufficientFundsException (balance permanece B)
              └─ Se balance >= amount  ──► balance = B - amount
```

### Regras de Consistência:
1. **Positividade Estrita de Operação**: Tanto `deposit()` quanto `withdraw()` rejeitam valores `<= 0` com `\InvalidArgumentException` antes de qualquer mutação.
2. **Proteção contra Saldo Negativo**: `withdraw()` verifica `$this->balance < $amount` via guard clause e dispara `App\Exceptions\InsufficientFundsException` sem alterar o estado da instância.
3. **Idempotência de Leitura**: Consultas a `$account->id` ou `$account->balance` jamais produzem efeitos colaterais no estado da entidade.
4. **Isolamento de Persistência**: Nenhuma transição de estado no Model invoca `save()`, banco de dados ou cache diretamente.

---

## 3. Exceção de Domínio `App\Exceptions\InsufficientFundsException`

| Elemento | Especificação |
| :--- | :--- |
| **Namespace / Classe** | `App\Exceptions\InsufficientFundsException` |
| **Herança** | `\Exception` |
| **Restrições Arquiteturais** | Sem acoplamento a códigos de status HTTP, headers ou formatação JSON. |

