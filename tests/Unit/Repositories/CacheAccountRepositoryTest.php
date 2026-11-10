<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Account;
use App\Repositories\Implementations\CacheAccountRepository;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheAccountRepositoryTest extends TestCase
{
    private CacheAccountRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->repository = new CacheAccountRepository();
    }

    public function testSavesAndFindsAccountById(): void
    {
        $account = new Account([
            'id' => '100',
            'balance' => 20,
        ]);

        $this->repository->save($account);

        $found = $this->repository->find('100');

        $this->assertInstanceOf(Account::class, $found);
        $this->assertSame('100', $found->id);
        $this->assertSame(20.0, $found->balance);
    }

    public function testUpdatesExistingAccountBalanceInCache(): void
    {
        $account = new Account([
            'id' => '100',
            'balance' => 20,
        ]);
        $this->repository->save($account);

        $account->deposit(15);
        $this->repository->save($account);

        $found = $this->repository->find('100');

        $this->assertInstanceOf(Account::class, $found);
        $this->assertSame(35.0, $found->balance);
    }

    public function testFindReturnsNullWhenAccountDoesNotExist(): void
    {
        $found = $this->repository->find('999');

        $this->assertNull($found);
    }

    public function testResetPurgesAllSavedAccountsFromCache(): void
    {
        $this->repository->save(new Account(['id' => '100', 'balance' => 20]));
        $this->repository->save(new Account(['id' => '200', 'balance' => 50]));

        $this->repository->reset();

        $this->assertNull($this->repository->find('100'));
        $this->assertNull($this->repository->find('200'));
    }
}
