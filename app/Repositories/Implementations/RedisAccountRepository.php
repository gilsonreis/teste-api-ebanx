<?php

declare(strict_types=1);

namespace App\Repositories\Implementations;

use App\Models\Account;
use App\Repositories\Contracts\AccountRepositoryInterface;
use Illuminate\Support\Facades\Redis;

class RedisAccountRepository implements AccountRepositoryInterface
{
    private const string KEY_PREFIX = 'accounts:';

    private const string INDEX_KEY = 'accounts:index';

    public function find(string $id): ?Account
    {
        $balance = Redis::get(self::KEY_PREFIX . $id);

        if ($balance === null || $balance === false) {
            return null;
        }

        return new Account([
            'id' => $id,
            'balance' => (float) $balance,
        ]);
    }

    public function save(Account $account): void
    {
        Redis::set(self::KEY_PREFIX . $account->id, (string) $account->balance);
        Redis::sadd(self::INDEX_KEY, $account->id);
    }

    public function reset(): void
    {
        $members = Redis::smembers(self::INDEX_KEY);

        if (! is_array($members) || $members === []) {
            return;
        }

        $keys = array_map(
            static fn (mixed $id): string => self::KEY_PREFIX . (string) $id,
            $members,
        );
        $keys[] = self::INDEX_KEY;

        Redis::del(...$keys);
    }
}
