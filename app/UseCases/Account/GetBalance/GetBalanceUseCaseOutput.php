<?php

declare(strict_types=1);

namespace App\UseCases\Account\GetBalance;

final readonly class GetBalanceUseCaseOutput
{
    public function __construct(
        public int|float $balance
    ) {}

    public function toValue(): string
    {
        return (string) $this->balance;
    }
}
