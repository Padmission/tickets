<?php

use Padmission\Tickets\Http\Controllers\Api;
use Padmission\Tickets\Http\Middleware\AuthenticateGuests;

Route::middleware(['web'])
    ->prefix('padmission-tickets/api')
    ->group(function () {
        Route::post('/otp-request', Api\RequestOtpController::class);
        Route::post('/otp-verify', Api\VerifyOtpController::class);
    });

Route::middleware(['web', AuthenticateGuests::class])
    ->prefix('padmission-tickets/api/tickets')
    ->group(function () {
        Route::get('/', Api\ListTicketsController::class);
        Route::post('/', Api\CreateTicketController::class);
        Route::get('/{ticket}/messages', Api\ListMessagesController::class);
        Route::post('/{ticket}/messages', Api\CreateMessageController::class);
    });
