<?php

declare(strict_types=1);

namespace Tests\Unit\UseCases\Account;

use App\Repositories\Contracts\AccountRepositoryInterface;
use App\UseCases\Account\ResetState\ResetStateUseCase;
use App\UseCases\Account\ResetState\ResetStateUseCaseInput;
use App\UseCases\Account\ResetState\ResetStateUseCaseOutput;
use PHPUnit\Framework\TestCase;

class ResetStateUseCaseTest extends TestCase
{
    public function testExecutesResetOnRepositoryAndReturnsSuccessfulOutput(): void
    {
        $repositoryMock = $this->createMock(AccountRepositoryInterface::class);
        $repositoryMock->expects($this->once())
            ->method('reset');

        $useCase = new ResetStateUseCase($repositoryMock);
        $input = new ResetStateUseCaseInput();

        $output = $useCase->execute($input);

        $this->assertInstanceOf(ResetStateUseCaseOutput::class, $output);
        $this->assertTrue($output->success);
        $this->assertSame('OK', $output->status);
        $this->assertSame('OK', $output->toValue());
    }

    public function testExecutesResetWhenInputInstantiatedViaFactory(): void
    {
        $repositoryMock = $this->createMock(AccountRepositoryInterface::class);
        $repositoryMock->expects($this->once())
            ->method('reset');

        $useCase = new ResetStateUseCase($repositoryMock);
        $input = ResetStateUseCaseInput::fromArray(['source' => 'test']);

        $output = $useCase->execute($input);

        $this->assertTrue($output->success);
        $this->assertSame('OK', $output->toValue());
    }
}
