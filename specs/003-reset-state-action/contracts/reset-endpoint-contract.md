# Contrato de Interface: POST /reset & ResetStateUseCase

Este documento formaliza os contratos da camada HTTP e da camada de aplicação para o endpoint de reset de estado.

---

## 1. Contrato HTTP: `POST /reset`

### Definição Geral
- **Método HTTP:** `POST`
- **URI:** `/reset`
- **Prefixos:** Nenhum (raiz da aplicação)
- **Middleware:** `api` (stateless, sem cookies de sessão, sem tokens CSRF)
- **Autenticação:** Nenhuma (endpoint público de ciclo de vida de testes)

### Requisição
- **Headers:** Não exige cabeçalhos específicos. Cabeçalhos arbitrários como `Accept` ou `Content-Type` são aceitos.
- **Query Parameters:** Nenhum esperado. Parâmetros enviados são ignorados permissivamente.
- **Request Body:** Vazio ou opcional. Se enviado (ex: `{}`, string vazia ou dados adicionais), é ignorado permissivamente sem gerar erro de validação.

### Resposta de Sucesso (`200 OK`)
- **Código HTTP:** `200`
- **Content-Type:** `text/plain; charset=UTF-8`
- **Body:** `OK`

Exemplo de resposta HTTP bruta:
```http
HTTP/1.1 200 OK
Content-Type: text/plain; charset=UTF-8

OK
```

### Respostas de Erro
- **`405 Method Not Allowed`:**
  - Disparado se o cliente tentar verbos não suportados na URI (ex: `GET /reset`, `PUT /reset`, `DELETE /reset`).
- **`500 Internal Server Error`:**
  - Disparado caso ocorra falha catastrófica de infraestrutura no driver de armazenamento (ex: Redis offline ou falha de disco SQLite).

---

## 2. Contrato de Aplicação: `ResetStateUseCase`

### Assinatura do Caso de Uso
```php
namespace App\UseCases\Account\ResetState;

use App\Repositories\Contracts\AccountRepositoryInterface;

class ResetStateUseCase
{
    public function __construct(
        private AccountRepositoryInterface $repository
    ) {}

    public function execute(ResetStateUseCaseInput $input): ResetStateUseCaseOutput;
}
```

### Invariantes e Pré-condições
1. O repositório injetado deve ser uma implementação válida de `AccountRepositoryInterface`.
2. A invocação de `execute()` chama `$this->repository->reset()` exatamente uma vez.
3. Retorna uma instância de `ResetStateUseCaseOutput` com `$success === true` e status `'OK'`.

