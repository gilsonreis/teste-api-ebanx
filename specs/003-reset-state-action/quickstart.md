# Quickstart: Validação e Execução de SPEC-03 (POST /reset)

Este guia descreve os passos para executar, testar e validar o endpoint `POST /reset` e o caso de uso `ResetState` em ambientes de desenvolvimento e teste.

---

## 1. Pré-requisitos

- PHP 8.3+ com extensões `pdo_sqlite` e `redis` instaladas
- Composer instalado
- Dependências instaladas (`composer install`)

---

## 2. Execução da Suíte de Testes Automatizados

### 2.1 Testes Unitários do Caso de Uso
Valida a lógica pura de orquestração do `ResetStateUseCase` com mock de `AccountRepositoryInterface`:
```bash
php artisan test --filter=ResetStateUseCaseTest
```

### 2.2 Testes de Feature do Endpoint HTTP
Valida o endpoint `POST /reset` de ponta a ponta, confirmando código `200`, cabeçalho `Content-Type: text/plain` e payload `OK`:
```bash
php artisan test --filter=ResetEndpointTest
```

### 2.3 Suíte Completa de Testes
Executa toda a suíte de testes do projeto para garantir ausência de regressões:
```bash
php artisan test
```

---

## 3. Validação Manual via cURL

Inicie o servidor embutido do Laravel em um terminal:
```bash
php artisan serve --port=8000
```

### 3.1 Disparo de Requisição Padrão
```bash
curl -i -X POST http://localhost:8000/reset
```
**Resposta esperada:**
```http
HTTP/1.1 200 OK
Content-Type: text/plain; charset=UTF-8

OK
```

### 3.2 Disparo com Payload Arbitrário (Comportamento Permissivo)
```bash
curl -i -X POST http://localhost:8000/reset \
  -H "Content-Type: application/json" \
  -d '{"unexpected": "data"}'
```
**Resposta esperada:**
```http
HTTP/1.1 200 OK
Content-Type: text/plain; charset=UTF-8

OK
```

### 3.3 Disparo de Verbo Não Permitido (GET)
```bash
curl -i -X GET http://localhost:8000/reset
```
**Resposta esperada:**
```http
HTTP/1.1 405 Method Not Allowed
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

