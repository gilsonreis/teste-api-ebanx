<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Exceptions\InsufficientFundsException;
use App\Models\Account;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class AccountTest extends TestCase
{
    public function testCreatesAccountWithValidAttributes(): void
    {
        $account = new Account([
            'id' => '100',
            'balance' => 20,
        ]);

        $this->assertSame('100', $account->id);
        $this->assertSame(20.0, $account->balance);
    }

    public function testInitializesBalanceToZeroWhenOmittedAndReadsAreIdempotent(): void
    {
        $account = new Account([
            'id' => '100',
        ]);

        $firstRead = $account->balance;
        $secondRead = $account->balance;

        $this->assertSame('100', $account->id);
        $this->assertSame(0.0, $firstRead);
        $this->assertSame($firstRead, $secondRead);
    }

    public function testDepositWithPositiveAmountIncrementsBalance(): void
    {
        $account = new Account([
            'id' => '100',
            'balance' => 10,
        ]);

        $account->deposit(15);

        $this->assertSame(25.0, $account->balance);
    }

    public function testDepositWithZeroAmountIsRejectedAndPreservesBalance(): void
    {
        $account = new Account([
            'id' => '100',
            'balance' => 10,
        ]);

        try {
            $account->deposit(0);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException) {
            $this->assertSame(10.0, $account->balance);
        }
    }

    public function testDepositWithNegativeAmountIsRejectedAndPreservesBalance(): void
    {
        $account = new Account([
            'id' => '100',
            'balance' => 10,
        ]);

        try {
            $account->deposit(-5);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException) {
            $this->assertSame(10.0, $account->balance);
        }
    }

    public function testWithdrawWithSufficientBalanceDebitsAmount(): void
    {
        $account = new Account([
            'id' => '100',
            'balance' => 20,
        ]);

        $account->withdraw(5);

        $this->assertSame(15.0, $account->balance);
    }

    public function testWithdrawOfFullAmountZerosBalance(): void
    {
        $account = new Account([
            'id' => '100',
            'balance' => 15,
        ]);

        $account->withdraw(15);

        $this->assertSame(0.0, $account->balance);
    }

    public function testWithdrawWithInsufficientBalanceThrowsDomainExceptionAndPreservesBalance(): void
    {
        $account = new Account([
            'id' => '100',
            'balance' => 10,
        ]);

        try {
            $account->withdraw(15);
            $this->fail('Expected InsufficientFundsException was not thrown.');
        } catch (InsufficientFundsException) {
            $this->assertSame(10.0, $account->balance);
        }
    }

    public function testWithdrawWithZeroAmountIsRejectedAndPreservesBalance(): void
    {
        $account = new Account([
            'id' => '100',
            'balance' => 20,
        ]);

        try {
            $account->withdraw(0);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException) {
            $this->assertSame(20.0, $account->balance);
        }
    }

    public function testWithdrawWithNegativeAmountIsRejectedAndPreservesBalance(): void
    {
        $account = new Account([
            'id' => '100',
            'balance' => 20,
        ]);

        try {
            $account->withdraw(-10);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (InvalidArgumentException) {
            $this->assertSame(20.0, $account->balance);
        }
    }
}
