# SPEC-06: Especificação Central OpenAPI (Swagger) e Documentação Interativa em /docs

> **Diretriz Mandatória para Agentes de IA e Engenheiros de Software:**  
> Antes de escrever ou refatorar qualquer linha de código referente a esta especificação, **leia obrigatoriamente o arquivo `agents.md` localizado na raiz do projeto**. As regras estabelecidas naquele documento — abordagem Spec-First, documentação centralizada em `docs/openapi.yaml`, contratos com schemas completos e tipados, ausência de pacotes pesados acoplados ao runtime do PHP para geração de Swagger e entrega de documentação interativa e responsiva via CDN na rota `/docs` — devem ser seguidas de forma irrestrita.

---

## 1. Contexto e Objetivo Arquitetural

Esta especificação formaliza a documentação técnica de contrato da API utilizando a especificação OpenAPI 3.0.

No desenvolvimento orientado a especificações (Spec-Driven Development), o contrato OpenAPI é a fonte da verdade universal para consumidores externos, frontend, testes automatizados e times de integração. 

O objetivo desta entrega é:
1. Manter a especificação centralizada e versionada no arquivo `docs/openapi.yaml` (espelhado ou servido para consumo público).
2. Cobrir 100% dos endpoints do take-home assignment do EBANX (`POST /reset`, `GET /balance`, `POST /event`), detalhando parâmetros, bodies condicionais e múltiplos formatos de retorno (`text/plain` e `application/json`).
3. Disponibilizar uma rota `/docs` limpa e interativa no navegador utilizando **Scalar** via CDN oficial (`@scalar/api-reference`), sem necessidade de anotações poluentes no código PHP ou dependências pesadas no `composer.json` (como L5-Swagger).

---

## Clarifications

### Session 2026-11-10
- Q: Qual biblioteca baseada em CDN deve ser adotada como o visualizador interativo padrão na rota GET /docs? → A: Scalar via CDN oficial (@scalar/api-reference) com leitor dinâmico apontando para /docs/openapi.yaml, proporcionando interface moderna, responsiva, com temas claro/escuro e client HTTP integrado para teste de requisições.
- Q: Como os controllers de documentação devem ser estruturados para atender às diretrizes arquiteturais do AGENTS.md? → A: Dividir em dois Single-Action Controllers invokable (__invoke): DocumentationUiController (responsável por renderizar a interface HTML com Scalar) e DocumentationSpecController (responsável por servir o arquivo docs/openapi.yaml com Content-Type text/yaml).
- Q: Como o arquivo de especificação OpenAPI deve ser armazenado e resolvido pelo DocumentationSpecController? → A: Manter fonte única em docs/openapi.yaml lido diretamente pelo DocumentationSpecController através de base_path('docs/openapi.yaml'), retornando HTTP 200 com Content-Type text/yaml; charset=UTF-8 quando presente e 404 Not Found caso o arquivo não exista, sem duplicação ou links simbólicos no diretório public/.
- Q: As rotas de documentação (/docs e /docs/openapi.yaml) devem ter controle de acesso ou permanecer integralmente públicas? → A: Integralmente públicas e acessíveis em todos os ambientes (local, testing, production) sem qualquer middleware de autenticação, restrição por IP ou credenciais.

---

## 2. Estrutura do Contrato OpenAPI (`docs/openapi.yaml`)

O arquivo `docs/openapi.yaml` deve seguir o padrão OpenAPI 3.0.3 e conter:

### 2.1 Metadados da API (Info & Servers)
- **Título:** EBANX Software Engineer Take-Home API
- **Versão:** 1.0.0
- **Descrição:** Especificação formal da API de gerenciamento de contas, eventos de saldo e liquidação financeira.
- **Servers:** URL relativa (`/`) e URLs de ambientes configurados (local e Laravel Cloud).

### 2.2 Mapeamento Completo dos Endpoints

#### A. `POST /reset`
- **Sumário:** Redefine o estado global da aplicação.
- **Descrição:** Expurga todas as contas e saldos existentes, restaurando o estado virgem.
- **Respostas:**
  - `200 OK`:
    - `description`: Estado resetado com sucesso.
    - `content`:
      - `text/plain`:
        - `schema`: `{ type: string, example: "OK" }`

#### B. `GET /balance`
- **Sumário:** Consulta o saldo de uma conta.
- **Descrição:** Retorna o saldo numérico atual da conta de forma estritamente idempotente.
- **Parâmetros de Consulta (`parameters`):**
  - `name: account_id`, `in: query`, `required: true`, `schema: { type: string }`, `example: "100"`.
- **Respostas:**
  - `200 OK`:
    - `description`: Conta encontrada com saldo.
    - `content`:
      - `text/plain`:
        - `schema`: `{ type: number, example: 20 }`
  - `404 Not Found`:
    - `description`: Conta não encontrada.
    - `content`:
      - `text/plain`:
        - `schema`: `{ type: string, example: "0" }`
  - `422 Unprocessable Content`:
    - `description`: Parâmetro obrigatório ausente ou inválido.
    - `content`:
      - `application/json`:
        - `$ref: '#/components/schemas/ValidationError'`

#### C. `POST /event`
- **Sumário:** Processa um evento financeiro (depósito, saque ou transferência).
- **Descrição:** Executa mutações de saldo de acordo com o tipo de evento fornecido no corpo da requisição.
- **Request Body:**
  - `required: true`
  - `content`:
    - `application/json`:
      - `schema`: `$ref: '#/components/schemas/EventRequest'`
      - `examples`: Exemplos dedicados para `deposit`, `withdraw` e `transfer`.
- **Respostas:**
  - `201 Created`:
    - `description`: Evento executado com sucesso.
    - `content`:
      - `application/json`:
        - `schema`: `$ref: '#/components/schemas/EventResponse'`
        - `examples`: Payloads de retorno para depósito, saque e transferência.
  - `404 Not Found`:
    - `description`: Conta de origem inexistente ou saldo insuficiente para saque/transferência.
    - `content`:
      - `text/plain`:
        - `schema`: `{ type: string, example: "0" }`
  - `422 Unprocessable Content`:
    - `description`: Erro de validação nos campos do payload.
    - `content`:
      - `application/json`:
        - `$ref: '#/components/schemas/ValidationError'`

### 2.3 Schemas de Componentes (`components/schemas`)
- **`Account`**: `{ type: object, properties: { id: { type: string }, balance: { type: number } } }`
- **`EventRequest`**: Schema polimórfico ou discriminado com enum `type: [deposit, withdraw, transfer]`, campos condicionais `origin`, `destination` e `amount > 0`.
- **`EventResponse`**: Objeto contendo nós opcionais `origin` e/ou `destination`, ambos referenciando o schema `Account`.
- **`ValidationError`**: Estrutura padrão de erros de validação do Laravel (`message` e `errors`).

---

## 3. Disponibilização da Interface Interativa (`/docs`)

Para atender às boas práticas do `agents.md` sem inchar dependências ou exigir build de assets:

### 3.1 Servir o Arquivo OpenAPI
- O arquivo de especificação reside com fonte única da verdade em `docs/openapi.yaml`.
- O endpoint `GET /docs/openapi.yaml` é processado pelo `DocumentationSpecController`, que realiza a leitura direta do arquivo em disco via `base_path('docs/openapi.yaml')`.
- Não deve haver espelhamento, links simbólicos ou duplicação de arquivos no diretório `public/`. Se o arquivo não existir fisicamente, o controller deve responder com `404 Not Found`.

### 3.2 Visualizador Interativo Moderno (Scalar via CDN)
- Rota: `GET /docs`
- Renderização: Retorna uma view Blade minimalista (`resources/views/docs.blade.php`) ou Controller dedicado que carrega a biblioteca interativa Scalar via CDN oficial (`@scalar/api-reference`):
  - Carregamento rápido via CDN.
  - Interface responsiva com suporte a temas (light/dark), teste de requisições inline e geração de snippets em múltiplas linguagens (cURL, PHP, Python, JS).
  - Aponta dinamicamente para a URL da especificação (`/docs/openapi.yaml`).

### 3.3 Política de Acesso e Segurança
- As rotas `/docs` e `/docs/openapi.yaml` são integralmente públicas e operam sem exigência de autenticação, tokens, restrição por IP ou middlewares de autorização em qualquer ambiente (local, testing e produção).

---

## 4. Camada de Apresentação HTTP (Single-Action Controllers & Rotas)

### 4.1 Single-Action Controllers (`app/Http/Controllers/Docs/`)
Seguindo as diretrizes do `agents.md`, cada endpoint é atendido por um Single-Action Controller dedicado via método mágico `__invoke`:

1. **`DocumentationUiController`**:
   - **Localização:** `app/Http/Controllers/Docs/DocumentationUiController.php`
   - **Método:** `public function __invoke(): View`
   - **Responsabilidade:** Renderiza a view Blade `docs` com o visualizador interativo Scalar.

2. **`DocumentationSpecController`**:
   - **Localização:** `app/Http/Controllers/Docs/DocumentationSpecController.php`
   - **Método:** `public function __invoke(): Response`
   - **Responsabilidade:** Lê o arquivo `docs/openapi.yaml` e retorna seu conteúdo com `Content-Type: text/yaml; charset=UTF-8`. Se o arquivo não existir, retorna `404 Not Found`.

### 4.2 Definição de Rotas (`routes/web.php`)
```php
use App\Http\Controllers\Docs\DocumentationSpecController;
use App\Http\Controllers\Docs\DocumentationUiController;

Route::get('/docs', DocumentationUiController::class)->name('docs.ui');
Route::get('/docs/openapi.yaml', DocumentationSpecController::class)->name('docs.spec');
```

---

## 5. Mapeamento de Arquivos da Entrega

```text
docs/
└── openapi.yaml                      # Especificação técnica formal OpenAPI 3.0

resources/
└── views/
    └── docs.blade.php                # Template Blade carregando Scalar via CDN oficial

app/
└── Http/
    └── Controllers/
        └── Docs/
            ├── DocumentationUiController.php
            └── DocumentationSpecController.php

routes/
└── web.php

tests/
└── Feature/
    └── Docs/
        └── DocumentationEndpointTest.php
```

---

## 6. Critérios de Aceitação e Cenários BDD (Gherkin)

### Testes de Feature HTTP (`DocumentationEndpointTest.php`)
```gherkin
Feature: API Documentation Endpoints

  Scenario: Acessar a interface interativa da documentação
    When eu envio uma requisição GET para "/docs"
    Then o status da resposta deve ser 200
    And o cabeçalho "Content-Type" deve conter "text/html"
    And a resposta deve conter a referência para o CDN do visualizador de documentação

  Scenario: Fazer o download do arquivo de especificação OpenAPI
    When eu envio uma requisição GET para "/docs/openapi.yaml"
    Then o status da resposta deve ser 200
    And o cabeçalho "Content-Type" deve conter "yaml"
    And a resposta deve conter as especificações dos caminhos "/reset", "/balance" e "/event"
```

---

## 7. Checklist de Validação Técnica e Aceite

- [x] Leu e cumpriu rigorosamente as regras do `agents.md`?
- [x] O arquivo `docs/openapi.yaml` é válido conforme o padrão OpenAPI 3.0.3?
- [x] Todos os 3 endpoints (`/reset`, `/balance`, `/event`) estão integralmente documentados com parâmetros, schemas de body e todos os status codes (`200`, `201`, `404`, `422`)?
- [x] Foram documentados os formatos de mídia exatos (`text/plain` para retornos de saldo/reset/0 e `application/json` para payloads de evento)?
- [x] Implementou a rota `GET /docs` mapeada para o Single-Action Controller `DocumentationUiController` servindo Scalar via CDN?
- [x] Implementou a rota `GET /docs/openapi.yaml` mapeada para o Single-Action Controller `DocumentationSpecController` servindo o arquivo de especificação com headers corretos?
- [x] Criou a suíte de testes de integração em `DocumentationEndpointTest` validando as respostas `200` para `/docs` e `/docs/openapi.yaml`?
- [x] O código passou sem alertas no Laravel Pint (`vendor/bin/pint --test`) e no Larastan (`vendor/bin/phpstan analyse`)?
