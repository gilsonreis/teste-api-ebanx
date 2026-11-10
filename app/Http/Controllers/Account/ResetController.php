<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\UseCases\Account\ResetState\ResetStateUseCase;
use App\UseCases\Account\ResetState\ResetStateUseCaseInput;
use Illuminate\Http\Response;

final class ResetController
{
    public function __invoke(ResetStateUseCase $useCase): Response
    {
        $input = new ResetStateUseCaseInput();
        $output = $useCase->execute($input);

        return response($output->toValue(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}

