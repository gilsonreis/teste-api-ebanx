<?php

declare(strict_types=1);

namespace App\UseCases\Account\ResetState;

use App\Repositories\Contracts\AccountRepositoryInterface;

final class ResetStateUseCase
{
    public function __construct(
        private AccountRepositoryInterface $repository
    ) {}

    public function execute(ResetStateUseCaseInput $input): ResetStateUseCaseOutput
    {
        $this->repository->reset();

        return new ResetStateUseCaseOutput(
            success: true,
            status: 'OK'
        );
    }
}

