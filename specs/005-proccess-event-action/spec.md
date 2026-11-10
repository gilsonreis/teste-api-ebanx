# SPEC-05: Processamento de Eventos Financeiros, Tríade do UseCase e Endpoint POST /event

> **Diretriz Mandatória para Agentes de IA e Engenheiros de Software:**  
> Antes de escrever ou refatorar qualquer linha de código referente a esta especificação, **leia obrigatoriamente o arquivo `agents.md` localizado na raiz do projeto**. As regras estabelecidas naquele documento — Single-Action Controllers (`__invoke`), organização coesa em `app/UseCases/{Domain}/{Action}/`, DTOs tipados e imutáveis (`Input` e `Output`), validação via `FormRequest`, regras e mutações de negócio encapsuladas nos métodos do Model Eloquent `Account`, persistência via `AccountRepositoryInterface`, atomicidade estrita em transferências, respostas HTTP exatas (`201 Created` com JSON e `404 Not Found` com literal `0` em `text/plain`) e cobertura exaustiva por testes unitários e de feature — devem ser seguidas de forma irrestrita.

---

## 1. Contexto e Objetivo Arquitetural

Esta especificação formaliza a implementação do núcleo transacional da aplicação: o processamento de eventos financeiros (`ProcessEvent`) através do endpoint `POST /event`.

O endpoint atende às três operações financeiras previstas no take-home assignment do EBANX:
1. **`deposit` (Depósito):** Cria uma nova conta com o saldo inicial ou incrementa o saldo de uma conta existente.
2. **`withdraw` (Saque):** Debita um valor de uma conta existente. Caso a conta de origem não exista (ou não tenha fundos), a operação é rejeitada.
3. **`transfer` (Transferência):** Move fundos entre duas contas (origem e destino). A operação deve ser estritamente atômica: se a conta de origem não existir ou não tiver saldo suficiente, nenhuma alteração pode ser persistida e o destino não é criado/alterado.

O UseCase orquestra as chamadas aos métodos semânticos do Model `Account` (`$account->deposit()`, `$account->withdraw()`) e persiste os estados alterados via `AccountRepositoryInterface`.

---

## Clarifications

### Session 2026-11-10
- Q: Como o endpoint POST /event deve responder quando uma solicitação de saque (withdraw) ou transferência (transfer) for realizada a partir de uma conta existente, porém com saldo insuficiente? → A: Retornar HTTP 404 Not Found com corpo literal 0 em text/plain; charset=UTF-8, tratando InsufficientFundsException de forma idêntica à conta inexistente (AccountNotFoundException), sem produzir efeitos colaterais ou mutações de saldo.
- Q: Como o endpoint POST /event deve se comportar caso uma transferência (transfer) informe o mesmo identificador de conta na origem e no destino (origin === destination)? → A: Rejeitar na camada de validação (ProcessEventRequest) utilizando a regra different:origin para o campo destination quando type for transfer, retornando HTTP 422 Unprocessable Content com payload JSON de erro de validação.
- Q: Como as falhas de validação do ProcessEventRequest devem ser renderizadas caso o cliente HTTP omita o cabeçalho Accept: application/json? → A: Forçar sempre resposta JSON com código 422 sobrescrevendo failedValidation no ProcessEventRequest para lançar HttpResponseException com payload JSON e status 422, garantindo consistência com o padrão adotado no GetBalanceRequest.
- Q: A documentação OpenAPI (docs/openapi.yaml) e a rota /docs para o endpoint POST /event devem ser implementadas nesta entrega da SPEC-05? → A: Manter docs/openapi.yaml e a rota /docs explicitamente fora de escopo nesta entrega da SPEC-05 (seguindo o precedente da SPEC-04), concentrando o escopo exclusivamente na implementação funcional, validações, UseCase e cobertura de testes automatizados.
- Q: Em qual arquivo de rotas o endpoint POST /event deve ser registrado? → A: Registrar exclusivamente em routes/api.php, mantendo a consistência com o pipeline de API stateless sem CSRF configurado na SPEC-03 com apiPrefix: ''.

---

## 2. Contrato HTTP do Endpoint

- **Método HTTP:** `POST`
- **Rota:** `/event` (mapeada exclusivamente em `routes/api.php` operando na raiz com `apiPrefix: ''`)
- **Headers:** `Content-Type: application/json`, `Accept: application/json`

### 2.1 Payloads de Requisição por Tipo de Evento

#### A. Depósito (`deposit`)
```json
{
  "type": "deposit",
  "destination": "100",
  "amount": 10
}
```

#### B. Saque (`withdraw`)
```json
{
  "type": "withdraw",
  "origin": "100",
  "amount": 5
}
```

#### C. Transferência (`transfer`)
```json
{
  "type": "transfer",
  "origin": "100",
  "amount": 15,
  "destination": "300"
}
```

### 2.2 Respostas Esperadas

| Operação / Cenário | Status Code | Content-Type | Response Body |
| :--- | :--- | :--- | :--- |
| **Depósito (Criar conta ou incrementar)** | `201 Created` | `application/json` | `{"destination": {"id": "100", "balance": 10}}` |
| **Saque (Conta existente)** | `201 Created` | `application/json` | `{"origin": {"id": "100", "balance": 15}}` |
| **Saque (Conta inexistente ou saldo insuficiente)** | `404 Not Found` | `text/plain; charset=UTF-8` | `0` |
| **Transferência (Origem existente com saldo)** | `201 Created` | `application/json` | `{"origin": {"id": "100", "balance": 0}, "destination": {"id": "300", "balance": 15}}` |
| **Transferência (Origem inexistente ou saldo insuficiente)** | `404 Not Found` | `text/plain; charset=UTF-8` | `0` |
| **Payload inválido / Falha de validação** | `422 Unprocessable Content` | `application/json` | JSON com lista de erros de validação |

---

## 3. Camada de Validação HTTP (FormRequest)

- **Localização:** `app/Http/Requests/Account/ProcessEventRequest.php`
- **Responsabilidade:** Valida rigorosamente a estrutura, tipos e condicionalidades de campos com base no `type`.
- **Regras de Validação:**
  - `type`: Obrigatório (`required`), string, dentro dos valores permitidos (`in:deposit,withdraw,transfer`).
  - `amount`: Obrigatório (`required`), numérico (`numeric`), maior que zero (`gt:0`).
  - `destination`: Obrigatório se `type` for `deposit` ou `transfer` (`required_if:type,deposit,transfer`), string (`string`), e obrigatoriamente diferente de `origin` em eventos de transferência (`different:origin`).
  - `origin`: Obrigatório se `type` for `withdraw` ou `transfer` (`required_if:type,withdraw,transfer`), string (`string`).
- **Tratamento de Falha:** Sobrescreve o método `failedValidation(Validator $validator)` para lançar `HttpResponseException(response()->json([...], 422))`, garantindo resposta JSON com status 422 mesmo quando o cliente omitir o cabeçalho `Accept: application/json`.

---

## 4. Tríade do Caso de Uso (`app/UseCases/Account/ProcessEvent/`)

### 4.1 `ProcessEventUseCaseInput`
- **Responsabilidade:** DTO imutável de entrada para o caso de uso.
- **Propriedades:** `public readonly string $type`, `public readonly int|float $amount`, `public readonly ?string $origin = null`, `public readonly ?string $destination = null`.
- **Fábrica:** Método estático `fromArray(array $data): self` para hidratação direta a partir dos dados validados do `ProcessEventRequest`.

### 4.2 `ProcessEventUseCaseOutput`
- **Responsabilidade:** DTO imutável de retorno contendo os estados consolidados das contas envolvidas.
- **Propriedades:** `public readonly ?array $origin = null`, `public readonly ?array $destination = null`.
  - Onde `$origin` e `$destination` contêm a estrutura `['id' => string, 'balance' => int|float]`.
- **Métodos:** `toArray(): array` filtrando nós nulos para garantir a serialização exata exigida pelo contrato (`origin`, `destination` ou ambos).

### 4.3 `ProcessEventUseCase`
- **Assinatura:** `public function execute(ProcessEventUseCaseInput $input): ProcessEventUseCaseOutput`
- **Injeção de Dependências:** Recebe exclusivamente `App\Repositories\Contracts\AccountRepositoryInterface`.
- **Fluxo Operacional por Tipo:**

#### Fluxo `deposit`:
1. Busca conta de destino via `$this->repository->find($input->destination)`.
2. Se inexistente, instancia uma nova conta `new Account(['id' => $input->destination, 'balance' => 0])`.
3. Executa a mutação de negócio `$account->deposit($input->amount)`.
4. Persiste via `$this->repository->save($account)`.
5. Retorna `ProcessEventUseCaseOutput` contendo apenas o nó `destination`.

#### Fluxo `withdraw`:
1. Busca conta de origem via `$this->repository->find($input->origin)`.
2. Se inexistente, lança `App\Exceptions\AccountNotFoundException`.
3. Executa a mutação de negócio `$account->withdraw($input->amount)` (o Model lança `InsufficientFundsException` caso o saldo seja insuficiente).
4. Persiste via `$this->repository->save($account)`.
5. Retorna `ProcessEventUseCaseOutput` contendo apenas o nó `origin`.

#### Fluxo `transfer` (Atomicidade):
1. Busca conta de origem via `$this->repository->find($input->origin)`.
2. Se inexistente, lança `App\Exceptions\AccountNotFoundException`.
3. Executa `$origin->withdraw($input->amount)` (garante que a origem possui saldo antes de tocar no destino).
4. Busca a conta de destino via `$this->repository->find($input->destination)` ou instancia nova caso não exista (`balance => 0`).
5. Executa `$destination->deposit($input->amount)`.
6. Persiste ambas as contas via `$this->repository->save($origin)` e `$this->repository->save($destination)`.
7. Retorna `ProcessEventUseCaseOutput` contendo os nós `origin` e `destination`.

---

## 5. Camada de Apresentação HTTP (Controller & Rota)

### 5.1 Single-Action Controller (`ProcessEventController`)
- **Localização:** `app/Http/Controllers/Account/ProcessEventController.php`
- **Método:** `public function __invoke(ProcessEventRequest $request, ProcessEventUseCase $useCase): JsonResponse|Response`
- **Fluxo:**
  1. Cria o input DTO: `$input = ProcessEventUseCaseInput::fromArray($request->validated());`.
  2. Executa o UseCase dentro de bloco `try/catch`.
  3. No sucesso: `return response()->json($output->toArray(), 201);`.
  4. No `catch (AccountNotFoundException | InsufficientFundsException)`: `return response('0', 404)->header('Content-Type', 'text/plain');`.

### 5.2 Registro de Rota
- Registrada exclusivamente em `routes/api.php`: `Route::post('/event', ProcessEventController::class);`.

---

## 6. Mapeamento de Arquivos da Entrega

```text
routes/
└── api.php (adição da rota Route::post('/event', ...))

app/
├── Http/
│   ├── Controllers/Account/
│   │   └── ProcessEventController.php
│   └── Requests/Account/
│       └── ProcessEventRequest.php
└── UseCases/Account/ProcessEvent/
    ├── ProcessEventUseCase.php
    ├── ProcessEventUseCaseInput.php
    └── ProcessEventUseCaseOutput.php

tests/
├── Unit/UseCases/Account/
│   └── ProcessEventUseCaseTest.php
└── Feature/Account/
    └── ProcessEventEndpointTest.php
```

---

## 7. Critérios de Aceitação e Cenários BDD (Gherkin)

### 7.1 Testes Unitários (`ProcessEventUseCaseTest.php`)
```gherkin
Feature: Process Event UseCase

  Scenario: Criar conta com depósito inicial
    Given um repositório onde a conta "100" não existe
    When o UseCase processar um evento "deposit" com destination "100" e amount 10
    Then o repositório deve salvar a conta "100" com saldo 10
    And o Output deve conter destination com id "100" e balance 10

  Scenario: Incrementar saldo de conta existente com depósito
    Given um repositório com a conta "100" contendo saldo 10
    When o UseCase processar um evento "deposit" com destination "100" e amount 10
    Then a conta "100" deve ser salva com saldo 20

  Scenario: Saque de conta existente com sucesso
    Given um repositório com a conta "100" contendo saldo 20
    When o UseCase processar um evento "withdraw" com origin "100" e amount 5
    Then a conta "100" deve ser salva com saldo 15
    And o Output deve conter origin com id "100" e balance 15

  Scenario: Saque de conta inexistente dispara exceção de domínio
    Given um repositório onde a conta "200" não existe
    When o UseCase processar um evento "withdraw" com origin "200" e amount 10
    Then uma exceção AccountNotFoundException deve ser lançada

  Scenario: Saque de conta existente com saldo insuficiente dispara exceção de domínio
    Given um repositório com a conta "100" contendo saldo 5
    When o UseCase processar um evento "withdraw" com origin "100" e amount 10
    Then uma exceção InsufficientFundsException deve ser lançada

  Scenario: Transferência entre contas com sucesso
    Given um repositório com a conta de origem "100" contendo saldo 15
    When o UseCase processar um evento "transfer" de origin "100" para destination "300" com amount 15
    Then a conta de origem "100" deve ter saldo final 0
    And a conta de destino "300" deve ter saldo final 15
    And o Output deve conter origin com balance 0 e destination com balance 15

  Scenario: Transferência com conta de origem inexistente falha sem efeitos colaterais
    Given um repositório onde a conta de origem "200" não existe
    When o UseCase processar um evento "transfer" de origin "200" para destination "300" com amount 15
    Then uma exceção AccountNotFoundException deve ser lançada
    And a conta de destino "300" não deve ser criada no repositório

  Scenario: Transferência com saldo insuficiente na conta de origem falha sem efeitos colaterais
    Given um repositório com a conta de origem "100" contendo saldo 5
    When o UseCase processar um evento "transfer" de origin "100" para destination "300" com amount 15
    Then uma exceção InsufficientFundsException deve ser lançada
    And a conta de origem "100" deve permanecer com saldo 5
    And a conta de destino "300" não deve ser criada no repositório
```

### 7.2 Testes de Feature HTTP (`ProcessEventEndpointTest.php`)
```gherkin
Feature: POST /event Endpoint (EBANX Specification)

  Scenario: Criar conta com saldo inicial (201 Created)
    When eu envio uma requisição POST para "/event" com:
      """
      {"type": "deposit", "destination": "100", "amount": 10}
      """
    Then o status da resposta deve ser 201
    And o corpo da resposta deve ser exatamente:
      """
      {"destination": {"id": "100", "balance": 10}}
      """

  Scenario: Depositar em conta existente (201 Created)
    Given que a conta "100" exista com saldo 10
    When eu envio uma requisição POST para "/event" com:
      """
      {"type": "deposit", "destination": "100", "amount": 10}
      """
    Then o status da resposta deve ser 201
    And o corpo da resposta deve ser exatamente:
      """
      {"destination": {"id": "100", "balance": 20}}
      """

  Scenario: Sacar de conta inexistente (404 Not Found com corpo 0)
    Given que a conta "200" não exista
    When eu envio uma requisição POST para "/event" com:
      """
      {"type": "withdraw", "origin": "200", "amount": 10}
      """
    Then o status da resposta deve ser 404
    And o cabeçalho "Content-Type" deve conter "text/plain"
    And o corpo da resposta deve ser "0"

  Scenario: Sacar de conta existente com saldo insuficiente (404 Not Found com corpo 0)
    Given que a conta "100" exista com saldo 5
    When eu envio uma requisição POST para "/event" com:
      """
      {"type": "withdraw", "origin": "100", "amount": 10}
      """
    Then o status da resposta deve ser 404
    And o cabeçalho "Content-Type" deve conter "text/plain"
    And o corpo da resposta deve ser "0"

  Scenario: Sacar de conta existente (201 Created)
    Given que a conta "100" exista com saldo 20
    When eu envio uma requisição POST para "/event" com:
      """
      {"type": "withdraw", "origin": "100", "amount": 5}
      """
    Then o status da resposta deve ser 201
    And o corpo da resposta deve ser exatamente:
      """
      {"origin": {"id": "100", "balance": 15}}
      """

  Scenario: Transferência de conta existente (201 Created)
    Given que a conta "100" exista com saldo 15
    When eu envio uma requisição POST para "/event" com:
      """
      {"type": "transfer", "origin": "100", "amount": 15, "destination": "300"}
      """
    Then o status da resposta deve ser 201
    And o corpo da resposta deve ser exatamente:
      """
      {"origin": {"id": "100", "balance": 0}, "destination": {"id": "300", "balance": 15}}
      """

  Scenario: Transferência de conta inexistente (404 Not Found com corpo 0)
    Given que a conta "200" não exista
    When eu envio uma requisição POST para "/event" com:
      """
      {"type": "transfer", "origin": "200", "amount": 15, "destination": "300"}
      """
    Then o status da resposta deve ser 404
    And o cabeçalho "Content-Type" deve conter "text/plain"
    And o corpo da resposta deve ser "0"

  Scenario: Transferência de conta existente com saldo insuficiente (404 Not Found com corpo 0)
    Given que a conta "100" exista com saldo 5
    When eu envio uma requisição POST para "/event" com:
      """
      {"type": "transfer", "origin": "100", "amount": 15, "destination": "300"}
      """
    Then o status da resposta deve ser 404
    And o cabeçalho "Content-Type" deve conter "text/plain"
    And o corpo da resposta deve ser "0"

  Scenario: Rejeitar transferência com mesma conta de origem e destino (422 Unprocessable Content)
    When eu envio uma requisição POST para "/event" com:
      """
      {"type": "transfer", "origin": "100", "amount": 15, "destination": "100"}
      """
    Then o status da resposta deve ser 422
```

---

## 8. Checklist de Validação Técnica e Aceite

- [x] Leu e seguiu rigorosamente os padrões conceituais do `agents.md`? [cite: 3]
- [x] Criou o `ProcessEventRequest` validando condicionalmente `origin`, `destination` e `amount` conforme o `type`? [cite: 3]
- [x] Implementou a tríade no diretório coeso `app/UseCases/Account/ProcessEvent/` (`UseCase`, `Input`, `Output`)? [cite: 3]
- [x] As mutações de saldo são executadas exclusivamente via métodos `$account->deposit()` e `$account->withdraw()`? [cite: 3]
- [x] Garantiu atomicidade estrita na transferência (origem validada e debitada antes de tocar no destino)? [cite: 3]
- [x] Criou o `ProcessEventController` retornando JSON com status `201` no sucesso e `text/plain` com código `404` e corpo `0` nas exceções? [cite: 3]
- [x] Mapeou a rota `POST /event` sem prefixos incorretos? [cite: 3]
- [x] Criou a suíte unitária `ProcessEventUseCaseTest` cobrindo todos os fluxos e exceções? [cite: 3]
- [x] Criou a suíte de Feature `ProcessEventEndpointTest` cobrindo os cenários exatos do EBANX? [cite: 3]
- [x] O código passou sem apontamentos no Laravel Pint (`vendor/bin/pint --test`) e no Larastan (`vendor/bin/phpstan analyse`)? [cite: 3]
