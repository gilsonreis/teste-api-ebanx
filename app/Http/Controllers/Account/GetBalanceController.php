<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Exceptions\AccountNotFoundException;
use App\Http\Requests\Account\GetBalanceRequest;
use App\UseCases\Account\GetBalance\GetBalanceUseCase;
use App\UseCases\Account\GetBalance\GetBalanceUseCaseInput;
use Illuminate\Http\Response;

final class GetBalanceController
{
    public function __invoke(GetBalanceRequest $request, GetBalanceUseCase $useCase): Response
    {
        $input = GetBalanceUseCaseInput::fromRequest($request);

        try {
            $output = $useCase->execute($input);

            return response($output->toValue(), 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        } catch (AccountNotFoundException) {
            return response('0', 404, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }
    }
}
