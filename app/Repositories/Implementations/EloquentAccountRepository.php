<?php

declare(strict_types=1);

namespace App\Repositories\Implementations;

use App\Models\Account;
use App\Repositories\Contracts\AccountRepositoryInterface;

class EloquentAccountRepository implements AccountRepositoryInterface
{
    public function find(string $id): ?Account
    {
        return Account::query()->find($id);
    }

    public function save(Account $account): void
    {
        Account::query()->updateOrCreate(
            ['id' => $account->id],
            ['balance' => $account->balance],
        );

        $account->exists = true;
    }

    public function reset(): void
    {
        Account::query()->delete();
    }
}
