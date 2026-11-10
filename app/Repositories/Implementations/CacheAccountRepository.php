<?php

declare(strict_types=1);

namespace App\Repositories\Implementations;

use App\Models\Account;
use App\Repositories\Contracts\AccountRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class CacheAccountRepository implements AccountRepositoryInterface
{
    private const string KEY_PREFIX = 'account:';

    private const string INDEX_KEY = 'account:index';

    public function find(string $id): ?Account
    {
        $data = Cache::get(self::KEY_PREFIX . $id);

        if (! is_array($data) || ! isset($data['id'], $data['balance'])) {
            return null;
        }

        return new Account([
            'id' => (string) $data['id'],
            'balance' => (float) $data['balance'],
        ]);
    }

    public function save(Account $account): void
    {
        Cache::forever(self::KEY_PREFIX . $account->id, [
            'id' => $account->id,
            'balance' => $account->balance,
        ]);

        /** @var array<int, string> $index */
        $index = is_array($rawIndex = Cache::get(self::INDEX_KEY)) ? $rawIndex : [];

        if (! in_array($account->id, $index, true)) {
            $index[] = $account->id;
            Cache::forever(self::INDEX_KEY, $index);
        }
    }

    public function reset(): void
    {
        /** @var array<int, string> $index */
        $index = is_array($rawIndex = Cache::get(self::INDEX_KEY)) ? $rawIndex : [];

        foreach ($index as $id) {
            Cache::forget(self::KEY_PREFIX . $id);
        }

        Cache::forget(self::INDEX_KEY);
    }
}
