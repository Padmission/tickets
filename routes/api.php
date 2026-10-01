<?php

use Illuminate\Auth\Middleware\Authenticate;
use Padmission\Tickets\Http\Controllers\Api;
use Padmission\Tickets\Http\Middleware\AuthenticateGuests;
use Padmission\Tickets\Http\Middleware\ReadOnlySession;

Route::middleware(['web'])
    ->prefix('padmission-tickets/api')
    ->as('padmission-tickets::.')
    ->group(function () {
        Route::post('/otp-request', Api\RequestOtpController::class)->name('otp.request');
        Route::post('/otp-verify', Api\VerifyOtpController::class)->name('otp.verify');
    });

Route::middleware(['web', AuthenticateGuests::class, Authenticate::class])
    ->prefix('padmission-tickets/api/tickets')
    ->as('padmission-tickets::api.')
    ->group(function () {
        // Pure reads, which never save the session (see ReadOnlySession).
        Route::middleware(ReadOnlySession::class)->group(function () {
            Route::get('/', Api\ListTicketsController::class)->name('index');
            Route::get('/unread-count', Api\UnreadTicketCountController::class)->name('unread-count');
            Route::get('/{ticket}/messages', Api\ListMessagesController::class)->name('messages.index');
        });

        Route::post('/', Api\CreateTicketController::class)->name('store');
        Route::post('/{ticket}/messages', Api\CreateMessageController::class)->name('messages.store');
        Route::post('/{ticket}/mark-seen', Api\MarkTicketSeenController::class)->name('mark-seen');
        Route::post('/{ticket}/upload-url', Api\TemporaryAttachmentUploadUrlController::class)->name('attachment-url');
        Route::post('/{ticket}/temporary-url', Api\TemporaryAttachmentUrlController::class)->name('temporary-attachment-url');
    });
