<?php

use Illuminate\Auth\Middleware\Authenticate;
use Padmission\Tickets\Http\Controllers\Api;
use Padmission\Tickets\Http\Middleware\AuthenticateGuests;
use Padmission\Tickets\Http\Middleware\ReadOnlySession;
use Padmission\Tickets\Support\EmailAuthentication;

if (EmailAuthentication::isEnabled()) {
    EmailAuthentication::routes();
}

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

        Route::middleware('throttle:padmission-tickets-writes')->group(function () {
            Route::post('/', Api\CreateTicketController::class)->name('store');
            Route::post('/{ticket}/messages', Api\CreateMessageController::class)->name('messages.store');
            Route::post('/{ticket}/mark-seen', Api\MarkTicketSeenController::class)->name('mark-seen');
            Route::post('/{ticket}/upload-url', Api\TemporaryAttachmentUploadUrlController::class)->name('attachment-url');
            Route::post('/{ticket}/temporary-url', Api\TemporaryAttachmentUrlController::class)->name('temporary-attachment-url');
        });
    });
