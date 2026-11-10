# Contrato de Interface: POST /event & ProcessEventUseCase

Este documento formaliza os contratos da camada HTTP e da camada de aplicação para o endpoint de processamento de eventos financeiros.

---

## 1. Contrato HTTP: `POST /event`

### Definição Geral
- **Método HTTP:** `POST`
- **URI:** `/event`
- **Prefixos:** Nenhum (operando na raiz da aplicação)
- **Middleware:** `api` (stateless, sem sessão, sem tokens CSRF)
- **Headers Requeridos:** `Content-Type: application/json`

---

### Requisição por Tipo de Evento

#### 1. Depósito (`deposit`)
Cria uma nova conta ou incrementa o saldo de uma conta existente.
```http
POST /event HTTP/1.1
Content-Type: application/json

{
  "type": "deposit",
  "destination": "100",
  "amount": 10
}
```

#### 2. Saque (`withdraw`)
Debita valor de uma conta existente.
```http
POST /event HTTP/1.1
Content-Type: application/json

{
  "type": "withdraw",
  "origin": "100",
  "amount": 5
}
```

#### 3. Transferência (`transfer`)
Transfere fundos entre duas contas distintas (`origin !== destination`).
```http
POST /event HTTP/1.1
Content-Type: application/json

{
  "type": "transfer",
  "origin": "100",
  "amount": 15,
  "destination": "300"
}
```

---

### Respostas

#### 1. Sucesso — Depósito Realizado (`201 Created`)
- **Status Code:** `201`
- **Content-Type:** `application/json`
- **Body:**
```json
{
  "destination": {
    "id": "100",
    "balance": 10
  }
}
```

#### 2. Sucesso — Saque Realizado (`201 Created`)
- **Status Code:** `201`
- **Content-Type:** `application/json`
- **Body:**
```json
{
  "origin": {
    "id": "100",
    "balance": 15
  }
}
```

#### 3. Sucesso — Transferência Realizada (`201 Created`)
- **Status Code:** `201`
- **Content-Type:** `application/json`
- **Body:**
```json
{
  "origin": {
    "id": "100",
    "balance": 0
  },
  "destination": {
    "id": "300",
    "balance": 15
  }
}
```

#### 4. Falha de Negócio — Conta Inexistente ou Saldo Insuficiente (`404 Not Found`)
- **Status Code:** `404`
- **Content-Type:** `text/plain; charset=UTF-8`
- **Body:** Literal `0`.
- **Cenários aplicáveis:**
  - Saque em conta que não existe
  - Saque em conta com saldo inferior ao valor solicitado
  - Transferência onde a conta de origem não existe
  - Transferência onde a conta de origem não possui saldo suficiente

Exemplo bruto:
```http
HTTP/1.1 404 Not Found
Content-Type: text/plain; charset=UTF-8

0
```

#### 5. Validação — Payload Inválido ou Parâmetros Ausentes (`422 Unprocessable Content`)
- **Status Code:** `422`
- **Content-Type:** `application/json`
- **Cenários aplicáveis:**
  - `type` ausente ou diferente de `deposit`, `withdraw`, `transfer`
  - `amount` ausente, menor ou igual a zero, ou não numérico
  - `destination` ausente em `deposit` ou `transfer`
  - `origin` ausente em `withdraw` ou `transfer`
  - `destination === origin` em `transfer` (regra `different:origin`)

Exemplo bruto:
```http
HTTP/1.1 422 Unprocessable Content
Content-Type: application/json

{
  "message": "The destination and origin must be different.",
  "errors": {
    "destination": [
      "The destination and origin must be different."
    ]
  }
}
```

---

## 2. Contrato de Aplicação: `ProcessEventUseCase`

### Assinatura do Caso de Uso
```php
namespace App\UseCases\Account\ProcessEvent;

use App\Repositories\Contracts\AccountRepositoryInterface;

class ProcessEventUseCase
{
    public function __construct(
        private AccountRepositoryInterface $repository
    ) {}

    /**
     * @throws \App\Exceptions\AccountNotFoundException
     * @throws \App\Exceptions\InsufficientFundsException
     * @throws \InvalidArgumentException
     */
    public function execute(ProcessEventUseCaseInput $input): ProcessEventUseCaseOutput;
}
```

### Invariantes e Pré-condições
1. O repositório injetado deve implementar `AccountRepositoryInterface`.
2. Para `deposit`: busca ou instancia conta, invoca `$account->deposit($amount)`, persiste e retorna `destination`.
3. Para `withdraw`: busca conta de origem. Se `null`, lança `AccountNotFoundException`. Invoca `$account->withdraw($amount)`. Se saldo insuficiente, `$account->withdraw()` lança `InsufficientFundsException`. Persiste e retorna `origin`.
4. Para `transfer`:
   - Busca origem. Se `null`, lança `AccountNotFoundException`.
   - Debita origem via `$origin->withdraw($amount)`.
   - Busca ou instancia destino (`new Account(['id' => $input->destination, 'balance' => 0])`).
   - Credita destino via `$destination->deposit($amount)`.
   - Persiste `$origin` e `$destination`.
   - Retorna `origin` e `destination`.

