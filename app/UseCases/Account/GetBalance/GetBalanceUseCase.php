<?php

declare(strict_types=1);

namespace App\UseCases\Account\GetBalance;

use App\Exceptions\AccountNotFoundException;
use App\Repositories\Contracts\AccountRepositoryInterface;

final class GetBalanceUseCase
{
    public function __construct(
        private AccountRepositoryInterface $repository
    ) {}

    /**
     * @throws AccountNotFoundException
     */
    public function execute(GetBalanceUseCaseInput $input): GetBalanceUseCaseOutput
    {
        $account = $this->repository->find($input->accountId);

        if ($account === null) {
            throw new AccountNotFoundException($input->accountId);
        }

        return new GetBalanceUseCaseOutput(
            balance: $account->balance
        );
    }
}

