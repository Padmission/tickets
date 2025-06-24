<?php

use Padmission\Tickets\Http\Controllers\Api;
use Padmission\Tickets\Http\Middleware\AuthenticateGuests;

Route::middleware(['web'])
    ->prefix('padmission-tickets/api')
    ->as('padmission-tickets::.')
    ->group(function () {
        Route::post('/otp-request', Api\RequestOtpController::class)->name('otp.request');
        Route::post('/otp-verify', Api\VerifyOtpController::class)->name('otp.verify');
    });

Route::middleware(['web', AuthenticateGuests::class])
    ->prefix('padmission-tickets/api/tickets')
    ->as('padmission-tickets::api.')
    ->group(function () {
        Route::get('/', Api\ListTicketsController::class)->name('index');
        Route::post('/', Api\CreateTicketController::class)->name('store');
        Route::get('/{ticket}/messages', Api\ListMessagesController::class)->name('messages.index');
        Route::post('/{ticket}/messages', Api\CreateMessageController::class)->name('messages.store');
    });
