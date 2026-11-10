# Contract: AccountRepositoryInterface & Dynamic Driver Resolution

**Feature**: `002-repository-persistence`  
**Scope**: Persistence Layer Contract (`App\Repositories\Contracts\AccountRepositoryInterface`), Implementations & Service Container Binding

---

## 1. Interface Contract: `App\Repositories\Contracts\AccountRepositoryInterface`

- **File**: `app/Repositories/Contracts/AccountRepositoryInterface.php`
- **Strict Types**: `declare(strict_types=1);`

### Public Signatures

```php
<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Account;

interface AccountRepositoryInterface
{
    /**
     * Find an account by its unique identifier without side effects.
     */
    public function find(string $id): ?Account;

    /**
     * Persist or update the given account state in the underlying storage.
     */
    public function save(Account $account): void;

    /**
     * Purge all stored accounts from the underlying storage mechanism.
     */
    public function reset(): void;
}
```

### Behavioral Postconditions

| Method | Precondition | Postcondition (Success) | Side Effects / Idempotency |
| :--- | :--- | :--- | :--- |
| `find(string $id): ?Account` | `$id` is a non-empty string | Returns hydrated `Account` with matching `id` and `float` `balance`, or `null` if not found | Strictly read-only and idempotent; never mutates storage |
| `save(Account $account): void` | `$account` is a valid `Account` instance | Subsequent `find($account->id)` returns an `Account` with `$account->balance` | Creates or overwrites the record in the active storage driver |
| `reset(): void` | None | Subsequent `find($id)` for any previously saved account returns `null` | Purges all account records in the active storage driver |

---

## 2. Concrete Implementations Contract

| Class | Namespace | Config Driver (`account.driver`) | Underlying Mechanism |
| :--- | :--- | :--- | :--- |
| `CacheAccountRepository` | `App\Repositories\Implementations` | `'cache'` | `Illuminate\Support\Facades\Cache` (`account:{id}`, `account:index`) |
| `EloquentAccountRepository` | `App\Repositories\Implementations` | `'database'` | `App\Models\Account` Eloquent Model (`accounts` table) |
| `RedisAccountRepository` | `App\Repositories\Implementations` | `'redis'` | `Illuminate\Support\Facades\Redis` (`accounts:{id}`, `accounts:index`) |

---

## 3. Service Container Binding Contract (`App\Providers\AppServiceProvider`)

- **Configuration File**: `config/account.php`
- **Configuration Key**: `config('account.driver', 'cache')`
- **Resolution Mechanism**:

```php
$this->app->bind(AccountRepositoryInterface::class, function (): AccountRepositoryInterface {
    $driver = config('account.driver', 'cache');

    return match ($driver) {
        'cache' => new CacheAccountRepository,
        'database' => new EloquentAccountRepository,
        'redis' => new RedisAccountRepository,
        default => throw new InvalidArgumentException("Unsupported account storage driver [{$driver}]."),
    };
});
```

- **Failure Mode**: Resolving `AccountRepositoryInterface::class` from the container when `config('account.driver')` is not one of `'cache'`, `'database'`, or `'redis'` MUST throw `\InvalidArgumentException`.

