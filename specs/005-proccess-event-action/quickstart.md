# Quickstart: Validação e Execução de SPEC-05 (POST /event)

Este guia descreve os passos para executar, testar e validar o endpoint `POST /event` e o caso de uso `ProcessEvent` em ambientes de desenvolvimento e teste.

---

## 1. Pré-requisitos

- PHP 8.3+ com extensões `pdo_sqlite` e `redis` instaladas
- Composer instalado
- Dependências instaladas (`composer install`)

---

## 2. Execução da Suíte de Testes Automatizados

### 2.1 Testes Unitários do Caso de Uso
Valida os fluxos de `deposit`, `withdraw`, `transfer`, atomicidade e lançamento de exceções (`AccountNotFoundException`, `InsufficientFundsException`) com mock de `AccountRepositoryInterface`:
```bash
php artisan test --filter=ProcessEventUseCaseTest
```

### 2.2 Testes de Feature do Endpoint HTTP
Valida o endpoint `POST /event` de ponta a ponta conforme o contrato oficial do EBANX (respostas 201 Created em JSON, 404 em texto puro com corpo `0`, e 422 em JSON para validação):
```bash
php artisan test --filter=ProcessEventEndpointTest
```

### 2.3 Suíte Completa de Testes
Executa todos os testes do projeto para assegurar que nenhum outro endpoint foi impactado:
```bash
php artisan test
```

---

## 3. Validação Manual via cURL

Inicie o servidor de desenvolvimento em um terminal:
```bash
php artisan serve --port=8000
```

### 3.1 Reset de Estado
```bash
curl -i -X POST http://localhost:8000/reset
```

### 3.2 Criar Conta com Saldo Inicial (Depósito)
```bash
curl -i -X POST http://localhost:8000/event \
  -H "Content-Type: application/json" \
  -d '{"type":"deposit", "destination":"100", "amount":10}'
```
**Resposta esperada (201 Created):**
```http
HTTP/1.1 201 Created
Content-Type: application/json

{"destination":{"id":"100","balance":10}}
```

### 3.3 Depositar em Conta Existente
```bash
curl -i -X POST http://localhost:8000/event \
  -H "Content-Type: application/json" \
  -d '{"type":"deposit", "destination":"100", "amount":10}'
```
**Resposta esperada (201 Created):**
```http
HTTP/1.1 201 Created
Content-Type: application/json

{"destination":{"id":"100","balance":20}}
```

### 3.4 Consultar Saldo
```bash
curl -i http://localhost:8000/balance?account_id=100
```
**Resposta esperada (200 OK):**
```http
HTTP/1.1 200 OK
Content-Type: text/plain; charset=UTF-8

20
```

### 3.5 Sacar de Conta Inexistente (404)
```bash
curl -i -X POST http://localhost:8000/event \
  -H "Content-Type: application/json" \
  -d '{"type":"withdraw", "origin":"200", "amount":10}'
```
**Resposta esperada (404 Not Found):**
```http
HTTP/1.1 404 Not Found
Content-Type: text/plain; charset=UTF-8

0
```

### 3.6 Sacar de Conta Existente
```bash
curl -i -X POST http://localhost:8000/event \
  -H "Content-Type: application/json" \
  -d '{"type":"withdraw", "origin":"100", "amount":5}'
```
**Resposta esperada (201 Created):**
```http
HTTP/1.1 201 Created
Content-Type: application/json

{"origin":{"id":"100","balance":15}}
```

### 3.7 Transferência entre Contas
```bash
curl -i -X POST http://localhost:8000/event \
  -H "Content-Type: application/json" \
  -d '{"type":"transfer", "origin":"100", "amount":15, "destination":"300"}'
```
**Resposta esperada (201 Created):**
```http
HTTP/1.1 201 Created
Content-Type: application/json

{"origin":{"id":"100","balance":0},"destination":{"id":"300","balance":15}}
```

### 3.8 Transferência de Conta Inexistente (404)
```bash
curl -i -X POST http://localhost:8000/event \
  -H "Content-Type: application/json" \
  -d '{"type":"transfer", "origin":"200", "amount":15, "destination":"300"}'
```
**Resposta esperada (404 Not Found):**
```http
HTTP/1.1 404 Not Found
Content-Type: text/plain; charset=UTF-8

0
```

### 3.9 Rejeição de Auto-Transferência (422)
```bash
curl -i -X POST http://localhost:8000/event \
  -H "Content-Type: application/json" \
  -d '{"type":"transfer", "origin":"100", "amount":15, "destination":"100"}'
```
**Resposta esperada (422 Unprocessable Content):**
```http
HTTP/1.1 422 Unprocessable Content
Content-Type: application/json

{"message":"The destination and origin must be different.","errors":{"destination":["The destination and origin must be different."]}}
```

---

## 4. Análise Estática e Padronização de Código

```bash
# Formatação e conformidade de estilo (Laravel Pint)
vendor/bin/pint --test

# Análise estática com PHPStan / Larastan Nível 6
vendor/bin/phpstan analyse
```

