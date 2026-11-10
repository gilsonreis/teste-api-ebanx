# SPEC-01: Encapsulamento de Regras de Saldo, Invariantes e Mutações no Model Account

> **Diretriz Mandatória para Agentes de IA e Engenheiros de Software:**  
> Antes de escrever ou refatorar qualquer linha de código referente a esta especificação, **leia obrigatoriamente o arquivo `agents.md` localizado na raiz do projeto**. As regras estabelecidas naquele documento — tipagem estrita (`declare(strict_types=1);`), combate a modelos anêmicos, uso exclusivo do Eloquent Model (`app/Models/Account.php`) sem POPOs redundantes, independência de camadas e escrita prévia de testes unitários automatizados — devem ser seguidas de forma irrestrita.

---

## 1. Contexto e Objetivo Arquitetural

Esta especificação formaliza o contrato, o comportamento e as invariantes de negócio do Model Eloquent `Account` (`app/Models/Account.php`).

O objetivo é transformar o Model na entidade central de domínio, protegendo suas invariantes e assegurando que qualquer modificação de saldo (`deposit`, `withdraw`) ou leitura seja realizada por meio de métodos de negócio semânticos e autocontidos. A entidade deve blindar o estado interno contra inconsistências, impedindo mutações arbitrárias externas e garantindo que nenhuma operação de débito viole a regra de fundos insuficientes.

---

## 2. Requisitos Técnicos e Invariantes de Negócio

### 2.1 Identificação e Estrutura de Atributos
- **Chave Primária (`id`):**
  - Identificador alfanumérico do tipo string (ex: `"100"`).
  - Propriedade `$incrementing = false`.
  - Propriedade `$keyType = 'string'`.
- **Saldo (`balance`):**
  - Atributo numérico (`int|float`), inicializado com `0` caso não informado.
  - Cast nativo no Eloquent para representação decimal/float (`'balance' => 'float'`).
- **Definição de Atributos em Massa:**
  - Configuração explícita de `$fillable = ['id', 'balance']`.

---

### 2.2 Invariantes de Mutação de Estado

#### A. Operação de Depósito (`deposit`)
- **Assinatura:** `public function deposit(int|float $amount): void`
- **Invariantes Obrigatórias:**
  1. O montante recebido (`$amount`) deve ser estritamente positivo (`$amount > 0`). Valores menores ou iguais a zero devem lançar `InvalidArgumentException`.
  2. O saldo interno (`$this->balance`) deve ser incrementado exatamente pelo valor fornecido.
  3. O método atua exclusivamente sobre o estado da instância em memória; persistência e sincronização de cache/storage são atribuições exclusivas do repositório/UseCase.

#### B. Operação de Saque (`withdraw`)
- **Assinatura:** `public function withdraw(int|float $amount): void`
- **Invariantes Obrigatórias:**
  1. O montante recebido (`$amount`) deve ser estritamente positivo (`$amount > 0`). Valores menores ou iguais a zero devem lançar `InvalidArgumentException`.
  2. A conta deve conter saldo igual ou superior ao montante solicitado (`$this->balance >= $amount`).
  3. Caso o saldo seja insuficiente, o método deve obrigatoriamente interromper o fluxo disparando a exceção de domínio `App\Exceptions\InsufficientFundsException`.
  4. Caso os fundos sejam suficientes, o valor deve ser debitado do saldo interno (`$this->balance -= $amount`).

#### C. Idempotência em Consultas
- Métodos acessores ou leituras de propriedades públicas/getters não devem gerar qualquer efeito colateral ou modificação no saldo da conta.

---

## 3. Exceções de Domínio

- **`App\Exceptions\InsufficientFundsException`**
  - Herança direta de `\Exception`.
  - Deve ser agnóstica de contexto HTTP (proibido conter status codes, cabeçalhos ou payloads JSON no corpo da exceção). A tradução de exceções de domínio para respostas HTTP é responsabilidade exclusiva dos Controllers.

---

## 4. Mapeamento de Arquivos da Entrega

```text
app/
├── Exceptions/
│   └── InsufficientFundsException.php
└── Models/
    └── Account.php

tests/
└── Unit/
    └── Models/
        └── AccountTest.php
```

---

## 5. Critérios de Aceitação e Cenários BDD (Gherkin)

A suíte de testes unitários em `tests/Unit/Models/AccountTest.php` deve validar integralmente o comportamento do Model sem acoplamento a banco de dados físico ou HTTP:

```gherkin
Feature: Eloquent Account Model Invariants and Balance Management

  Scenario: Criar conta com atributos válidos
    Given que uma instância de Account seja criada com id "100" e balance 20
    Then o identificador deve ser "100"
    And o saldo registrado deve ser 20

  Scenario: Depósito com valor positivo incrementa saldo
    Given uma conta existente com saldo de 10
    When o método deposit for chamado com valor 15
    Then o saldo da conta deve ser atualizado para 25

  Scenario: Depósito com valor não positivo deve ser rejeitado
    Given uma conta existente com saldo de 10
    When o método deposit for chamado com valor 0 ou negativo
    Then uma exceção InvalidArgumentException deve ser lançada
    And o saldo da conta deve permanecer exatamente 10

  Scenario: Saque com saldo suficiente debita o montante
    Given uma conta existente com saldo de 20
    When o método withdraw for chamado com valor 5
    Then o saldo da conta deve ser reduzido para 15

  Scenario: Saque do valor integral zera o saldo
    Given uma conta existente com saldo de 15
    When o método withdraw for chamado com valor 15
    Then o saldo da conta deve resultar em 0

  Scenario: Saque com saldo insuficiente dispara exceção de domínio
    Given uma conta existente com saldo de 10
    When o método withdraw for chamado com valor 15
    Then a exceção InsufficientFundsException deve ser lançada
    And o saldo da conta deve permanecer intacto em 10

  Scenario: Saque com valor não positivo deve ser rejeitado
    Given uma conta existente com saldo de 20
    When o método withdraw for chamado com valor 0 ou negativo
    Then uma exceção InvalidArgumentException deve ser lançada
    And o saldo da conta deve permanecer inalterado em 20
```

---

## 6. Checklist de Validação Técnica e Aceite

- [x] Leu e aplicou os padrões estabelecidos no `agents.md` antes de codificar?
- [x] Criou a exceção de domínio desacoplada `App\Exceptions\InsufficientFundsException`?
- [x] O arquivo `app/Models/Account.php` utiliza `declare(strict_types=1);` e herda diretamente de `Illuminate\Database\Eloquent\Model`?
- [x] Configurou a chave primária string (`$incrementing = false`, `$keyType = 'string'`, `$fillable`)?
- [x] Encapsulou `deposit()` e `withdraw()` com validações estritas de valores positivos e saldo suficiente?
- [x] Implementou a suíte unitária `tests/Unit/Models/AccountTest.php` cobrindo 100% dos cenários Gherkin?
- [x] Todos os testes unitários foram executados e passaram com sucesso (`php artisan test --filter=AccountTest`)?
- [x] O código passou sem apontamentos no Laravel Pint (`vendor/bin/pint --test`) e no Larastan (`vendor/bin/phpstan analyse`)?
