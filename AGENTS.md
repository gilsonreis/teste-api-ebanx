# Manual Técnico de Engenharia Backend & APIs (Laravel)

Este documento estabelece as diretrizes arquiteturais, padrões de design e convenções de código obrigatórias para o desenvolvimento de APIs e microsserviços em Laravel. Ele funciona como a especificação canônica a ser seguida por desenvolvedores e agentes autônomos de IA para assegurar previsibilidade, baixo acoplamento, alta coesão e facilidade de manutenção.

---

## 1. Princípios Norteadores da Engenharia

1. **Spec-Driven Development (SDD):** Todo desenvolvimento inicia a partir de contratos e especificações formais (`PRD.md`, `.specs/` ou `openapi.yaml`). A implementação e os testes devem refletir estritamente os critérios de aceitação estabelecidos, coibindo código especulativo.
2. **YAGNI & Simplicidade Pragmática:** A arquitetura deve resolver o problema presente sem antecipar cenários não demandados pela especificação. Abstrações devem ter justificativa técnica tangível.
3. **Fronteiras Explícitas via DTOs (Input/Output):** A camada de aplicação opera isolada do transporte HTTP. Nenhum UseCase deve receber instâncias de `Request`, parâmetros globais ou arrays associativos desestruturados. A comunicação é delimitada por objetos de transferência de dados tipados e imutáveis.
4. **Model Eloquent como Centro de Invariantes:** Utiliza-se o Model padrão do Laravel (`app/Models`) para centralizar as regras de consistência, cálculos e transições de estado por meio de métodos semânticos. Evita-se modelos anêmicos ou a criação de camadas redundantes de POPOs/Entidades puras fora do padrão do framework.
5. **Persistência Plugável (Repository Pattern):** O acesso a dados é abstraído por contratos (interfaces), desacoplando o domínio do mecanismo físico de armazenamento (seja cache em memória, array estático ou banco relacional via Eloquent).
6. **Segurança de Configuração em Produção:** Chamadas à função `env()` são estritamente restritas aos arquivos dentro do diretório `config/`. O runtime da aplicação deve consultar valores exclusivamente via `config()`, preservando a compatibilidade integral com otimizações de cache (`php artisan config:cache`).
7. **Documentação Viva e Spec-First (OpenAPI / Swagger):** Todo endpoint deve ter seu contrato integralmente documentado no schema OpenAPI antes ou concomitantemente à disponibilização do código.

---

## 2. Estrutura Canônica de Diretórios

O projeto organiza seus componentes respeitando a separação de responsabilidades e agrupando UseCases em subdiretórios orientados à ação:

```text
app/
├── Http/
│   ├── Controllers/{Domain}/         # Single-Action Controllers (invokable)
│   └── Requests/{Domain}/            # FormRequests para validação e autorização
├── UseCases/{Domain}/{Action}/       # Tríade da Ação: UseCase, Input DTO e Output DTO
│   ├── {Action}UseCase.php
│   ├── {Action}UseCaseInput.php
│   └── {Action}UseCaseOutput.php
├── Models/                           # Eloquent Models com invariantes e regras encapsuladas
├── Repositories/
│   ├── Contracts/                    # Interfaces puras de persistência
│   └── Implementations/              # Implementações concretas (Cache, Array, Eloquent)
└── Exceptions/                       # Exceções de domínio sem acoplamento a status HTTP

config/
└── {domain}.php                      # Mapeamento do driver de armazenamento e parâmetros

docs/
└── openapi.yaml                      # Especificação central da API (Swagger / OpenAPI 3.0)

tests/
├── Feature/                          # Testes de integração HTTP e validação E2E
└── Unit/                             # Testes unitários de UseCases, DTOs e Models
```

---

## 3. Diretrizes de Implementação por Camada

### 3.1 Camada HTTP (Controllers e FormRequests)
- **Responsabilidade Única:** Controllers atuam puramente como adaptadores de transporte. Devem ser implementados preferencialmente como classes de ação única através do método mágico `__invoke`.
- **Validação Antecipada:** Todas as regras de entrada (formatos, tipos, faixas de valores e condicionalidades) pertencem exclusivamente aos `FormRequests`.
- **Fluxo Operacional:** O Controller apenas obtém os dados validados da requisição, instancia o respectivo `{Action}UseCaseInput`, delega a execução ao UseCase e transforma o `{Action}UseCaseOutput` na resposta HTTP cabível.
- **Mapeamento de Falhas:** Exceções de domínio disparadas pelas camadas inferiores devem ser capturadas no Controller e traduzidas nos códigos e formatos HTTP esperados (por exemplo, transformando erros de entidade inexistente ou saldo indisponível em respostas `404`).

### 3.2 Camada de Aplicação (UseCases & DTOs)
- **Agrupamento de Ação:** Para cada operação de negócio, cria-se um diretório dedicado (`app/UseCases/{Domain}/{Action}/`) contendo a tríade: o caso de uso, seu DTO de entrada e seu DTO de saída.
- **DTOs Imutáveis:** Os objetos `Input` e `Output` devem utilizar propriedades declaradas como `readonly` e tipadas estritamente. O DTO de entrada pode dispor de métodos estáticos de fábrica (como `fromArray`) para converter dados normalizados vindos dos controllers.
- **Orquestração Pura:** O UseCase contém o fluxo procedural do processo: valida pré-condições por meio de guard clauses, consulta ou instancia os Models através de interfaces de repositório, invoca os métodos de negócio nos Models e persiste as alterações via repositório.
- **Inversão de Dependências:** O UseCase nunca referencia drivers concretos ou classes de infraestrutura; depende estritamente das interfaces definidas em `Repositories/Contracts`.

### 3.3 Camada de Domínio (Models Eloquent)
- **Defesa de Invariantes:** Métodos que alteram o estado da entidade (como depósitos, baixas, transições de status ou débitos) devem residir dentro da própria classe do Model.
- **Eliminação do Modelo Anêmico:** Atribuições diretas de propriedades críticas a partir de camadas externas (como cálculos manuais no UseCase) são proibidas. O Model deve validar sua consistência interna antes de permitir mutações.

### 3.4 Camada de Persistência (Repository Pattern Plugável)
- **Contrato Universal:** Cada aggregate ou Model possui uma interface em `Repositories/Contracts` contendo apenas as assinaturas essenciais de busca, salvamento e expurgo de dados.
- **Isolamento de Mecanismos:** Podem coexistir implementações concretas baseadas em Cache, Arrays em memória estática para testes de alta performance ou Eloquent para bancos relacionais.
- **Resolução via Injeção de Dependências:** A vinculação da interface à implementação concreta ocorre no `AppServiceProvider`, consumindo a chave correspondente do arquivo em `config/` por meio de uma estrutura `match`. Isso assegura troca dinâmica de mecanismo via ambiente sem modificações no código da aplicação.

---

## 4. Governança de Documentação OpenAPI (Swagger)

A especificação da API deve seguir o modelo **Spec-First**, com centralização no arquivo `docs/openapi.yaml` (espelhado publicamente para acesso de documentação estática):

1. **Sincronismo Obrigatório:** Nenhuma rota de API pode ser mesclada na ramificação principal sem que seu respectivo path, métodos e schemas estejam declarados no `openapi.yaml`.
2. **Completude Contratual:** A documentação deve especificar parâmetros de URL, query strings, headers requeridos, schemas rigorosos para payloads de entrada (com tipos e exemplos) e a totalidade das respostas HTTP possíveis (sucessos, validações incorretas e exceções).
3. **Interface Visual Autocontida:** A rota `/docs` deve renderizar a documentação interativa (Swagger UI ou Scalar) consumindo diretamente o arquivo estático da especificação via CDN, prescindindo de bibliotecas pesadas de anotação acopladas ao código-fonte.

---

## 5. Diretrizes de Qualidade e Boas Práticas

1. **Guard Clauses e Saída Antecipada:** Elimina-se o encadeamento de blocos condicionais (`else` / `elseif`). Casos de exceção, validações negativas ou estados inválidos devem disparar saídas imediatas ou exceções.
2. **Atomicidade em Operações Compostas:** Em operações que envolvam alteração concorrente de mais de uma entidade (como transferências entre contas), garante-se a integridade global: caso a operação falhe em um dos nós, nenhum efeito colateral deve ser consolidado no outro.
3. **Idempotência de Operações de Leitura:** Endpoints sob o verbo `GET` destinam-se exclusivamente à consulta e jamais devem gerar efeitos colaterais ou alterar estados de armazenamento.
4. **Tipagem Estrita Global:** Todo arquivo PHP deve iniciar com a declaração `declare(strict_types=1);`, impondo tipos explícitos em argumentos, retornos e propriedades.

---

## 6. Checklist de Aceite e Revisão de Pull Request

- [ ] A rota está mapeada para um Single-Action Controller devidamente tipado?
- [ ] O `FormRequest` valida exaustivamente os dados da requisição antes do controller?
- [ ] O Controller converte a entrada em um `{Action}UseCaseInput` tipado e delega a execução?
- [ ] A ação de negócio está concentrada no `{Action}UseCase` dentro de seu diretório específico?
- [ ] O UseCase retorna um `{Action}UseCaseOutput` tipado, desacoplado de estruturas HTTP?
- [ ] As alterações de estado e regras de negócio estão encapsuladas nos métodos do Model Eloquent?
- [ ] A persistência e consultas são efetuadas exclusivamente através de `RepositoryInterface`?
- [ ] A resolução do repositório no Provider respeita a leitura via `config()` com estrutura `match`?
- [ ] O contrato do endpoint está fielmente documentado no `openapi.yaml` com todos os códigos de retorno?
- [ ] A visualização interativa em `/docs` opera em conformidade com o contrato?
- [ ] A suíte de testes (Feature e Unit) cobre fluxos felizes, falhas de negócio e idempotência de leitura?
- [ ] A base de código está validada pelas ferramentas de análise estática e padronização de estilo sem apontamentos?
