<?php

declare(strict_types=1);

namespace App\UseCases\Account\ProcessEvent;

final readonly class ProcessEventUseCaseOutput
{
    /**
     * @param  array{id: string, balance: int|float}|null  $origin
     * @param  array{id: string, balance: int|float}|null  $destination
     */
    public function __construct(
        public ?array $origin = null,
        public ?array $destination = null,
    ) {}

    /**
     * @return array<string, array{id: string, balance: int|float}>
     */
    public function toArray(): array
    {
        return array_filter([
            'origin' => $this->origin,
            'destination' => $this->destination,
        ], static fn (?array $account): bool => $account !== null);
    }
}
