<?php

declare(strict_types=1);

namespace App\UseCases\Account\GetBalance;

use App\Http\Requests\Account\GetBalanceRequest;

final readonly class GetBalanceUseCaseInput
{
    public function __construct(
        public string $accountId
    ) {}

    public static function fromRequest(GetBalanceRequest $request): self
    {
        /** @var string $accountId */
        $accountId = $request->validated('account_id');

        return new self($accountId);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self((string) ($data['account_id'] ?? ''));
    }
}

