<?php

declare(strict_types=1);

namespace App\UseCases\Account\ResetState;

final readonly class ResetStateUseCaseOutput
{
    public function __construct(
        public bool $success = true,
        public string $status = 'OK'
    ) {}

    public function toValue(): string
    {
        return $this->status;
    }
}
