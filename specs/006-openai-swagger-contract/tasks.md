# Tasks: SPEC-06 Especificação Central OpenAPI (Swagger) e Documentação Interativa em /docs

**Input**: Design documents from `/specs/006-openai-swagger-contract/` (`spec.md`, `plan.md`, `research.md`, `data-model.md`, `contracts/`, `quickstart.md`)  
**Prerequisites**: `spec.md`, `plan.md`, `research.md`, `data-model.md`, `contracts/`  
**Tests**: Sim (especificados em `spec.md` seção 6 e `AGENTS.md`)  

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Preparação da estrutura de diretórios e arquivos base

- [x] T001 Criar estrutura de diretórios para documentação e controllers em docs/, app/Http/Controllers/Docs/, resources/views/ e tests/Feature/Docs/

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Infraestrutura base de roteamento web que suporta os endpoints de documentação

- [x] T002 Configurar arquivo de rotas web em routes/web.php com declaração strict_types e imports base

**Checkpoint**: Estrutura base e roteamento web prontos para implementação das histórias de usuário

---

## Phase 3: User Story 1 - Especificação OpenAPI 3.0 e Endpoint GET /docs/openapi.yaml (Priority: P1) 🎯 MVP

**Goal**: Fornecer a especificação canônica formal OpenAPI 3.0.3 e o endpoint para download e consumo do YAML bruto

**Independent Test**: Enviar requisição GET para `/docs/openapi.yaml` e verificar status 200, Content-Type `text/yaml; charset=UTF-8` e schemas completos de `/reset`, `/balance` e `/event`.

### Tests for User Story 1 ⚠️

> **NOTE: Escrever os testes primeiro e verificar que falham antes da implementação**

- [x] T003 [P] [US1] Criar teste de feature para o endpoint GET /docs/openapi.yaml em tests/Feature/Docs/DocumentationEndpointTest.php cobrindo sucesso 200 e 404 quando o arquivo inexiste

### Implementation for User Story 1

- [x] T004 [P] [US1] Criar arquivo canônico de especificação OpenAPI 3.0.3 em docs/openapi.yaml cobrindo metadados, endpoints (/reset, /balance, /event) e schemas de componentes
- [x] T005 [US1] Implementar Single-Action Controller invokable DocumentationSpecController em app/Http/Controllers/Docs/DocumentationSpecController.php para servir docs/openapi.yaml com Content-Type text/yaml
- [x] T006 [US1] Registrar rota GET /docs/openapi.yaml vinculada ao DocumentationSpecController em routes/web.php

**Checkpoint**: Neste ponto, o arquivo OpenAPI e seu respectivo endpoint de download estão 100% operacionais e testáveis independentemente (MVP completo)

---

## Phase 4: User Story 2 - Visualizador Interativo Scalar em GET /docs (Priority: P2)

**Goal**: Disponibilizar interface gráfica moderna e interativa para documentação da API utilizando Scalar via CDN

**Independent Test**: Acessar `GET /docs` e verificar retorno HTML 200 contendo a tag de script da CDN `@scalar/api-reference` e o atributo `data-url="/docs/openapi.yaml"`.

### Tests for User Story 2 ⚠️

> **NOTE: Escrever os testes primeiro e verificar que falham antes da implementação**

- [x] T007 [P] [US2] Adicionar casos de teste para o endpoint GET /docs em tests/Feature/Docs/DocumentationEndpointTest.php validando resposta 200, Content-Type text/html e referências ao Scalar

### Implementation for User Story 2

- [x] T008 [P] [US2] Criar template Blade da documentação interativa em resources/views/docs.blade.php carregando Scalar via CDN oficial e apontando para /docs/openapi.yaml
- [x] T009 [US2] Implementar Single-Action Controller invokable DocumentationUiController em app/Http/Controllers/Docs/DocumentationUiController.php renderizando a view docs
- [x] T010 [US2] Registrar rota GET /docs vinculada ao DocumentationUiController em routes/web.php

**Checkpoint**: Ambas as histórias de usuário (US1 e US2) operam de forma independente e integrada

---

## Phase 5: Polish & Cross-Cutting Concerns

**Purpose**: Verificação de integridade, formatação, análise estática e validação E2E

- [x] T011 Executar validações manuais e automatizadas descritas em specs/006-openai-swagger-contract/quickstart.md
- [x] T012 Formatar e padronizar o código conforme regras do Laravel Pint com vendor/bin/pint
- [x] T013 [P] Executar análise estática de tipos nível máximo com vendor/bin/phpstan analyse
- [x] T014 [P] Executar suíte de testes completa da aplicação com php artisan test

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: Sem dependências — execução imediata.
- **Foundational (Phase 2)**: Depende do Setup (Phase 1) — desbloqueia as histórias de usuário.
- **User Story 1 (Phase 3 - P1)**: Depende da Phase 2 — MVP prioritário.
- **User Story 2 (Phase 4 - P2)**: Depende da Phase 2 — consome o endpoint gerado na US1 (`/docs/openapi.yaml`).
- **Polish (Phase 5)**: Depende da conclusão das fases de histórias de usuário.

### Within Each User Story

- Testes TDD escritos antes da implementação.
- Criação dos arquivos de dados/templates antes dos controllers.
- Implementação dos controllers antes do registro de rotas.
- Validação independente antes do avanço para a próxima etapa.

### Parallel Opportunities

- `T003` (Testes US1) e `T004` (Arquivo OpenAPI YAML) podem ser desenvolvidos em paralelo.
- `T007` (Testes US2) e `T008` (Template Blade docs) podem ser desenvolvidos em paralelo.
- `T013` (PHPStan) e `T014` (PHPUnit/Pest) podem ser executados em paralelo na fase final de Polish.

---

## Parallel Example: User Story 1

```bash
# Execução paralela: teste de feature e especificação OpenAPI YAML
Task: "T003 [P] [US1] Criar teste de feature para o endpoint GET /docs/openapi.yaml em tests/Feature/Docs/DocumentationEndpointTest.php"
Task: "T004 [P] [US1] Criar arquivo canônico de especificação OpenAPI 3.0.3 em docs/openapi.yaml"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Concluir Phase 1 (Setup) e Phase 2 (Foundational).
2. Concluir Phase 3 (User Story 1: `docs/openapi.yaml` e `DocumentationSpecController`).
3. **STOP and VALIDATE**: Testar o endpoint `GET /docs/openapi.yaml` com `php artisan test --filter DocumentationEndpointTest`.
4. Contrato OpenAPI formalmente funcional e pronto para consumo.

### Incremental Delivery

1. Setup + Foundational concluídos.
2. User Story 1 entregue e testada (especificação formal OpenAPI e download YAML).
3. User Story 2 entregue e testada (interface visual interativa Scalar).
4. Polish executado com Pint, Larastan e testes 100% aprovados.

