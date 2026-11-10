# SPEC-03: Reset de Estado, Tríade do UseCase e Endpoint POST /reset

> **Diretriz Mandatória para Agentes de IA e Engenheiros de Software:**  
> Antes de escrever ou refatorar qualquer linha de código referente a esta especificação, **leia obrigatoriamente o arquivo `agents.md` localizado na raiz do projeto**. As regras estabelecidas naquele documento — Single-Action Controllers (`__invoke`), agrupamento coeso por ação em `app/UseCases/{Domain}/{Action}/`, DTOs tipados e imutáveis (`Input` e `Output`), persistência estritamente desacoplada via `AccountRepositoryInterface`, respostas HTTP com formato e status codes exatos (`200 OK` com payload `OK` em `text/plain`) e cobertura completa por testes unitários e de feature — devem ser seguidas de forma irrestrita.

---

## 1. Contexto e Objetivo Arquitetural

Esta especificação formaliza a implementação da ação de reset de estado da aplicação, responsável por redefinir todas as contas salvas e restaurar o sistema para seu estado virgem inicial.

O endpoint `POST /reset` é o ponto de partida do ciclo de testes do take-home assignment do EBANX: toda bateria de validação inicia limpando o estado global para garantir previsibilidade e isolamento.

O objetivo é orquestrar essa operação respeitando a arquitetura limpa:
1. Um **Controller invokable** que recebe a requisição HTTP e trata a resposta de transporte.
2. A **Tríade do UseCase** isolada no diretório `app/UseCases/Account/ResetState/`:
   - `ResetStateUseCase.php`
   - `ResetStateUseCaseInput.php`
   - `ResetStateUseCaseOutput.php`
3. A invocação do método `reset()` do contrato `AccountRepositoryInterface` (cuja implementação física varia conforme o driver configurado: cache, database/sqlite ou redis, conforme definido na SPEC-02).

---

## Clarifications

### Session 2026-11-10
- Q: Como a rota `POST /reset` deve ser configurada para operar sem o prefixo `/api` e sem bloqueio por verificação de CSRF? → A: Configurar `routes/api.php` em `bootstrap/app.php` com `apiPrefix: ''`, garantindo o pipeline stateless de API nativo do Laravel (sem sessão, sem cookies e sem CSRF) mantendo as rotas na raiz (`/reset`).
- Q: Como deve ser estruturada a documentação OpenAPI e a interface interativa `/docs` para o endpoint `POST /reset` nesta entrega? → A: A criação de `docs/openapi.yaml` e da rota `/docs` fica explicitamente fora de escopo nesta entrega, concentrando esta especificação exclusivamente na implementação funcional e testes do endpoint `POST /reset`.
- Q: Como o endpoint `POST /reset` deve tratar requisições que enviem corpo (payload) ou parâmetros de query inesperados? → A: Comportamento permissivo: ignorar parâmetros e corpos extras, executando o reset com sucesso e retornando `200 OK` com `'OK'`.

---

## 2. Contrato HTTP do Endpoint

- **Método HTTP:** `POST`
- **Rota:** `/reset` (mapeada sem prefixos indevidos em `routes/api.php`)
- **Corpo da Requisição (Request Body):** Opcional/Vazio. Qualquer payload (como `{}` ou campos arbitrários) ou query params enviados na requisição serão permissivamente ignorados.
- **Código de Resposta:** `200 OK`
- **Cabeçalho de Resposta:** `Content-Type: text/plain; charset=UTF-8`
- **Corpo da Resposta (Response Body):** Texto literal `OK`

---

## 3. Tríade do Caso de Uso (`app/UseCases/Account/ResetState/`)

### 3.1 `ResetStateUseCaseInput`
- **Responsabilidade:** Representa o payload de entrada da operação.
- **Estrutura:** Classe `readonly` com tipagem estrita. Como a requisição não possui parâmetros, atua como objeto semântico de sinalização de disparo, dispondo de construtor sem argumentos ou método de fábrica estático `create()` / `fromArray(array $data = [])`.

### 3.2 `ResetStateUseCaseOutput`
- **Responsabilidade:** Representa o resultado consolidado da execução do UseCase.
- **Estrutura:** Classe `readonly` com tipagem estrita contendo a confirmação do reset (ex: propriedade booleana `public readonly bool $success = true;` ou status string `'OK'`).
- **Métodos:** Fornece método `toValue(): string` ou `toStatus(): string` retornando `'OK'` para consumo desacoplado pelo Controller.

### 3.3 `ResetStateUseCase`
- **Assinatura:** `public function execute(ResetStateUseCaseInput $input): ResetStateUseCaseOutput`
- **Injeção de Dependências:** Recebe exclusivamente `App\Repositories\Contracts\AccountRepositoryInterface`.
- **Orquestração:**
  1. Invoca `$this->repository->reset()`.
  2. Retorna uma instância de `ResetStateUseCaseOutput(success: true)`.

---

## 4. Camada de Apresentação HTTP (Controller & Rota)

### 4.1 Single-Action Controller (`ResetController`)
- **Localização:** `app/Http/Controllers/Account/ResetController.php`
- **Método:** `public function __invoke(ResetStateUseCase $useCase): Response`
- **Comportamento:**
  1. Instancia `ResetStateUseCaseInput`.
  2. Executa `$output = $useCase->execute($input)`.
  3. Retorna uma resposta HTTP nativa do Laravel: `response('OK', 200)->header('Content-Type', 'text/plain')`.
  4. Não contém lógica de limpeza direta, acesso a banco, chamada a facades de cache ou manipulação manual de arquivos.

### 4.2 Registro de Rotas
- Mapeado no arquivo de rotas `routes/api.php` registrado em `bootstrap/app.php` com `apiPrefix: ''`:
  ```php
  Route::post('/reset', ResetController::class);
  ```

---

## 5. Mapeamento de Arquivos da Entrega

```text
bootstrap/
└── app.php (registro de routes/api.php com apiPrefix: '')

routes/
└── api.php

app/
├── Http/
│   └── Controllers/
│       └── Account/
│           └── ResetController.php
├── UseCases/
│   └── Account/
│       └── ResetState/
│           ├── ResetStateUseCase.php
│           ├── ResetStateUseCaseInput.php
│           └── ResetStateUseCaseOutput.php

tests/
├── Unit/
│   └── UseCases/
│       └── Account/
│           └── ResetStateUseCaseTest.php
└── Feature/
    └── Account/
        └── ResetEndpointTest.php
```

---

## 6. Critérios de Aceitação e Cenários BDD (Gherkin)

### 6.1 Testes Unitários (`ResetStateUseCaseTest.php`)
```gherkin
Feature: Reset State UseCase

  Scenario: Executar o caso de uso de reset de estado com sucesso
    Given um repositório AccountRepositoryInterface mockado
    And que o método reset do repositório é configurado para ser executado uma vez
    When o ResetStateUseCase for executado com seu Input
    Then o repositório deve ter seu método reset invocado
    And o Output retornado deve indicar sucesso
```

### 6.2 Testes de Feature HTTP (`ResetEndpointTest.php`)
```gherkin
Feature: POST /reset Endpoint

  Scenario: Resetar o estado com base limpa prévia
    When eu envio uma requisição POST para "/reset"
    Then o status da resposta deve ser 200
    And o cabeçalho "Content-Type" deve conter "text/plain"
    And o corpo da resposta deve ser exatamente "OK"

  Scenario: Resetar o estado ignorando permissivamente payloads arbitrários
    When eu envio uma requisição POST para "/reset" com o payload '{"unexpected": "data"}'
    Then o status da resposta deve ser 200
    And o corpo da resposta deve ser exatamente "OK"

  Scenario: Resetar o estado expurgando contas existentes
    Given que existam contas cadastradas com saldo no sistema
    When eu envio uma requisição POST para "/reset"
    Then o status da resposta deve ser 200
    And o corpo da resposta deve ser "OK"
    And qualquer consulta subsequente por saldo deve retornar status 404
```

---

## 7. Checklist de Validação Técnica e Aceite

- [x] Leu e seguiu estritamente as convenções do `agents.md`?
- [x] Criou o diretório coeso `app/UseCases/Account/ResetState/` contendo a tríade de arquivos (`UseCase`, `Input`, `Output`)?
- [x] O `ResetStateUseCase` depende apenas de `AccountRepositoryInterface`?
- [x] Criou o `ResetController` como Single-Action (`__invoke`) retornando `text/plain` com código `200` e corpo `'OK'`?
- [x] Registrou a rota `POST /reset` sem prefixos incorretos (como `/api/v1/reset`)?
- [x] Criou os testes de unidade cobrindo o `ResetStateUseCase` com mock do repositório?
- [x] Criou os testes de Feature testando a rota `/reset` de ponta a ponta?
- [x] Garantiu que todos os testes passem (`php artisan test`)?
- [x] Formatou o código com o Laravel Pint (`vendor/bin/pint --test`) sem erros de estilo?
- [x] Passou na análise estática do Larastan (`vendor/bin/phpstan analyse`) no nível 6 sem falhas?
