<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Models\Account;
use App\Repositories\Contracts\AccountRepositoryInterface;
use Tests\TestCase;

class GetBalanceEndpointTest extends TestCase
{
    public function testReturnsBalanceSuccessfullyWhenAccountExists(): void
    {
        /** @var AccountRepositoryInterface $repository */
        $repository = $this->app->make(AccountRepositoryInterface::class);
        $repository->reset();

        $account = new Account(['id' => '100', 'balance' => 20]);
        $repository->save($account);

        $response = $this->get('/balance?account_id=100');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));
        $this->assertSame('20', $response->getContent());
    }

    public function testReturnsNotFoundWithZeroWhenAccountDoesNotExist(): void
    {
        /** @var AccountRepositoryInterface $repository */
        $repository = $this->app->make(AccountRepositoryInterface::class);
        $repository->reset();

        $response = $this->get('/balance?account_id=1234');

        $response->assertStatus(404);
        $this->assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));
        $this->assertSame('0', $response->getContent());
    }

    public function testGuaranteesStrictIdempotencyAcrossMultipleReads(): void
    {
        /** @var AccountRepositoryInterface $repository */
        $repository = $this->app->make(AccountRepositoryInterface::class);
        $repository->reset();

        $account = new Account(['id' => '100', 'balance' => 20]);
        $repository->save($account);

        for ($i = 0; $i < 3; $i++) {
            $response = $this->get('/balance?account_id=100');
            $response->assertStatus(200);
            $this->assertSame('20', $response->getContent());
        }

        $persistedAccount = $repository->find('100');
        $this->assertNotNull($persistedAccount);
        $this->assertEquals(20, $persistedAccount->balance);
    }

    public function testRejectsRequestWithoutAccountIdWithUnprocessableContent(): void
    {
        $response = $this->get('/balance');

        $response->assertStatus(422);
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        $response->assertJsonValidationErrors(['account_id']);
    }

    public function testReturnsMethodNotAllowedWhenInvokedWithPostVerb(): void
    {
        $response = $this->post('/balance');

        $response->assertStatus(405);
    }
}
