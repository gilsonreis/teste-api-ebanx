<?php

declare(strict_types=1);

use App\Http\Controllers\Account\GetBalanceController;
use App\Http\Controllers\Account\ProcessEventController;
use App\Http\Controllers\Account\ResetController;
use Illuminate\Support\Facades\Route;

Route::post('/reset', ResetController::class);
Route::get('/balance', GetBalanceController::class);
Route::post('/event', ProcessEventController::class);
