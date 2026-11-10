<?php

declare(strict_types=1);

namespace App\Http\Controllers\Account;

use App\Exceptions\AccountNotFoundException;
use App\Exceptions\InsufficientFundsException;
use App\Http\Requests\Account\ProcessEventRequest;
use App\UseCases\Account\ProcessEvent\ProcessEventUseCase;
use App\UseCases\Account\ProcessEvent\ProcessEventUseCaseInput;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class ProcessEventController
{
    public function __invoke(ProcessEventRequest $request, ProcessEventUseCase $useCase): JsonResponse|Response
    {
        $input = ProcessEventUseCaseInput::fromRequest($request);

        try {
            $output = $useCase->execute($input);

            return response()->json($output->toArray(), 201);
        } catch (AccountNotFoundException|InsufficientFundsException) {
            return response('0', 404, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }
    }
}
