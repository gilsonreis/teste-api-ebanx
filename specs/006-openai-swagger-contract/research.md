# Research: SPEC-06 Especificação Central OpenAPI (Swagger) e Documentação Interativa em /docs

**Feature**: `006-openai-swagger-contract`  
**Status**: Concluído  
**Data**: 2026-11-10  

---

## 1. Biblioteca do Visualizador Interativo de Documentação

### Decisão
Utilizar a biblioteca **Scalar** (`@scalar/api-reference`) servida via CDN oficial (`https://cdn.jsdelivr.net/npm/@scalar/api-reference`) em uma view Blade minimalista (`resources/views/docs.blade.php`), renderizada pelo `DocumentationUiController`.

### Racional
1. **Zero acoplamento ao PHP runtime**: Dispensa pacotes pesados como `darkaonline/l5-swagger` ou anotações PHP attributes/annotations no código-fonte, preservando o `composer.json` limpo e enxuto.
2. **Interface moderna e responsiva**: O Scalar oferece interface de última geração com suporte nativo a temas claro/escuro, cliente HTTP interativo embutido para testes de endpoints e geração automática de snippets em diversas linguagens (cURL, PHP, Python, JS).
3. **Configuração declarativa limpa**: Basta uma tag `<script>` apontando para o arquivo de especificação (`data-url="/docs/openapi.yaml"`), sem necessidade de etapas de compilação de assets com Vite/NPM no deploy.

### Alternativas Consideradas
- **Swagger UI (`swagger-ui-dist` via CDN)**: Interface clássica e amplamente conhecida, porém com visual datado e dependente de múltiplos assets (CSS + bundles JS externos).
- **L5-Swagger / zircote/swagger-php**: Exigiria dezenas de dependências no `composer.json` e anotações prolixas em controllers, violando os princípios de simplicidade e código limpo do `AGENTS.md`.

---

## 2. Estrutura e Localização do Contrato OpenAPI 3.0

### Decisão
Manter o arquivo canônico como fonte única da verdade em `docs/openapi.yaml` na raiz do repositório, servido dinamicamente via `DocumentationSpecController` através de `base_path('docs/openapi.yaml')`.

### Racional
1. **Spec-First e Versionamento Único**: Mantém o arquivo OpenAPI no diretório padrão de governança da documentação (`docs/`), evitando duplicação em `public/` ou rotinas frágeis de links simbólicos (`symlink`).
2. **Atualização Imediata**: Qualquer modificação no arquivo `docs/openapi.yaml` é imediatamente refletida nas requisições `GET /docs/openapi.yaml` e na visualização interativa do Scalar.
3. **Controle de Resposta HTTP**: O controller garante o cabeçalho `Content-Type: text/yaml; charset=UTF-8` e trata graciosamente a ausência do arquivo com status `404 Not Found`.

### Alternativas Consideradas
- **Espelhamento estático em `public/docs/openapi.yaml`**: Exigiria duplicar arquivos ou criar passos manuais no CI/CD ou comandos artisan, gerando risco de dessincronização.
- **Arquivo JSON (`openapi.json`)**: YAML é mais conciso, legível por humanos e facilita a revisão de diffs em Pull Requests.

---

## 3. Arquitetura dos Controllers (Conformidade com AGENTS.md)

### Decisão
Implementar dois **Single-Action Controllers** invokable (`__invoke`) em `app/Http/Controllers/Docs/`:
- `DocumentationUiController.php`: Atende a rota `GET /docs` e retorna a view `docs`.
- `DocumentationSpecController.php`: Atende a rota `GET /docs/openapi.yaml` e retorna a `Response` com o conteúdo YAML ou `404 Not Found`.

### Racional
1. **Conformidade Estrita com `AGENTS.md`**: O manual de engenharia estabelece que controllers atuam puramente como adaptadores de transporte em classes de ação única através de `__invoke`.
2. **Alta Coesão e Baixo Acoplamento**: Cada classe possui uma responsabilidade única, facilitando testes unitários, isolamento de exceções e manutenção.
3. **Roteamento Limpo**: As rotas são registradas diretamente com o nome da classe (`Route::get('/docs', DocumentationUiController::class)`).

### Alternativas Consideradas
- **Controller multi-ação (`DocumentationController`)**: Agruparia `ui()` e `spec()` em uma única classe, mas violaria o padrão obrigatório de Single-Action Controllers estabelecido no checklist do `AGENTS.md`.

---

## 4. Política de Acesso e Segurança

### Decisão
Disponibilizar as rotas `/docs` e `/docs/openapi.yaml` de forma integralmente pública em todos os ambientes (`local`, `testing`, `production`), sem necessidade de autenticação ou restrição por IP.

### Racional
1. Trata-se de uma API pública para desafio técnico (take-home assignment) do EBANX.
2. A documentação deve estar imediatamente acessível aos avaliadores técnicos sem barreiras de autenticação.

### Alternativas Consideradas
- **Proteção por Basic Auth ou IP**: Adicionaria complexidade desnecessária e impediria a avaliação direta da documentação interativa.

