# Data Model: Account Persistence & Storage Drivers

**Feature**: `002-repository-persistence`  
**Date**: 2026-11-10  
**Spec**: `specs/002-repository-persistence/spec.md`

---

## 1. Entidade Persistida: `App\Models\Account`

A entidade de domínio `App\Models\Account` (definida na `SPEC-01`) é o objeto de transferência e domínio manipulado por todas as implementações de `AccountRepositoryInterface`.

| Atributo | Tipo PHP | Tipo SQLite (`accounts`) | Tipo Cache (`account:{id}`) | Tipo Redis (`accounts:{id}`) |
| :--- | :--- | :--- | :--- | :--- |
| `id` | `string` | `VARCHAR(255) PRIMARY KEY` | `string` (chave no array) | `string` (sufixo da chave Redis e membro de `accounts:index`) |
| `balance` | `float` | `DECIMAL(15, 2) NOT NULL DEFAULT 0` | `float` (valor no array) | `string` numérico convertido para `float` na hidratação |

---

## 2. Esquema Relacional (`database` / SQLite)

**Migration**: `database/migrations/2026_11_10_000000_create_accounts_table.php`  
**Tabela**: `accounts`

| Coluna | Tipo | Nulo | Padrão | Restrições / Índices |
| :--- | :--- | :--- | :--- | :--- |
| `id` | `string` (`varchar`) | Não | — | `PRIMARY KEY` (não auto-incrementável) |
| `balance` | `decimal(15, 2)` | Não | `0` | Saldo financeiro da conta |
| `created_at` | `timestamp` | Sim | `NULL` | Gerenciado pelo Eloquent |
| `updated_at` | `timestamp` | Sim | `NULL` | Gerenciado pelo Eloquent |

---

## 3. Esquema Key-Value no Driver `cache` (`CacheAccountRepository`)

| Chave | Tipo de Valor | Finalidade | Ciclo de Vida / `reset()` |
| :--- | :--- | :--- | :--- |
| `account:{id}` | `array{id: string, balance: float}` | Estado serializado da conta individual | Removido via `Cache::forget("account:{id}")` no `reset()` |
| `account:index` | `list<string>` | Lista de IDs de contas ativas no cache | Removido via `Cache::forget('account:index')` no `reset()` |

---

## 4. Esquema Key-Value no Driver `redis` (`RedisAccountRepository`)

| Chave | Estrutura Redis | Tipo de Valor | Finalidade | Ciclo de Vida / `reset()` |
| :--- | :--- | :--- | :--- | :--- |
| `accounts:{id}` | `STRING` | `string` (representação de `float`) | Saldo atual da conta `{id}` | Removido via `Redis::del("accounts:{id}")` no `reset()` |
| `accounts:index` | `SET` | Conjunto de `string` (`{id}`) | Índice de todas as contas persistidas | Lido via `SMEMBERS` e removido via `Redis::del('accounts:index')` no `reset()` |

---

## 5. Mapeamento de Configuração (`config/account.php`)

| Chave de Configuração | Variável de Ambiente | Valores Suportados | Valor Padrão | Exceção em Valor Inválido |
| :--- | :--- | :--- | :--- | :--- |
| `account.driver` | `ACCOUNT_STORAGE_DRIVER` | `'cache'`, `'database'`, `'redis'` | `'cache'` | `\InvalidArgumentException` ao resolver `AccountRepositoryInterface` |

