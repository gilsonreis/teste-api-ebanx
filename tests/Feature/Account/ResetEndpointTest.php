<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Models\Account;
use App\Repositories\Contracts\AccountRepositoryInterface;
use Tests\TestCase;

class ResetEndpointTest extends TestCase
{
    public function testResetsStateSuccessfullyWhenDatabaseIsAlreadyClean(): void
    {
        $response = $this->post('/reset');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));
        $this->assertSame('OK', $response->getContent());
    }

    public function testResetsStatePurgingExistingAccounts(): void
    {
        /** @var AccountRepositoryInterface $repository */
        $repository = $this->app->make(AccountRepositoryInterface::class);

        $account1 = new Account(['id' => '100', 'balance' => 20]);
        $account2 = new Account(['id' => '200', 'balance' => 50]);

        $repository->save($account1);
        $repository->save($account2);

        $this->assertNotNull($repository->find('100'));
        $this->assertNotNull($repository->find('200'));

        $response = $this->post('/reset');

        $response->assertStatus(200);
        $this->assertSame('OK', $response->getContent());

        $this->assertNull($repository->find('100'));
        $this->assertNull($repository->find('200'));
    }

    public function testResetsStatePermissivelyIgnoringUnexpectedPayload(): void
    {
        $response = $this->postJson('/reset?query=unexpected', [
            'unexpected' => 'data',
            'another_field' => 123,
        ]);

        $response->assertStatus(200);
        $this->assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));
        $this->assertSame('OK', $response->getContent());
    }

    public function testReturnsMethodNotAllowedWhenInvokedWithGetVerb(): void
    {
        $response = $this->get('/reset');

        $response->assertStatus(405);
    }
}

