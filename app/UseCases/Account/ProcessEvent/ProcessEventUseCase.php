<?php

declare(strict_types=1);

namespace App\UseCases\Account\ProcessEvent;

use App\Exceptions\AccountNotFoundException;
use App\Exceptions\InsufficientFundsException;
use App\Models\Account;
use App\Repositories\Contracts\AccountRepositoryInterface;
use InvalidArgumentException;

class ProcessEventUseCase
{
    public function __construct(
        private AccountRepositoryInterface $repository
    ) {}

    /**
     * @throws AccountNotFoundException
     * @throws InsufficientFundsException
     * @throws InvalidArgumentException
     */
    public function execute(ProcessEventUseCaseInput $input): ProcessEventUseCaseOutput
    {
        return match ($input->type) {
            'deposit' => $this->handleDeposit($input),
            'withdraw' => $this->handleWithdraw($input),
            'transfer' => $this->handleTransfer($input),
            default => throw new InvalidArgumentException(sprintf('Unsupported event type: "%s".', $input->type)),
        };
    }

    private function handleDeposit(ProcessEventUseCaseInput $input): ProcessEventUseCaseOutput
    {
        if ($input->destination === null) {
            throw new InvalidArgumentException('Destination account ID is required for deposits.');
        }

        $account = $this->repository->find($input->destination)
            ?? new Account(['id' => $input->destination, 'balance' => 0]);

        $account->deposit($input->amount);
        $this->repository->save($account);

        return new ProcessEventUseCaseOutput(
            destination: [
                'id' => $account->id,
                'balance' => $account->balance,
            ],
        );
    }

    private function handleWithdraw(ProcessEventUseCaseInput $input): ProcessEventUseCaseOutput
    {
        if ($input->origin === null) {
            throw new InvalidArgumentException('Origin account ID is required for withdrawals.');
        }

        $account = $this->repository->find($input->origin);

        if ($account === null) {
            throw new AccountNotFoundException(sprintf('Account with ID "%s" not found.', $input->origin));
        }

        $account->withdraw($input->amount);
        $this->repository->save($account);

        return new ProcessEventUseCaseOutput(
            origin: [
                'id' => $account->id,
                'balance' => $account->balance,
            ],
        );
    }

    private function handleTransfer(ProcessEventUseCaseInput $input): ProcessEventUseCaseOutput
    {
        if ($input->origin === null || $input->destination === null) {
            throw new InvalidArgumentException('Both origin and destination account IDs are required for transfers.');
        }

        $origin = $this->repository->find($input->origin);

        if ($origin === null) {
            throw new AccountNotFoundException(sprintf('Account with ID "%s" not found.', $input->origin));
        }

        $origin->withdraw($input->amount);

        $destination = $this->repository->find($input->destination)
            ?? new Account(['id' => $input->destination, 'balance' => 0]);

        $destination->deposit($input->amount);

        $this->repository->save($origin);
        $this->repository->save($destination);

        return new ProcessEventUseCaseOutput(
            origin: [
                'id' => $origin->id,
                'balance' => $origin->balance,
            ],
            destination: [
                'id' => $destination->id,
                'balance' => $destination->balance,
            ],
        );
    }
}
