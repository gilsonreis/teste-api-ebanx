<?php

declare(strict_types=1);

namespace Tests\Unit\UseCases\Account;

use App\Exceptions\AccountNotFoundException;
use App\Models\Account;
use App\Repositories\Contracts\AccountRepositoryInterface;
use App\UseCases\Account\GetBalance\GetBalanceUseCase;
use App\UseCases\Account\GetBalance\GetBalanceUseCaseInput;
use App\UseCases\Account\GetBalance\GetBalanceUseCaseOutput;
use PHPUnit\Framework\TestCase;

class GetBalanceUseCaseTest extends TestCase
{
    public function testReturnsBalanceWhenAccountExists(): void
    {
        $repositoryMock = $this->createMock(AccountRepositoryInterface::class);
        $account = new Account(['id' => '100', 'balance' => 20]);

        $repositoryMock->expects($this->once())
            ->method('find')
            ->with('100')
            ->willReturn($account);

        $useCase = new GetBalanceUseCase($repositoryMock);
        $input = new GetBalanceUseCaseInput('100');

        $output = $useCase->execute($input);

        $this->assertInstanceOf(GetBalanceUseCaseOutput::class, $output);
        $this->assertEquals(20, $output->balance);
        $this->assertSame('20', $output->toValue());
    }

    public function testThrowsAccountNotFoundExceptionWhenAccountDoesNotExist(): void
    {
        $repositoryMock = $this->createMock(AccountRepositoryInterface::class);

        $repositoryMock->expects($this->once())
            ->method('find')
            ->with('999')
            ->willReturn(null);

        $useCase = new GetBalanceUseCase($repositoryMock);
        $input = new GetBalanceUseCaseInput('999');

        $this->expectException(AccountNotFoundException::class);
        $this->expectExceptionMessage('Account with ID "999" not found.');

        $useCase->execute($input);
    }

    public function testInstantiatesInputViaFactoryFromArray(): void
    {
        $input = GetBalanceUseCaseInput::fromArray(['account_id' => '1234']);

        $this->assertSame('1234', $input->accountId);
    }
}
