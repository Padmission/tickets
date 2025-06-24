<?php

use Padmission\Tickets\Http\Controllers\Api;
use Padmission\Tickets\Http\Middleware\AuthenticateGuests;

Route::middleware(['web'])
    ->prefix('padmission-tickets/api')
    ->group(function () {
        Route::post('/otp-request', Api\RequestOtpController::class)->name('padmission-ticket::otp.request');
        Route::post('/otp-verify', Api\VerifyOtpController::class)->name('padmission-ticket::otp.verify');
    });

Route::middleware(['web', AuthenticateGuests::class])
    ->prefix('padmission-tickets/api/tickets')
    ->group(function () {
        Route::get('/', Api\ListTicketsController::class)->name('padmission-ticket::api.index');
        Route::post('/', Api\CreateTicketController::class)->name('padmission-ticket::api.store');
        Route::get('/{ticket}/messages', Api\ListMessagesController::class)->name('padmission-ticket::api.messages.index');
        Route::post('/{ticket}/messages', Api\CreateMessageController::class)->name('padmission-ticket::api.messages.store');
    });
