<?php

declare(strict_types=1);

namespace Tests\Unit\UseCases\Account;

use App\Exceptions\AccountNotFoundException;
use App\Exceptions\InsufficientFundsException;
use App\Models\Account;
use App\Repositories\Contracts\AccountRepositoryInterface;
use App\UseCases\Account\ProcessEvent\ProcessEventUseCase;
use App\UseCases\Account\ProcessEvent\ProcessEventUseCaseInput;
use App\UseCases\Account\ProcessEvent\ProcessEventUseCaseOutput;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ProcessEventUseCaseTest extends TestCase
{
    public function testDepositCreatesNewAccountWhenItDoesNotExist(): void
    {
        $repositoryMock = $this->createMock(AccountRepositoryInterface::class);

        $repositoryMock->expects($this->once())
            ->method('find')
            ->with('100')
            ->willReturn(null);

        $repositoryMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Account $account): bool {
                return $account->id === '100' && $account->balance === 10.0;
            }));

        $useCase = new ProcessEventUseCase($repositoryMock);
        $input = new ProcessEventUseCaseInput(
            type: 'deposit',
            amount: 10,
            destination: '100',
        );

        $output = $useCase->execute($input);

        $this->assertInstanceOf(ProcessEventUseCaseOutput::class, $output);
        $this->assertNull($output->origin);
        $this->assertSame(['id' => '100', 'balance' => 10.0], $output->destination);
        $this->assertSame([
            'destination' => ['id' => '100', 'balance' => 10.0],
        ], $output->toArray());
    }

    public function testDepositIncrementsBalanceWhenAccountAlreadyExists(): void
    {
        $repositoryMock = $this->createMock(AccountRepositoryInterface::class);
        $existingAccount = new Account(['id' => '100', 'balance' => 10]);

        $repositoryMock->expects($this->once())
            ->method('find')
            ->with('100')
            ->willReturn($existingAccount);

        $repositoryMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Account $account): bool {
                return $account->id === '100' && $account->balance === 20.0;
            }));

        $useCase = new ProcessEventUseCase($repositoryMock);
        $input = new ProcessEventUseCaseInput(
            type: 'deposit',
            amount: 10,
            destination: '100',
        );

        $output = $useCase->execute($input);

        $this->assertSame(['id' => '100', 'balance' => 20.0], $output->destination);
    }

    public function testWithdrawDecrementsBalanceWhenAccountExistsAndHasSufficientFunds(): void
    {
        $repositoryMock = $this->createMock(AccountRepositoryInterface::class);
        $existingAccount = new Account(['id' => '100', 'balance' => 20]);

        $repositoryMock->expects($this->once())
            ->method('find')
            ->with('100')
            ->willReturn($existingAccount);

        $repositoryMock->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Account $account): bool {
                return $account->id === '100' && $account->balance === 15.0;
            }));

        $useCase = new ProcessEventUseCase($repositoryMock);
        $input = new ProcessEventUseCaseInput(
            type: 'withdraw',
            amount: 5,
            origin: '100',
        );

        $output = $useCase->execute($input);

        $this->assertNull($output->destination);
        $this->assertSame(['id' => '100', 'balance' => 15.0], $output->origin);
        $this->assertSame([
            'origin' => ['id' => '100', 'balance' => 15.0],
        ], $output->toArray());
    }

    public function testWithdrawThrowsAccountNotFoundExceptionWhenOriginDoesNotExist(): void
    {
        $repositoryMock = $this->createMock(AccountRepositoryInterface::class);

        $repositoryMock->expects($this->once())
            ->method('find')
            ->with('200')
            ->willReturn(null);

        $repositoryMock->expects($this->never())->method('save');

        $useCase = new ProcessEventUseCase($repositoryMock);
        $input = new ProcessEventUseCaseInput(
            type: 'withdraw',
            amount: 10,
            origin: '200',
        );

        $this->expectException(AccountNotFoundException::class);
        $this->expectExceptionMessage('Account with ID "200" not found.');

        $useCase->execute($input);
    }

    public function testWithdrawThrowsInsufficientFundsExceptionWhenOriginHasInsufficientBalance(): void
    {
        $repositoryMock = $this->createMock(AccountRepositoryInterface::class);
        $existingAccount = new Account(['id' => '100', 'balance' => 5]);

        $repositoryMock->expects($this->once())
            ->method('find')
            ->with('100')
            ->willReturn($existingAccount);

        $repositoryMock->expects($this->never())->method('save');

        $useCase = new ProcessEventUseCase($repositoryMock);
        $input = new ProcessEventUseCaseInput(
            type: 'withdraw',
            amount: 10,
            origin: '100',
        );

        $this->expectException(InsufficientFundsException::class);
        $this->expectExceptionMessage('Insufficient funds for withdrawal.');

        $useCase->execute($input);
    }

    public function testTransferTransfersAmountBetweenAccountsAtomically(): void
    {
        $repositoryMock = $this->createMock(AccountRepositoryInterface::class);
        $originAccount = new Account(['id' => '100', 'balance' => 15]);

        $repositoryMock->expects($this->exactly(2))
            ->method('find')
            ->willReturnCallback(function (string $id) use ($originAccount): ?Account {
                if ($id === '100') {
                    return $originAccount;
                }

                return null; // destination 300 does not exist yet
            });

        $savedAccounts = [];
        $repositoryMock->expects($this->exactly(2))
            ->method('save')
            ->willReturnCallback(function (Account $account) use (&$savedAccounts): void {
                $savedAccounts[$account->id] = $account->balance;
            });

        $useCase = new ProcessEventUseCase($repositoryMock);
        $input = new ProcessEventUseCaseInput(
            type: 'transfer',
            amount: 15,
            origin: '100',
            destination: '300',
        );

        $output = $useCase->execute($input);

        $this->assertSame(['id' => '100', 'balance' => 0.0], $output->origin);
        $this->assertSame(['id' => '300', 'balance' => 15.0], $output->destination);
        $this->assertSame([
            'origin' => ['id' => '100', 'balance' => 0.0],
            'destination' => ['id' => '300', 'balance' => 15.0],
        ], $output->toArray());

        $this->assertSame([
            '100' => 0.0,
            '300' => 15.0,
        ], $savedAccounts);
    }

    public function testTransferThrowsAccountNotFoundExceptionWhenOriginDoesNotExistWithoutCreatingDestination(): void
    {
        $repositoryMock = $this->createMock(AccountRepositoryInterface::class);

        $repositoryMock->expects($this->once())
            ->method('find')
            ->with('200')
            ->willReturn(null);

        $repositoryMock->expects($this->never())->method('save');

        $useCase = new ProcessEventUseCase($repositoryMock);
        $input = new ProcessEventUseCaseInput(
            type: 'transfer',
            amount: 15,
            origin: '200',
            destination: '300',
        );

        $this->expectException(AccountNotFoundException::class);
        $this->expectExceptionMessage('Account with ID "200" not found.');

        $useCase->execute($input);
    }

    public function testTransferThrowsInsufficientFundsExceptionWhenOriginHasInsufficientBalanceWithoutModifyingDestination(): void
    {
        $repositoryMock = $this->createMock(AccountRepositoryInterface::class);
        $originAccount = new Account(['id' => '100', 'balance' => 5]);

        $repositoryMock->expects($this->once())
            ->method('find')
            ->with('100')
            ->willReturn($originAccount);

        $repositoryMock->expects($this->never())->method('save');

        $useCase = new ProcessEventUseCase($repositoryMock);
        $input = new ProcessEventUseCaseInput(
            type: 'transfer',
            amount: 15,
            origin: '100',
            destination: '300',
        );

        $this->expectException(InsufficientFundsException::class);
        $this->expectExceptionMessage('Insufficient funds for withdrawal.');

        $useCase->execute($input);
    }

    public function testThrowsInvalidArgumentExceptionForUnsupportedEventType(): void
    {
        $repositoryMock = $this->createMock(AccountRepositoryInterface::class);

        $useCase = new ProcessEventUseCase($repositoryMock);
        $input = new ProcessEventUseCaseInput(
            type: 'unsupported',
            amount: 10,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported event type: "unsupported".');

        $useCase->execute($input);
    }
}
