<?php

declare(strict_types=1);

use App\Http\Controllers\Docs\DocumentationSpecController;
use App\Http\Controllers\Docs\DocumentationUiController;
use Illuminate\Support\Facades\Route;

Route::get('/docs', DocumentationUiController::class)->name('docs.ui');
Route::get('/docs/openapi.yaml', DocumentationSpecController::class)->name('docs.spec');

