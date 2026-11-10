<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Models\Account;
use App\Repositories\Contracts\AccountRepositoryInterface;
use Tests\TestCase;

class ProcessEventEndpointTest extends TestCase
{
    private AccountRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var AccountRepositoryInterface $repository */
        $repository = $this->app->make(AccountRepositoryInterface::class);
        $this->repository = $repository;
        $this->repository->reset();
    }

    public function testCreateAccountWithInitialBalanceOnDeposit(): void
    {
        $response = $this->postJson('/event', [
            'type' => 'deposit',
            'destination' => '100',
            'amount' => 10,
        ]);

        $response->assertStatus(201);
        $response->assertExactJson([
            'destination' => [
                'id' => '100',
                'balance' => 10,
            ],
        ]);

        $account = $this->repository->find('100');
        $this->assertNotNull($account);
        $this->assertEquals(10, $account->balance);
    }

    public function testDepositIntoExistingAccount(): void
    {
        $this->repository->save(new Account(['id' => '100', 'balance' => 10]));

        $response = $this->postJson('/event', [
            'type' => 'deposit',
            'destination' => '100',
            'amount' => 10,
        ]);

        $response->assertStatus(201);
        $response->assertExactJson([
            'destination' => [
                'id' => '100',
                'balance' => 20,
            ],
        ]);

        $account = $this->repository->find('100');
        $this->assertNotNull($account);
        $this->assertEquals(20, $account->balance);
    }

    public function testWithdrawFromExistingAccount(): void
    {
        $this->repository->save(new Account(['id' => '100', 'balance' => 20]));

        $response = $this->postJson('/event', [
            'type' => 'withdraw',
            'origin' => '100',
            'amount' => 5,
        ]);

        $response->assertStatus(201);
        $response->assertExactJson([
            'origin' => [
                'id' => '100',
                'balance' => 15,
            ],
        ]);

        $account = $this->repository->find('100');
        $this->assertNotNull($account);
        $this->assertEquals(15, $account->balance);
    }

    public function testWithdrawFromNonExistingAccountReturnsNotFoundWithZero(): void
    {
        $response = $this->postJson('/event', [
            'type' => 'withdraw',
            'origin' => '200',
            'amount' => 10,
        ]);

        $response->assertStatus(404);
        $this->assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));
        $this->assertSame('0', $response->getContent());
    }

    public function testWithdrawFromAccountWithInsufficientFundsReturnsNotFoundWithZero(): void
    {
        $this->repository->save(new Account(['id' => '100', 'balance' => 5]));

        $response = $this->postJson('/event', [
            'type' => 'withdraw',
            'origin' => '100',
            'amount' => 10,
        ]);

        $response->assertStatus(404);
        $this->assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));
        $this->assertSame('0', $response->getContent());

        $account = $this->repository->find('100');
        $this->assertNotNull($account);
        $this->assertEquals(5, $account->balance);
    }

    public function testTransferFromExistingAccount(): void
    {
        $this->repository->save(new Account(['id' => '100', 'balance' => 15]));

        $response = $this->postJson('/event', [
            'type' => 'transfer',
            'origin' => '100',
            'amount' => 15,
            'destination' => '300',
        ]);

        $response->assertStatus(201);
        $response->assertExactJson([
            'origin' => [
                'id' => '100',
                'balance' => 0,
            ],
            'destination' => [
                'id' => '300',
                'balance' => 15,
            ],
        ]);

        $origin = $this->repository->find('100');
        $destination = $this->repository->find('300');
        $this->assertNotNull($origin);
        $this->assertNotNull($destination);
        $this->assertEquals(0, $origin->balance);
        $this->assertEquals(15, $destination->balance);
    }

    public function testTransferFromNonExistingAccountReturnsNotFoundWithZero(): void
    {
        $response = $this->postJson('/event', [
            'type' => 'transfer',
            'origin' => '200',
            'amount' => 15,
            'destination' => '300',
        ]);

        $response->assertStatus(404);
        $this->assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));
        $this->assertSame('0', $response->getContent());

        $destination = $this->repository->find('300');
        $this->assertNull($destination);
    }

    public function testTransferFromAccountWithInsufficientFundsReturnsNotFoundWithZero(): void
    {
        $this->repository->save(new Account(['id' => '100', 'balance' => 5]));

        $response = $this->postJson('/event', [
            'type' => 'transfer',
            'origin' => '100',
            'amount' => 15,
            'destination' => '300',
        ]);

        $response->assertStatus(404);
        $this->assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));
        $this->assertSame('0', $response->getContent());

        $origin = $this->repository->find('100');
        $destination = $this->repository->find('300');
        $this->assertNotNull($origin);
        $this->assertEquals(5, $origin->balance);
        $this->assertNull($destination);
    }

    public function testRejectsSelfTransferWithUnprocessableContent(): void
    {
        $response = $this->postJson('/event', [
            'type' => 'transfer',
            'origin' => '100',
            'amount' => 15,
            'destination' => '100',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        $response->assertJsonValidationErrors(['destination']);
    }

    public function testRejectsInvalidPayloadWithUnprocessableContentEvenWithoutAcceptJsonHeader(): void
    {
        $response = $this->call('POST', '/event', [
            'type' => 'deposit',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        $response->assertJsonValidationErrors(['destination', 'amount']);
    }
}
