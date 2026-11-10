# SPEC-04: Consulta de Saldo, Tríade do UseCase e Endpoint GET /balance

> **Diretriz Mandatória para Agentes de IA e Engenheiros de Software:**  
> Antes de escrever ou refatorar qualquer linha de código referente a esta especificação, **leia obrigatoriamente o arquivo `agents.md` localizado na raiz do projeto**. As regras estabelecidas naquele documento — Single-Action Controllers (`__invoke`), agrupamento coeso por ação em `app/UseCases/{Domain}/{Action}/`, DTOs tipados e imutáveis (`Input` e `Output`), validação isolada via `FormRequest`, persistência via `AccountRepositoryInterface`, respostas HTTP com formato e status codes exatos (`200 OK` com saldo numérico em `text/plain` e `404 Not Found` com literal `0`), idempotência estrita sem efeito colateral e cobertura por testes unitários e de feature — devem ser seguidas de forma irrestrita.

---

## 1. Contexto e Objetivo Arquitetural

Esta especificação formaliza a implementação da ação de consulta de saldo (`GetBalance`), responsável por ler o estado atual de uma conta financeira a partir do seu identificador único.

O endpoint `GET /balance` atende às seguintes premissas críticas do take-home assignment do EBANX:
1. **Idempotência Estrita:** Operações de consulta sob o verbo `GET` jamais produzem efeito colateral, mutação de saldo ou criação implícita de contas. Consultas consecutivas a uma mesma conta inalterada devem produzir sempre o mesmo resultado.
2. **Tratamento de Contas Inexistentes:** Caso a conta solicitada não exista no repositório, a API deve responder imediatamente com status code `404 Not Found` e corpo literal `0` em `text/plain`.
3. **Respostas em Formato Texto Puro:** Quando a conta existir, a API retorna status `200 OK` e o valor numérico do saldo (ex: `20`) formatado como texto simples (`text/plain`).

---

## Clarifications

### Session 2026-11-10
- Q: Como as falhas de validação do GetBalanceRequest devem ser renderizadas quando a requisição não incluir o cabeçalho Accept: application/json? → A: Forçar sempre resposta JSON com código 422 para erros de validação em rotas de API (sobrescrevendo `failedValidation` no FormRequest para lançar `HttpResponseException` com payload JSON e status 422, ou configurando o renderizador global em `bootstrap/app.php`), garantindo retorno 422 mesmo para clientes sem cabeçalhos explícitos de API.
- Q: A documentação OpenAPI (docs/openapi.yaml) e a rota visual /docs para o endpoint GET /balance devem ser implementadas nesta entrega da SPEC-04? → A: Manter `docs/openapi.yaml` e a rota `/docs` explicitamente fora de escopo nesta entrega, concentrando esta especificação exclusivamente na implementação funcional, validação e testes automatizados do endpoint `GET /balance`.
- Q: Em qual arquivo de rotas o endpoint GET /balance deve ser registrado? → A: Registrar exclusivamente em `routes/api.php`, mantendo a consistência com o pipeline de API stateless sem CSRF configurado na SPEC-03 com `apiPrefix: ''`.

---

## 2. Contrato HTTP do Endpoint

- **Método HTTP:** `GET`
- **Rota:** `/balance` (mapeada em `routes/api.php` operando na raiz com `apiPrefix: ''`)
- **Query Parameters:**
  - `account_id` (obrigatório, string): Identificador da conta a ser consultada (ex: `/balance?account_id=1234`).
- **Respostas Esperadas:**

| Cenário | Status Code | Content-Type | Response Body |
| :--- | :--- | :--- | :--- |
| **Conta Encontrada** | `200 OK` | `text/plain; charset=UTF-8` | Representação textual do saldo (ex: `20` ou `20.5`) |
| **Conta Não Encontrada** | `404 Not Found` | `text/plain; charset=UTF-8` | `0` |
| **Parâmetro Ausente/Inválido** | `422 Unprocessable Content` | `application/json` | JSON de erros de validação padrão do Laravel |

---

## 3. Camada de Validação HTTP (FormRequest)

- **Localização:** `app/Http/Requests/Account/GetBalanceRequest.php`
- **Responsabilidade:** Valida e tipa os parâmetros da query string antes de atingir o controller.
- **Regras:**
  - `account_id`: Obrigatório (`required`), string (`string`), não vazio (`filled`).
- **Tratamento de Falha:** Sobrescreve o método `failedValidation(Validator $validator)` para lançar `HttpResponseException(response()->json([...], 422))`, garantindo resposta JSON com status 422 mesmo quando o cliente omitir o cabeçalho `Accept: application/json`.

---

## 4. Tríade do Caso de Uso (`app/UseCases/Account/GetBalance/`)

### 4.1 `GetBalanceUseCaseInput`
- **Responsabilidade:** Representa os dados validados de entrada para a ação.
- **Estrutura:** Classe `readonly` com tipagem estrita (`string $accountId`).
- **Métodos de Fábrica:** Método estático `fromRequest(GetBalanceRequest $request): self` ou `fromArray(array $data): self`.

### 4.2 `GetBalanceUseCaseOutput`
- **Responsabilidade:** Representa o resultado consolidado da consulta.
- **Estrutura:** Classe `readonly` com tipagem estrita contendo o saldo obtido (`public readonly int|float $balance`).
- **Métodos:** Fornece método `toValue(): string` ou `getBalance(): int|float` para serialização direta no Controller.

### 4.3 `GetBalanceUseCase`
- **Assinatura:** `public function execute(GetBalanceUseCaseInput $input): GetBalanceUseCaseOutput`
- **Injeção de Dependências:** Recebe exclusivamente `App\Repositories\Contracts\AccountRepositoryInterface`.
- **Orquestração e Guard Clauses:**
  1. Invoca `$account = $this->repository->find($input->accountId);`.
  2. Caso a conta retorne `null`, dispara imediatamente a exceção de domínio `App\Exceptions\AccountNotFoundException`.
  3. Caso encontrada, retorna uma instância de `GetBalanceUseCaseOutput(balance: $account->balance)`.
  4. Nenhuma operação de gravação ou escrita de repositório pode ser invocada neste fluxo.

---

## 5. Exceções de Domínio

- **`App\Exceptions\AccountNotFoundException`**
  - Herança de `\Exception`.
  - Agnóstica de protocolo HTTP (sem código de status embutido na classe).

---

## 6. Camada de Apresentação HTTP (Controller & Rota)

### 6.1 Single-Action Controller (`GetBalanceController`)
- **Localização:** `app/Http/Controllers/Account/GetBalanceController.php`
- **Método:** `public function __invoke(GetBalanceRequest $request, GetBalanceUseCase $useCase): Response`
- **Fluxo:**
  1. Cria o input DTO: `$input = GetBalanceUseCaseInput::fromArray($request->validated());`.
  2. Executa o UseCase dentro de bloco `try/catch`.
  3. No sucesso: `return response((string) $output->balance, 200)->header('Content-Type', 'text/plain');`.
  4. No `catch (AccountNotFoundException)`: `return response('0', 404)->header('Content-Type', 'text/plain');`.

### 6.2 Registro de Rota
- Registrada exclusivamente em `routes/api.php`: `Route::get('/balance', GetBalanceController::class);`.

---

## 7. Mapeamento de Arquivos da Entrega

```text
routes/
└── api.php (adição da rota Route::get('/balance', ...))

app/
├── Exceptions/
│   └── AccountNotFoundException.php
├── Http/
│   ├── Controllers/Account/
│   │   └── GetBalanceController.php
│   └── Requests/Account/
│       └── GetBalanceRequest.php
└── UseCases/Account/GetBalance/
    ├── GetBalanceUseCase.php
    ├── GetBalanceUseCaseInput.php
    └── GetBalanceUseCaseOutput.php

tests/
├── Unit/UseCases/Account/
│   └── GetBalanceUseCaseTest.php
└── Feature/Account/
    └── GetBalanceEndpointTest.php
```

---

## 8. Critérios de Aceitação e Cenários BDD (Gherkin)

### 8.1 Testes Unitários (`GetBalanceUseCaseTest.php`)
```gherkin
Feature: Get Balance UseCase

  Scenario: Consultar saldo de conta existente
    Given um repositório AccountRepositoryInterface com a conta "100" contendo saldo 20
    When o GetBalanceUseCase for executado com o accountId "100"
    Then o Output deve conter o saldo 20

  Scenario: Consultar saldo de conta inexistente dispara exceção de domínio
    Given um repositório AccountRepositoryInterface onde a conta "999" não existe
    When o GetBalanceUseCase for executado com o accountId "999"
    Then uma exceção AccountNotFoundException deve ser lançada
```

### 8.2 Testes de Feature HTTP (`GetBalanceEndpointTest.php`)
```gherkin
Feature: GET /balance Endpoint

  Scenario: Consultar saldo de conta existente com sucesso
    Given que exista uma conta "100" com saldo 20 salva no sistema
    When eu envio uma requisição GET para "/balance?account_id=100"
    Then o status da resposta deve ser 200
    And o cabeçalho "Content-Type" deve conter "text/plain"
    And o corpo da resposta deve ser "20"

  Scenario: Consultar saldo de conta inexistente retorna 404 com corpo 0
    Given que a conta "1234" não exista no sistema
    When eu envio uma requisição GET para "/balance?account_id=1234"
    Then o status da resposta deve ser 404
    And o cabeçalho "Content-Type" deve conter "text/plain"
    And o corpo da resposta deve ser "0"

  Scenario: Garantir idempotência estrita em múltiplas leituras
    Given que exista uma conta "100" com saldo 20 salva no sistema
    When eu envio três requisições GET consecutivas para "/balance?account_id=100"
    Then todas as três requisições devem retornar status 200 com corpo "20"
    And o estado da conta no repositório deve permanecer inalterado

  Scenario: Rejeitar requisição sem o parâmetro account_id
    When eu envio uma requisição GET para "/balance" sem parâmetros
    Then o status da resposta deve ser 422
```

---

## 9. Checklist de Validação Técnica e Aceite

- [x] Leu e aplicou os padrões estabelecidos no `agents.md` antes de codificar?
- [x] Criou a exceção de domínio desacoplada `App\Exceptions\AccountNotFoundException`?
- [x] Criou o `GetBalanceRequest` com regras estritas para `account_id`?
- [x] Implementou a tríade no diretório coeso `app/UseCases/Account/GetBalance/`?
- [x] Garantiu que o `GetBalanceUseCase` executa apenas leituras, sem efeitos colaterais de escrita?
- [x] Criou o `GetBalanceController` Single-Action tratando retornos `200` e `404` em `text/plain`?
- [x] Mapeou a rota `GET /balance` sem prefixos incorretos?
- [x] Criou e validou os testes de unidade em `GetBalanceUseCaseTest`?
- [x] Criou e validou os testes de Feature em `GetBalanceEndpointTest` cobrindo cenários de sucesso, 404, 422 e idempotência?
- [x] O código passou sem alertas no Laravel Pint (`vendor/bin/pint --test`) e no Larastan (`vendor/bin/phpstan analyse`)?
