<?php

declare(strict_types=1);

namespace App\UseCases\Account\ProcessEvent;

use App\Http\Requests\Account\ProcessEventRequest;

final readonly class ProcessEventUseCaseInput
{
    public function __construct(
        public string $type,
        public int|float $amount,
        public ?string $origin = null,
        public ?string $destination = null,
    ) {}

    public static function fromRequest(ProcessEventRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $amount = $data['amount'] ?? 0;
        $numericAmount = is_numeric($amount) ? (is_int($amount) ? $amount : (float) $amount) : 0;

        return new self(
            type: (string) ($data['type'] ?? ''),
            amount: $numericAmount,
            origin: isset($data['origin']) && $data['origin'] !== '' ? (string) $data['origin'] : null,
            destination: isset($data['destination']) && $data['destination'] !== '' ? (string) $data['destination'] : null,
        );
    }
}
