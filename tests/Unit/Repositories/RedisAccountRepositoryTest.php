<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Account;
use App\Repositories\Contracts\AccountRepositoryInterface;
use App\Repositories\Implementations\CacheAccountRepository;
use App\Repositories\Implementations\EloquentAccountRepository;
use App\Repositories\Implementations\RedisAccountRepository;
use Illuminate\Support\Facades\Redis;
use InvalidArgumentException;
use Tests\TestCase;

class RedisAccountRepositoryTest extends TestCase
{
    /**
     * @var array<string, string>
     */
    private array $kvStore = [];

    /**
     * @var array<string, array<string, bool>>
     */
    private array $setsStore = [];

    private RedisAccountRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kvStore = [];
        $this->setsStore = [];

        Redis::shouldReceive('set')
            ->andReturnUsing(function (string $key, string $value): bool {
                $this->kvStore[$key] = $value;

                return true;
            });

        Redis::shouldReceive('get')
            ->andReturnUsing(function (string $key): ?string {
                return $this->kvStore[$key] ?? null;
            });

        Redis::shouldReceive('sadd')
            ->andReturnUsing(function (string $key, string $member): int {
                $isNew = ! isset($this->setsStore[$key][$member]);
                $this->setsStore[$key][$member] = true;

                return $isNew ? 1 : 0;
            });

        Redis::shouldReceive('smembers')
            ->andReturnUsing(function (string $key): array {
                return array_keys($this->setsStore[$key] ?? []);
            });

        Redis::shouldReceive('del')
            ->andReturnUsing(function (string ...$keys): int {
                $deleted = 0;

                foreach ($keys as $key) {
                    if (isset($this->kvStore[$key]) || isset($this->setsStore[$key])) {
                        unset($this->kvStore[$key], $this->setsStore[$key]);
                        $deleted++;
                    }
                }

                return $deleted;
            });

        $this->repository = new RedisAccountRepository();
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

    public function testUpdatesExistingAccountBalanceInRedis(): void
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

    public function testResetPurgesAllSavedAccountsFromRedis(): void
    {
        $this->repository->save(new Account(['id' => '100', 'balance' => 20]));
        $this->repository->save(new Account(['id' => '200', 'balance' => 50]));

        $this->repository->reset();

        $this->assertNull($this->repository->find('100'));
        $this->assertNull($this->repository->find('200'));
    }

    public function testContainerResolvesConfiguredRepositoryDrivers(): void
    {
        config(['account.driver' => 'cache']);
        $this->assertInstanceOf(CacheAccountRepository::class, $this->app->make(AccountRepositoryInterface::class));

        config(['account.driver' => 'database']);
        $this->assertInstanceOf(EloquentAccountRepository::class, $this->app->make(AccountRepositoryInterface::class));

        config(['account.driver' => 'redis']);
        $this->assertInstanceOf(RedisAccountRepository::class, $this->app->make(AccountRepositoryInterface::class));
    }

    public function testContainerThrowsInvalidArgumentExceptionForUnsupportedDriver(): void
    {
        config(['account.driver' => 'invalid_driver']);

        $this->expectException(InvalidArgumentException::class);

        $this->app->make(AccountRepositoryInterface::class);
    }
}
