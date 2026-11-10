# SPEC-02: Contrato de Repositório, Persistência Plugável e Resolução Dinâmica

> **Diretriz Mandatória para Agentes de IA e Engenheiros de Software:**  
> Antes de escrever ou refatorar qualquer linha de código referente a esta especificação, **leia obrigatoriamente o arquivo `agents.md` localizado na raiz do projeto**. As regras estabelecidas naquele documento — tipagem estrita (`declare(strict_types=1);`), injeção de dependência por interface pura, proibição estrita de chamadas `env()` fora de `config/`, suporte total ao cache de configuração (`php artisan config:cache`) e cobertura de testes automatizados com mocks/implementações reais — devem ser seguidas de forma irrestrita.

---

## 1. Contexto e Objetivo Arquitetural

Esta especificação define o contrato universal de persistência para a entidade `Account` e suas implementações concretas intercambiáveis em tempo de execução.

A aplicação deve ser capaz de alternar entre três mecanismos de armazenamento através de uma única variável de configuração:
1. **Cache (`cache`):** Armazenamento em memória/arquivo utilizando a facade `Cache` nativa do Laravel (ideal para desenvolvimento local e ambientes efêmeros sem banco).
2. **SQLite / Banco Relacional (`database`):** Persistência relacional utilizando o Model Eloquent `Account` sobre SQLite (ideal para auditoria, histórico e testes de integração relacional).
3. **Redis (`redis`):** Persistência de alta performance em memória via conexão direta ao Redis (ideal para ambientes de produção com concorrência escalável no Laravel Cloud).

O chaveamento entre esses drivers é feito exclusivamente no `AppServiceProvider`, consumindo `config('account.driver')` através de uma expressão `match`.

---

## 2. Contrato de Interface (`AccountRepositoryInterface`)

O contrato puro deve residir em `app/Repositories/Contracts/AccountRepositoryInterface.php` e definir as assinaturas fundamentais para o ciclo de vida da conta:

- **`find(string $id): ?Account`**  
  Busca uma conta pelo identificador único. Retorna a instância do Model `Account` populada ou `null` caso não exista. Deve ser idempotente e não produzir efeitos colaterais.
- **`save(Account $account): void`**  
  Persiste ou atualiza o estado atual da conta no meio de armazenamento correspondente.
- **`reset(): void`**  
  Limpa integralmente o estado das contas no meio de armazenamento configurado (suporte direto ao endpoint `POST /reset` do desafio).

---

## 3. Implementações Concretas dos Drivers

As classes concretas residem em `app/Repositories/Implementations/` e implementam integralmente `AccountRepositoryInterface`:

### 3.1 `CacheAccountRepository` (Driver: `cache`)
- **Mecanismo:** Utiliza a facade `Illuminate\Support\Facades\Cache`.
- **Estratégia de Chave:** `account:{id}` para armazenamento individual e um registro ou tag/lista para identificação no expurgo.
- **Serialização:** Armazena o estado como array ou objeto serializado, reconstruindo uma instância de `Account` ao recuperar.
- **Comportamento do `reset()`:** Expurga as chaves de contas do cache ou executa `Cache::flush()`.

### 3.2 `SqliteAccountRepository` / `EloquentAccountRepository` (Driver: `database`)
- **Mecanismo:** Utiliza o Model Eloquent `App\Models\Account` sobre a conexão configurada (SQLite).
- **Operações:**
  - `find`: Invoca `Account::find($id)`.
  - `save`: Invoca `$account->save()`.
  - `reset`: Trunca ou remove os registros da tabela (`Account::query()->delete()`).
- **Migração:** Tabela `accounts` com coluna `id` (string, primary) e `balance` (decimal/float).

### 3.3 `RedisAccountRepository` (Driver: `redis`)
- **Mecanismo:** Utiliza a facade `Illuminate\Support\Facades\Redis`.
- **Estratégia de Chave:** Chaves prefixadas `accounts:{id}` e um conjunto (`Set`) `accounts:index` para rastrear as contas ativas.
- **Operações:**
  - `find`: Recupera o saldo da hash/chave Redis e hidrata uma instância de `Account`.
  - `save`: Salva o saldo da conta na chave e registra o `id` no conjunto `accounts:index`.
  - `reset`: Itera sobre o conjunto `accounts:index`, remove todas as chaves associadas e limpa o conjunto (ou `Redis::flushdb()` se em conexão isolada).

---

## 4. Configuração e Service Provider

### 4.1 Arquivo de Configuração (`config/account.php`)
```php
return [
    'driver' => env('ACCOUNT_STORAGE_DRIVER', 'cache'),
];
```

### 4.2 Resolução Dinâmica no `AppServiceProvider`
No método `register()` de `app/Providers/AppServiceProvider.php`, o container deve vincular a interface à implementação apropriada:

- Lê exclusivamente via `config('account.driver', 'cache')`.
- Utiliza a estrutura `match` do PHP 8.
- Lança `InvalidArgumentException` caso o driver configurado não seja suportado (`cache`, `database`, `redis`).

---

## 5. Mapeamento de Arquivos da Entrega

```text
config/
└── account.php

database/migrations/
└── xxxx_xx_xx_create_accounts_table.php

app/
├── Repositories/
│   ├── Contracts/
│   │   └── AccountRepositoryInterface.php
│   └── Implementations/
│       ├── CacheAccountRepository.php
│       ├── EloquentAccountRepository.php
│       └── RedisAccountRepository.php
└── Providers/
    └── AppServiceProvider.php

tests/
└── Unit/
    └── Repositories/
        ├── CacheAccountRepositoryTest.php
        ├── EloquentAccountRepositoryTest.php
        └── RedisAccountRepositoryTest.php
```

---

## 6. Critérios de Aceitação e Cenários BDD (Gherkin)

```gherkin
Feature: Account Repository Persistence Drivers

  Scenario Outline: Persistência e busca de contas em diferentes drivers
    Given que o driver de repositório configurado seja "<driver>"
    When eu salvo uma conta com id "100" e saldo 20
    Then ao buscar pela conta com id "100" o saldo retornado deve ser 20

    Examples:
      | driver   |
      | cache    |
      | database |
      | redis    |

  Scenario Outline: Buscar conta inexistente retorna nulo
    Given que o driver de repositório configurado seja "<driver>"
    When eu busco por uma conta inexistente com id "999"
    Then o resultado deve ser nulo

    Examples:
      | driver   |
      | cache    |
      | database |
      | redis    |

  Scenario Outline: Operação de reset expurga todas as contas
    Given que o driver de repositório configurado seja "<driver>"
    And existem contas com ids "100" e "200" salvas
    When a operação reset for executada
    Then ao buscar pela conta com id "100" o resultado deve ser nulo
    And ao buscar pela conta com id "200" o resultado deve ser nulo

    Examples:
      | driver   |
      | cache    |
      | database |
      | redis    |

  Scenario: Resolução de driver inválido falha antecipadamente
    Given que a configuração "account.driver" seja definida como "invalid_driver"
    When o container do Laravel tentar resolver AccountRepositoryInterface
    Then uma exceção InvalidArgumentException deve ser lançada
```

---

## 7. Checklist de Validação Técnica e Aceite

- [x] Leu e cumpriu rigorosamente as regras do `agents.md`?
- [x] Criou o contrato puro `app/Repositories/Contracts/AccountRepositoryInterface.php`?
- [x] Criou a migration para a tabela `accounts` (suporte ao driver `database`/SQLite)?
- [x] Implementou `CacheAccountRepository` com testes unitários cobrindo `find`, `save` e `reset`?
- [x] Implementou `EloquentAccountRepository` com testes sobre conexão SQLite em memória (`:memory:`)?
- [x] Implementou `RedisAccountRepository` garantindo limpeza e indexação de chaves?
- [x] Criou o arquivo `config/account.php` e configurou a resolução por `match` no `AppServiceProvider`?
- [x] Verificou se `php artisan config:cache` roda sem erros e preserva o chaveamento do driver?
- [x] Garantiu que todos os testes passem (`php artisan test`)?
- [x] O código passou sem alertas no Laravel Pint (`vendor/bin/pint --test`) e no Larastan (`vendor/bin/phpstan analyse`)?
