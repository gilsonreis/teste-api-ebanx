<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts\AccountRepositoryInterface;
use App\Repositories\Implementations\CacheAccountRepository;
use App\Repositories\Implementations\EloquentAccountRepository;
use App\Repositories\Implementations\RedisAccountRepository;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AccountRepositoryInterface::class, function (): AccountRepositoryInterface {
            $driver = config('account.driver', 'cache');
            $driverName = is_string($driver) ? $driver : 'invalid';

            return match ($driverName) {
                'cache' => new CacheAccountRepository(),
                'database' => new EloquentAccountRepository(),
                'redis' => new RedisAccountRepository(),
                default => throw new InvalidArgumentException("Unsupported account storage driver [{$driverName}]."),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
