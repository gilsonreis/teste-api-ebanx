# Implementation Plan: SPEC-03 — Reset de Estado, Tríade do UseCase e Endpoint POST /reset

**Branch**: `003-reset-state-action` (`feature/spec-03-reset-state-action`) | **Date**: 2026-11-10 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/003-reset-state-action/spec.md`

---

## Summary

Implementar a ação de reset de estado da aplicação, orquestrando a restauração do sistema para o estado virgem via `AccountRepositoryInterface::reset()`. A solução compreende o Single-Action Controller `ResetController` (`app/Http/Controllers/Account/ResetController.php`), a tríade coesa do caso de uso em `app/UseCases/Account/ResetState/` (`ResetStateUseCase`, `ResetStateUseCaseInput`, `ResetStateUseCaseOutput`), a configuração do arquivo de rotas `routes/api.php` em `bootstrap/app.php` com `apiPrefix: ''` (assegurando pipeline stateless sem CSRF) e a cobertura completa por testes unitários e de feature HTTP validando o retorno `200 OK` com `'OK'` em `text/plain`.

---

## Technical Context

**Language/Version**: PHP `^8.3` (`declare(strict_types=1);` obrigatório em todos os arquivos)

**Primary Dependencies**: Laravel Framework `^13.17` (`Illuminate\Http\Response`, `Illuminate\Support\Facades\Route`)

**Storage**: Integração desacoplada com `App\Repositories\Contracts\AccountRepositoryInterface` (cuja resolução física suporta os drivers `cache`, `database` e `redis` implementados na SPEC-02)

**Testing**: PHPUnit `^12.5.12` (`php artisan test`), Laravel Pint `^1.27` (`vendor/bin/pint --test`), Larastan/PHPStan `^3.14` Nível 6 (`vendor/bin/phpstan analyse`)

**Target Platform**: Servidor Linux/macOS com runtime PHP 8.3+ (compatível com Laravel Cloud)

**Project Type**: Web Service / Backend API (Laravel) — Camada de Apresentação HTTP (Controller & Roteamento) e Camada de Aplicação (UseCases & DTOs)

**Performance Goals**: Tempo de resposta do endpoint `< 10ms` em ambiente local/testes, sem overhead de sessões ou cookies.

**Constraints**:
- Single-Action Controller usando o método mágico `__invoke` (`AGENTS.md` §3.1).
- `FormRequest` dispensado explicitamente (YAGNI) por ausência de dados a validar e comportamento permissivo do endpoint.
- Tríade do caso de uso (`UseCase`, `Input`, `Output`) coesa em `app/UseCases/Account/ResetState/` (`AGENTS.md` §3.2).
- DTOs imutáveis declarados com `readonly` e tipagem estrita global (`AGENTS.md` §3.2 e §5.4).
- Inversão de dependência total: o UseCase depende exclusivamente de `AccountRepositoryInterface` (`AGENTS.md` §1.5 e §3.2).
- Roteamento configurado sem prefixo `/api` via `bootstrap/app.php` com `apiPrefix: ''` e isento de CSRF.
- Formato de resposta HTTP exato: status `200`, `Content-Type: text/plain; charset=UTF-8`, corpo `'OK'`.
- Tratamento permissivo: ignorar quaisquer payloads ou parâmetros extras sem retornar erro `422`.

**Scale/Scope**: 1 Controller invokable, 1 Caso de Uso, 2 DTOs (Input e Output), 1 arquivo de rotas (`routes/api.php`), atualização em `bootstrap/app.php`, 1 teste unitário do caso de uso e 1 teste de feature do endpoint HTTP.

---

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-checked after Phase 1 design.*

Como `.specify/memory/constitution.md` contém apenas placeholders de template, os gates arquiteturais e de governança são validados contra a especificação canônica do projeto em `AGENTS.md`:

| Gate Arquitetural (`AGENTS.md`) | Status Pré-Pesquisa | Status Pós-Design (Phase 1) | Verificação Técnica |
| :--- | :--- | :--- | :--- |
| **1. Spec-Driven Development (SDD)** | PASS | PASS | Implementa estritamente o endpoint `POST /reset`, o caso de uso e os cenários BDD descritos em `specs/003-reset-state-action/spec.md`. |
| **2. YAGNI & Simplicidade Pragmática** | PASS | PASS | DTOs e caso de uso focados exclusivamente na operação de reset, sem abstrações ou middlewares redundantes. |
| **3. Fronteiras Explícitas via DTOs (Input/Output)** | PASS | PASS | UseCase recebe `ResetStateUseCaseInput` e retorna `ResetStateUseCaseOutput`; nenhuma instância de `Request` atinge o caso de uso. |
| **4. Single-Action Controllers (`__invoke`)** | PASS | PASS | `ResetController` implementa exclusivamente `__invoke`, operando apenas como adaptador de transporte. |
| **5. Persistência Desacoplada (Repository Pattern)** | PASS | PASS | O caso de uso depende puramente de `AccountRepositoryInterface`, sem conhecimento do mecanismo físico de armazenamento. |
| **6. Tipagem Estrita (`declare(strict_types=1);`)** | PASS | PASS | Todos os arquivos criados e editados declaram tipagem estrita e cumprem os requisitos do PHPStan Nível 6. |

---

## Project Structure

### Documentation (this feature)

```text
specs/003-reset-state-action/
├── spec.md                                   # Especificação da feature (SPEC-03)
├── plan.md                                   # Este plano de implementação (/speckit-plan)
├── research.md                               # Decisões de arquitetura e roteamento (Phase 0)
├── data-model.md                             # DTOs da tríade e transições de estado (Phase 1)
├── quickstart.md                             # Guia de validação e testes rápidos (Phase 1)
├── contracts/
│   └── reset-endpoint-contract.md            # Contrato formal do endpoint POST /reset (Phase 1)
└── tasks.md                                  # Lista de tarefas (gerada na próxima etapa via /speckit-tasks)
```

### Source Code (repository root)

```text
bootstrap/
└── app.php                                   # Configuração de rotas de API com apiPrefix: ''

routes/
└── api.php                                   # Definição da rota Route::post('/reset', ResetController::class)

app/
├── Http/
│   └── Controllers/
│       └── Account/
│           └── ResetController.php           # Single-Action Controller (__invoke)
└── UseCases/
    └── Account/
        └── ResetState/
            ├── ResetStateUseCase.php         # Orquestrador da limpeza
            ├── ResetStateUseCaseInput.php    # Input DTO imutável
            └── ResetStateUseCaseOutput.php   # Output DTO imutável

tests/
├── Unit/
│   └── UseCases/
│       └── Account/
│           └── ResetStateUseCaseTest.php     # Teste unitário isolado com mock do repositório
└── Feature/
    └── Account/
        └── ResetEndpointTest.php             # Teste de Feature HTTP (status 200, Content-Type, payload 'OK')
```

**Structure Decision**: Adotada a estrutura padrão do projeto definida no `AGENTS.md` (§2 e §3), centralizando o controller de transporte em `app/Http/Controllers/Account/`, o caso de uso com sua tríade em `app/UseCases/Account/ResetState/`, as rotas de API stateless em `routes/api.php` e os testes espelhados em `tests/Unit/` e `tests/Feature/`.

---

## Complexity Tracking

| Violação | Justificativa Técnica | Alternativa Mais Simples Rejeitada |
| :--- | :--- | :--- |
| *Nenhuma violação identificada* | Todas as decisões seguem estritamente as convenções mínimas exigidas pelo `AGENTS.md`. | N/A |
