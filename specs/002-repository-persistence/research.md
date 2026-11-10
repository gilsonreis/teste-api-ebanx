# Phase 0 Research: Contrato de Repositório, Persistência Plugável e Resolução Dinâmica

**Feature**: `002-repository-persistence`  
**Date**: 2026-11-10  
**Spec**: `specs/002-repository-persistence/spec.md`

---

## 1. Estratégia de Armazenamento e Expurgo no `CacheAccountRepository` (Driver: `cache`)

- **Decision**:
  - Armazenar cada conta sob a chave `account:{id}` usando `Cache::forever("account:{$account->id}", ['id' => $account->id, 'balance' => $account->balance])`.
  - Manter uma chave de índice `account:index` (`list<string>`) contendo os IDs registrados para permitir remoção seletiva das chaves `account:{id}` durante `reset()`, seguida de `Cache::forget('account:index')`.
  - No método `find(string $id): ?Account`, consultar `Cache::get("account:{$id}")`; caso não seja um array válido com `id` e `balance`, retornar `null` (guard clause). Caso exista, reidratar `new Account(['id' => (string) $data['id'], 'balance' => (float) $data['balance']])`.
- **Rationale**: Nem todos os drivers de Cache do Laravel (como `file` ou `database`) suportam `Cache::tags()`. O uso de uma chave de índice `account:index` combinado à limpeza explícita das chaves `account:{id}` funciona de maneira uniforme em qualquer store (`array`, `file`, `database`, `redis`) sem depender de tags e sem exigir `Cache::flush()` global em ambientes compartilhados.
- **Alternatives considered**:
  - *Usar `Cache::tags(['accounts'])->flush()`*: Rejeitado porque o driver `file` e `database` do Laravel não suportam tags de cache, lançando `BadMethodCallException`.
  - *Serializar a instância completa do Model Eloquent no Cache*: Armazenar um array associativo enxuto `['id' => ..., 'balance' => ...]` evita problemas de desserialização de conexões/metadados internos do Eloquent e reconstrói uma instância limpa de `Account`.

---

## 2. Persistência Relacional com `EloquentAccountRepository` e Migration SQLite (Driver: `database`)

- **Decision**:
  - Criar a migration `database/migrations/2026_11_10_000000_create_accounts_table.php` com `$table->string('id')->primary()`, `$table->decimal('balance', 15, 2)->default(0)` e `$table->timestamps()`.
  - No `EloquentAccountRepository`:
    - `find(string $id): ?Account`: retornar `Account::query()->find($id)`.
    - `save(Account $account): void`: utilizar `Account::query()->updateOrCreate(['id' => $account->id], ['balance' => $account->balance])` (ou sincronizar `$account->exists` e chamar `$account->save()`).
    - `reset(): void`: executar `Account::query()->delete()`.
- **Rationale**: Como `Account` pode ser instanciado em memória via `new Account(['id' => '100', 'balance' => 20])` (onde `$account->exists` é `false`) e salvo múltiplas vezes ou atualizado após já existir no banco, `Account::query()->updateOrCreate(['id' => $account->id], ['balance' => $account->balance])` (ou verificar existência antes de salvar) garante idempotência de escrita sem violação de `UNIQUE constraint failed: accounts.id` no SQLite quando uma instância recém-hidratada em memória atualiza uma conta existente. Além disso, incluir `$table->timestamps()` na migration mantém compatibilidade nativa com `App\Models\Account` sem exigir alterações no Model entregue na `SPEC-01`.
- **Alternatives considered**:
  - *Chamar apenas `$account->save()` diretamente*: Se um caso de uso ou repositório hidratar `new Account(['id' => '100', 'balance' => 50])` para uma conta já persistida no SQLite, `$account->exists` seria `false` e `$account->save()` tentaria um `INSERT` duplicado. Usar `updateOrCreate` (ou sincronizar `$account->exists = true`) evita colisões de chave primária.

---

## 3. Persistência e Testes Isolados no `RedisAccountRepository` (Driver: `redis`)

- **Decision**:
  - Utilizar a facade `Illuminate\Support\Facades\Redis`.
  - `save(Account $account): void`: armazenar o saldo em `Redis::set("accounts:{$account->id}", (string) $account->balance)` e registrar o identificador no conjunto `Redis::sadd('accounts:index', $account->id)`.
  - `find(string $id): ?Account`: buscar `Redis::get("accounts:{$id}")`; se `null` ou `false`, retornar `null` via guard clause; caso contrário, retornar `new Account(['id' => $id, 'balance' => (float) $balance])`.
  - `reset(): void`: obter todos os membros via `Redis::smembers('accounts:index')`; se houver IDs, remover cada chave `"accounts:{$id}"` com `Redis::del(...)` e remover a chave `'accounts:index'`.
  - Nos testes unitários (`tests/Unit/Repositories/RedisAccountRepositoryTest.php`), simular o armazenamento Redis em memória através de `Redis::shouldReceive(...)` sob a facade do Laravel caso um servidor Redis ou `ext-redis` não esteja disponível no ambiente de CI/local.
- **Rationale**: O ambiente PHP de execução/CI pode não possuir a extensão nativa `ext-redis` instalada nem um daemon `redis-server` ativo em `127.0.0.1:6379`. O uso de `Illuminate\Support\Facades\Redis` permite que o `RedisAccountRepository` execute comandos reais em produção e seja validado deterministicamente em testes unitários via Mockery/Facade sem dependências externas de rede.
- **Alternatives considered**:
  - *Exigir daemon Redis ativo para rodar `php artisan test`*: Fragilizaria a suíte de testes unitários em máquinas locais e pipelines sem serviço Redis.
  - *Usar `Redis::flushdb()` no `reset()`*: Remover apenas as chaves indexadas em `accounts:index` é mais seguro em instâncias Redis compartilhadas com filas/sessões, cumprindo exatamente a estratégia descrita na Seção 3.3 da `spec.md`.

---

## 4. Resolução Dinâmica no `AppServiceProvider` e Compatibilidade com `config:cache`

- **Decision**:
  - Criar `config/account.php` contendo exclusivamente `'driver' => env('ACCOUNT_STORAGE_DRIVER', 'cache')`.
  - No método `register()` de `App\Providers\AppServiceProvider`, registrar `$this->app->bind(AccountRepositoryInterface::class, function (): AccountRepositoryInterface { ... })` avaliando `config('account.driver', 'cache')` com `match`:
    - `'cache' => new CacheAccountRepository()`
    - `'database' => new EloquentAccountRepository()`
    - `'redis' => new RedisAccountRepository()`
    - `default => throw new InvalidArgumentException(...)`
- **Rationale**: Ao resolver o driver dentro da closure de `bind()`, a avaliação ocorre sob demanda quando `AccountRepositoryInterface` é solicitado ao container, permitindo que testes alterem `config(['account.driver' => '...'])` dinamicamente em runtime e garantindo que `php artisan config:cache` serialize `config/account.php` sem erros.
- **Alternatives considered**:
  - *Resolver o `match` fora da closure de `bind()` (diretamente no `register()`)**: Impediria que testes alterassem `config(['account.driver' => 'invalid_driver'])` após o boot da aplicação e dispararia exceção antes mesmo do repositório ser solicitado, quebrando o cenário Gherkin *"When o container do Laravel tentar resolver AccountRepositoryInterface"*.

