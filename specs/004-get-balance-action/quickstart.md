# Quickstart: Validação e Execução de SPEC-04 (GET /balance)

Este guia descreve os passos para executar, testar e validar o endpoint `GET /balance` e o caso de uso `GetBalance` em ambientes de desenvolvimento e teste.

---

## 1. Pré-requisitos

- PHP 8.3+ com extensões `pdo_sqlite` e `redis` instaladas
- Composer instalado
- Dependências instaladas (`composer install`)

---

## 2. Execução da Suíte de Testes Automatizados

### 2.1 Testes Unitários do Caso de Uso
Valida a lógica pura de consulta e o disparo de `AccountNotFoundException` com mock de `AccountRepositoryInterface`:
```bash
php artisan test --filter=GetBalanceUseCaseTest
```

### 2.2 Testes de Feature do Endpoint HTTP
Valida o endpoint `GET /balance` de ponta a ponta, confirmando código `200` com saldo numérico em `text/plain`, `404` com corpo `0`, e `422` em JSON quando o parâmetro for omitido:
```bash
php artisan test --filter=GetBalanceEndpointTest
```

### 2.3 Suíte Completa de Testes
Executa toda a suíte de testes do projeto para assegurar que não haja regressões:
```bash
php artisan test
```

---

## 3. Validação Manual via cURL

Inicie o servidor embutido do Laravel em um terminal:
```bash
php artisan serve --port=8000
```

### 3.1 Consulta de Conta Inexistente (404)
```bash
curl -i "http://localhost:8000/balance?account_id=1234"
```
**Resposta esperada:**
```http
HTTP/1.1 404 Not Found
Content-Type: text/plain; charset=UTF-8

0
```

### 3.2 Consulta com Parâmetro Ausente (422 JSON forçado)
```bash
curl -i "http://localhost:8000/balance"
```
**Resposta esperada:**
```http
HTTP/1.1 422 Unprocessable Content
Content-Type: application/json

{"message":"The account id field is required.","errors":{"account_id":["The account id field is required."]}}
```

### 3.3 Consulta de Conta Existente (200 OK)
*(Após criar uma conta via evento de depósito ou no tinker)*:
```bash
curl -i "http://localhost:8000/balance?account_id=100"
```
**Resposta esperada:**
```http
HTTP/1.1 200 OK
Content-Type: text/plain; charset=UTF-8

20
```

---

## 4. Validação de Qualidade de Código e Análise Estática

### 4.1 Formatação com Laravel Pint
```bash
vendor/bin/pint --test
```

### 4.2 Análise Estática com Larastan / PHPStan (Nível 6)
```bash
vendor/bin/phpstan analyse
```

