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
