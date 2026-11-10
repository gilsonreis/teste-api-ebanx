# Contrato de Interface: GET /balance & GetBalanceUseCase

Este documento formaliza os contratos da camada HTTP e da camada de aplicação para o endpoint de consulta de saldo.

---

## 1. Contrato HTTP: `GET /balance`

### Definição Geral
- **Método HTTP:** `GET`
- **URI:** `/balance`
- **Prefixos:** Nenhum (raiz da aplicação)
- **Middleware:** `api` (stateless, sem cookies de sessão, sem tokens CSRF)
- **Autenticação:** Nenhuma (endpoint público de consulta)

### Requisição
- **Query Parameters:**
  - `account_id` (string, obrigatório): Identificador único da conta consultada. Exemplo: `account_id=1234`.
- **Request Body:** Nenhum (ignorado permissivamente se enviado em chamadas GET).

### Respostas

#### 1. Sucesso — Conta Encontrada (`200 OK`)
- **Status Code:** `200`
- **Content-Type:** `text/plain; charset=UTF-8`
- **Body:** String representando o valor numérico do saldo (ex: `20` ou `20.5`).

Exemplo bruto:
```http
HTTP/1.1 200 OK
Content-Type: text/plain; charset=UTF-8

20
```

#### 2. Negócio — Conta Inexistente (`404 Not Found`)
- **Status Code:** `404`
- **Content-Type:** `text/plain; charset=UTF-8`
- **Body:** Literal `0`.

Exemplo bruto:
```http
HTTP/1.1 404 Not Found
Content-Type: text/plain; charset=UTF-8

0
```

#### 3. Validação — Parâmetro Inválido ou Ausente (`422 Unprocessable Content`)
- **Status Code:** `422`
- **Content-Type:** `application/json`
- **Body:** JSON estruturado com os erros de validação do Laravel.

Exemplo bruto:
```http
HTTP/1.1 422 Unprocessable Content
Content-Type: application/json

{
  "message": "The account id field is required.",
  "errors": {
    "account_id": [
      "The account id field is required."
    ]
  }
}
```

#### 4. Transporte — Método Não Permitido (`405 Method Not Allowed`)
- Disparado caso o cliente tente verbos HTTP não suportados (ex: `PUT /balance`, `DELETE /balance`).

---

## 2. Contrato de Aplicação: `GetBalanceUseCase`

### Assinatura do Caso de Uso
```php
namespace App\UseCases\Account\GetBalance;

use App\Repositories\Contracts\AccountRepositoryInterface;

class GetBalanceUseCase
{
    public function __construct(
        private AccountRepositoryInterface $repository
    ) {}

    /**
     * @throws \App\Exceptions\AccountNotFoundException
     */
    public function execute(GetBalanceUseCaseInput $input): GetBalanceUseCaseOutput;
}
```

### Invariantes e Pré-condições
1. O repositório injetado deve ser uma implementação válida de `AccountRepositoryInterface`.
2. A invocação de `execute()` chama `$this->repository->find($input->accountId)` exatamente uma vez.
3. Se a conta existir, retorna uma instância de `GetBalanceUseCaseOutput` contendo `$account->balance`.
4. Se a conta não existir (`null`), lança `AccountNotFoundException`.
5. Nenhuma mutação de dados é permitida.

