# Quickstart: Validação da Documentação OpenAPI e Interface Interativa (/docs)

**Feature**: `006-openai-swagger-contract`  
**Data**: 2026-11-10  

Este guia apresenta o roteiro prático e executável para validar localmente a disponibilização do arquivo `docs/openapi.yaml` e a interface visual interativa em `/docs`.

---

## 1. Pré-Requisitos

1. PHP 8.2 ou superior instalado com extensões padrão do Laravel.
2. Servidor web local em execução (ou ambiente de testes PHPUnit/Pest).

---

## 2. Inicialização do Ambiente Local

Para executar e testar localmente:

```bash
# Iniciar o servidor embutido do Laravel na porta 8000
php artisan serve
```

---

## 3. Cenários de Validação Manual via cURL / Navegador

### Cenário 1: Acesso à Interface Interativa no Navegador
- **Ação**: Abra o navegador na URL `http://localhost:8000/docs`
- **Validação visual**:
  1. A página deve renderizar sem erros de JavaScript.
  2. O visualizador Scalar deve exibir os metadados da API (*EBANX Software Engineer Take-Home API*).
  3. Devem estar visíveis os três endpoints: `POST /reset`, `GET /balance` e `POST /event`.
  4. O painel deve permitir testar requisições diretamente contra o servidor local.

- **Validação via cURL**:
```bash
curl -i http://localhost:8000/docs
```
- **Resultado Esperado**:
  - Status HTTP `200 OK`
  - Cabeçalho `Content-Type: text/html; charset=UTF-8`
  - Presença da tag CDN `@scalar/api-reference` e do atributo `data-url="/docs/openapi.yaml"` no corpo HTML.

---

### Cenário 2: Download do Arquivo OpenAPI YAML
- **Ação**:
```bash
curl -i http://localhost:8000/docs/openapi.yaml
```
- **Resultado Esperado**:
  - Status HTTP `200 OK`
  - Cabeçalho `Content-Type: text/yaml; charset=UTF-8`
  - O corpo da resposta deve conter a especificação YAML válida com as rotas `/reset`, `/balance` e `/event`.

---

### Cenário 3: Comportamento quando o Arquivo YAML não Existe
- **Ação**: Caso o arquivo `docs/openapi.yaml` seja temporariamente renomeado ou removido:
```bash
curl -i http://localhost:8000/docs/openapi.yaml
```
- **Resultado Esperado**:
  - Status HTTP `404 Not Found`.

---

## 4. Validação Automatizada (Suíte de Testes)

Execute a suíte de testes de integração dedicada:

```bash
# Execução dos testes de feature do módulo Docs
php artisan test --filter DocumentationEndpointTest
```

---

## 5. Verificação de Qualidade e Análise Estática

Garantir que a entrega atende a todas as diretrizes de tipagem estrita e estilo de código:

```bash
# Análise de estilo (Laravel Pint)
vendor/bin/pint --test

# Análise estática de tipos (Larastan)
vendor/bin/phpstan analyse
```

