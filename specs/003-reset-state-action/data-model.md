# Data Model: SPEC-03 — Reset de Estado, Tríade do UseCase e Endpoint POST /reset

Este documento define os modelos de transferência de dados (DTOs) da tríade do caso de uso e a transição de estado promovida pela operação de reset no sistema.

---

## 1. DTO de Entrada: `ResetStateUseCaseInput`

- **Localização:** `app/UseCases/Account/ResetState/ResetStateUseCaseInput.php`
- **Mutabilidade:** Imutável (`readonly class`).
- **Finalidade:** Representa o sinal semântico de entrada para execução da limpeza de estado.

### Atributos e Métodos
```php
namespace App\UseCases\Account\ResetState;

final readonly class ResetStateUseCaseInput
{
    /**
     * @param array<string, mixed> $metadata Metadados opcionais da requisição
     */
    public function __construct(
        public array $metadata = []
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data = []): self
    {
        return new self($data);
    }
}
```

---

## 2. DTO de Saída: `ResetStateUseCaseOutput`

- **Localização:** `app/UseCases/Account/ResetState/ResetStateUseCaseOutput.php`
- **Mutabilidade:** Imutável (`readonly class`).
- **Finalidade:** Encapsula o resultado consolidado da execução do UseCase desacoplado de detalhes de transporte HTTP.

### Atributos e Métodos
```php
namespace App\UseCases\Account\ResetState;

final readonly class ResetStateUseCaseOutput
{
    public function __construct(
        public bool $success = true,
        public string $status = 'OK'
    ) {}

    public function toValue(): string
    {
        return $this->status;
    }
}
```

---

## 3. Transição de Estado do Domínio

A operação `ResetState` tem efeito global sobre o repositório de contas ativo (`AccountRepositoryInterface`), restaurando o sistema para seu estado original:

```mermaid
stateDiagram-v2
    [*] --> EstadoComDados: Contas criadas (ex: id=100 saldo=20, id=200 saldo=100)
    EstadoComDados --> Resetting: POST /reset aciona ResetStateUseCase
    Resetting --> EstadoVirgem: AccountRepositoryInterface::reset()
    EstadoVirgem --> [*]: Nenhuma conta cadastrada (find("100") -> null)
```

### Comportamento por Driver de Persistência
1. **Driver `cache`:**
   - Limpa todas as contas registradas no Cache (`Cache::flush()` ou expurgo das chaves rastreadas).
   - Qualquer consulta posterior retorna `null`.
2. **Driver `database` (SQLite):**
   - Trunca ou remove todos os registros da tabela `accounts` (`Account::query()->delete()`).
   - A tabela permanece estruturada, porém com 0 linhas.
3. **Driver `redis`:**
   - Itera pelo Set `accounts:index`, remove as chaves associadas e limpa o índice.
   - Qualquer consulta posterior retorna `null`.

