# Data Model & Schema Specifications: SPEC-06 OpenAPI (Swagger)

**Feature**: `006-openai-swagger-contract`  
**Status**: Concluído  
**Data**: 2026-11-10  

Este documento descreve as entidades, schemas e estruturas de dados padronizadas na especificação OpenAPI 3.0.3 (`docs/openapi.yaml`) que formalizam a totalidade dos contratos da API.

---

## 1. Estrutura Canônica da Especificação OpenAPI 3.0.3

```yaml
openapi: 3.0.3
info:
  title: EBANX Software Engineer Take-Home API
  version: 1.0.0
  description: Especificação formal da API de gerenciamento de contas, liquidação financeira e consulta de saldos.
servers:
  - url: /
    description: Servidor local / relativo
```

---

## 2. Schemas de Componentes (`components/schemas`)

### 2.1 `Account`
Representa o estado fundamental de uma conta bancária.
- **Tipo:** `object`
- **Propriedades:**
  - `id`: `string` (Identificador único da conta, ex.: `"100"`)
  - `balance`: `number` (Saldo atual da conta, podendo ser inteiro ou ponto flutuante, ex.: `20` ou `15.5`)
- **Campos Obrigatórios:** `id`, `balance`

```yaml
Account:
  type: object
  required:
    - id
    - balance
  properties:
    id:
      type: string
      example: "100"
      description: Identificador único da conta.
    balance:
      type: number
      example: 20
      description: Saldo numérico disponível.
```

---

### 2.2 `EventRequest`
Payload polimórfico de entrada para o endpoint `POST /event`, atendendo às três operações: `deposit`, `withdraw` e `transfer`.

- **Tipo:** `object`
- **Propriedades:**
  - `type`: `string`, enum: `['deposit', 'withdraw', 'transfer']`
  - `amount`: `number`, mínimo estrito maior que 0 (`exclusiveMinimum: 0`)
  - `origin`: `string` (obrigatório para `withdraw` e `transfer`, ex.: `"100"`)
  - `destination`: `string` (obrigatório para `deposit` e `transfer`, ex.: `"300"`, obrigatoriamente diferente de `origin`)
- **Campos Obrigatórios Globais:** `type`, `amount`

```yaml
EventRequest:
  type: object
  required:
    - type
    - amount
  properties:
    type:
      type: string
      enum:
        - deposit
        - withdraw
        - transfer
      description: Tipo de operação financeira.
    amount:
      type: number
      minimum: 0.01
      example: 10
      description: Valor da transação (deve ser estritamente maior que zero).
    origin:
      type: string
      example: "100"
      description: Identificador da conta de origem (obrigatório para withdraw e transfer).
    destination:
      type: string
      example: "300"
      description: Identificador da conta de destino (obrigatório para deposit e transfer).
```

---

### 2.3 `EventResponse`
Retorno estruturado após a liquidação bem-sucedida de um evento financeiro (`201 Created`).

- **Tipo:** `object`
- **Propriedades Opcionais:**
  - `origin`: `$ref: '#/components/schemas/Account'` (presente em `withdraw` e `transfer`)
  - `destination`: `$ref: '#/components/schemas/Account'` (presente em `deposit` e `transfer`)

```yaml
EventResponse:
  type: object
  properties:
    origin:
      $ref: '#/components/schemas/Account'
    destination:
      $ref: '#/components/schemas/Account'
```

---

### 2.4 `ValidationError`
Estrutura de erro retornada pelo Laravel quando dados de entrada falham nas validações do `FormRequest` (`422 Unprocessable Content`).

- **Tipo:** `object`
- **Propriedades:**
  - `message`: `string` (Mensagem descritiva sumária)
  - `errors`: `object` com mapeamento de chave de campo para array de strings com mensagens de erro

```yaml
ValidationError:
  type: object
  required:
    - message
    - errors
  properties:
    message:
      type: string
      example: "The origin field is required when type is withdraw."
    errors:
      type: object
      additionalProperties:
        type: array
        items:
          type: string
      example:
        origin:
          - "The origin field is required when type is withdraw."
```

---

## 3. Matriz de Mapeamento Endpoints x Payloads x Status Codes

| Endpoint | Verbo HTTP | Request Content-Type | Response Content-Type | Status Codes | Descrição |
|---|---|---|---|---|---|
| `/reset` | `POST` | *Nenhum* | `text/plain; charset=UTF-8` | `200` | Limpa o banco/cache de contas e retorna `"OK"` |
| `/balance` | `GET` | *Query params* (`account_id`) | `text/plain; charset=UTF-8` \| `application/json` | `200`, `404`, `422` | Retorna saldo (`200`), literal `"0"` (`404`), ou JSON de validação (`422`) |
| `/event` | `POST` | `application/json` | `application/json` \| `text/plain; charset=UTF-8` | `201`, `404`, `422` | Liquida evento (`201`), erro de conta/saldo (`404` literal `"0"`), validação (`422`) |
| `/docs` | `GET` | *Nenhum* | `text/html; charset=UTF-8` | `200` | Interface interativa Scalar |
| `/docs/openapi.yaml` | `GET` | *Nenhum* | `text/yaml; charset=UTF-8` | `200`, `404` | Conteúdo bruto da especificação OpenAPI 3.0 |

