<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Padmission\Tickets\Copilot\Http\Controllers\StreamController;

Route::middleware(['web'])
    ->prefix('copilot')
    ->name('filament-copilot.')
    ->group(function () {
        Route::post('/stream', [StreamController::class, 'stream'])
            ->name('stream');
    });
