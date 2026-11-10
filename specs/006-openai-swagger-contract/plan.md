# Implementation Plan: SPEC-06 Especificação Central OpenAPI (Swagger) e Documentação Interativa em /docs

**Branch**: `006-openai-swagger-contract` | **Date**: 2026-11-10 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `specs/006-openai-swagger-contract/spec.md`

## Summary

Esta entrega formaliza e disponibiliza o contrato OpenAPI 3.0.3 centralizado da aplicação no arquivo `docs/openapi.yaml`, contemplando 100% dos endpoints do take-home assignment do EBANX (`POST /reset`, `GET /balance`, `POST /event`). Além disso, disponibiliza a rota interativa `GET /docs` renderizando o visualizador **Scalar** via CDN (`@scalar/api-reference`) e o endpoint `GET /docs/openapi.yaml` servindo o arquivo YAML diretamente do filesystem. Ambos os endpoints são orquestrados por Single-Action Controllers (`__invoke`), sem introduzir dependências externas no `composer.json` e respeitando estritamente o manual de engenharia `AGENTS.md`.

## Technical Context

**Language/Version**: PHP 8.2+ com tipagem estrita global (`declare(strict_types=1);`)  
**Primary Dependencies**: Laravel 12.x Framework (sem adição de bibliotecas de Swagger no `composer.json`), Scalar CDN (`@scalar/api-reference`)  
**Storage**: N/A (leitura direta do arquivo `docs/openapi.yaml` no disco)  
**Testing**: PHPUnit / Pest (`tests/Feature/Docs/DocumentationEndpointTest.php`)  
**Target Platform**: Linux/macOS Web Server / Laravel Cloud  
**Project Type**: Web API & Documentation Viewer  
**Performance Goals**: Tempo de resposta do endpoint `/docs/openapi.yaml` < 15ms; carregamento assíncrono e leve da CDN do Scalar no navegador  
**Constraints**: Zero dependências de anotações ou bibliotecas pesadas de OpenAPI no PHP runtime; estrita aderência a Single-Action Controllers (`__invoke`); 100% em conformidade com Laravel Pint e Larastan  
**Scale/Scope**: 1 arquivo OpenAPI YAML central (`docs/openapi.yaml`), 1 view Blade (`resources/views/docs.blade.php`), 2 Single-Action Controllers, 2 rotas em `routes/web.php` e 1 suíte de testes de feature  

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

| Princípio / Regra (`AGENTS.md`) | Status | Análise de Conformidade |
|---|---|---|
| **Spec-Driven Development (SDD)** | PASS | O desenvolvimento parte estritamente do contrato formalizado em `spec.md` e `docs/openapi.yaml`. |
| **YAGNI & Simplicidade Pragmática** | PASS | Scalar via CDN estática dispensa pacotes pesados de terceiros (L5-Swagger) ou rotinas de build no deploy. |
| **Single-Action Controllers (`__invoke`)** | PASS | Duas classes dedicadas (`DocumentationUiController` e `DocumentationSpecController`) com método `__invoke()`. |
| **Governança OpenAPI Spec-First** | PASS | Todos os 3 endpoints da API (`/reset`, `/balance`, `/event`) estão exaustivamente documentados com schemas, status codes e mídias (`text/plain`, `application/json`). |
| **Segurança de Configuração** | PASS | Nenhuma chamada a `env()` no código da aplicação. |
| **Qualidade e Estilo de Código** | PASS | `declare(strict_types=1);` em todos os arquivos PHP e `single_blank_line_at_eof` garantido. |

## Project Structure

### Documentation (this feature)

```text
specs/006-openai-swagger-contract/
├── spec.md              # Especificação de requisitos da feature
├── plan.md              # Este plano de implementação
├── research.md          # Pesquisa técnica (Scalar CDN, Single-Action Controllers, storage)
├── data-model.md        # Schemas OpenAPI e matriz de contratos
├── quickstart.md        # Roteiro de validação manual e automatizada
├── contracts/
│   └── documentation-endpoints.yaml # Contrato OpenAPI específico das rotas /docs
└── tasks.md             # Decomposição de tarefas (/speckit-tasks)
```

### Source Code (repository root)

```text
docs/
└── openapi.yaml                                # Especificação canônica OpenAPI 3.0.3

resources/
└── views/
    └── docs.blade.php                          # View Blade com Scalar via CDN oficial

app/
└── Http/
    └── Controllers/
        └── Docs/
            ├── DocumentationUiController.php   # Single-Action Controller para GET /docs
            └── DocumentationSpecController.php # Single-Action Controller para GET /docs/openapi.yaml

routes/
└── web.php                                     # Mapeamento das rotas /docs e /docs/openapi.yaml

tests/
└── Feature/
    └── Docs/
        └── DocumentationEndpointTest.php       # Testes de integração para os endpoints de documentação
```

**Structure Decision**:
A implementação utiliza o diretório central `docs/` para abrigar a especificação canônica `openapi.yaml`. A camada de apresentação reside em `app/Http/Controllers/Docs/`, mantendo total isolamento e coesão dos adaptadores HTTP de documentação em relação aos domínios de negócio (`Account`). O template Blade fica em `resources/views/docs.blade.php` e as rotas são declaradas em `routes/web.php` com nomes semânticos (`docs.ui`, `docs.spec`).

## Complexity Tracking

> **Sem violações aos princípios constitucionais ou às regras do `AGENTS.md`.**

| Violation | Why Needed | Simpler Alternative Rejected Because |
|---|---|---|
| *Nenhuma* | N/A | N/A |
