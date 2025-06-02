<?php

use Padmission\Tickets\Http\Controllers\Api;

Route::middleware(['web'])
    ->prefix('padmission-tickets/api/tickets')
    ->group(function () {
        Route::get('/', Api\ListTicketsController::class);
        Route::post('/', Api\CreateTicketController::class);
        Route::get('/{ticket}/messages', Api\ListMessagesController::class);
        Route::post('/{ticket}/messages', Api\CreateMessageController::class);
    });
