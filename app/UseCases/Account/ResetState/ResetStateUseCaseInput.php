<?php

declare(strict_types=1);

namespace App\UseCases\Account\ResetState;

final readonly class ResetStateUseCaseInput
{
    /**
     * @param  array<string, mixed>  $metadata  Metadados opcionais da requisição
     */
    public function __construct(
        public array $metadata = []
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data = []): self
    {
        return new self($data);
    }
}
