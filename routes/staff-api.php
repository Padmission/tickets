<?php

use Illuminate\Support\Facades\Route;
use Padmission\Tickets\Http\Controllers\Api;
use Padmission\Tickets\Http\Controllers\StaffApi;
use Padmission\Tickets\Http\Middleware\PrepareStaffApiRequest;

/*
 * The staff API (see padmission-tickets.staff_api). The host's middleware
 * authenticates; PrepareStaffApiRequest then runs every request inside the
 * staff panel, which is also what lets the chat API's own write controllers
 * serve here unchanged: one reply, upload and mark-seen path for both.
 */
Route::middleware([...(array) config('padmission-tickets.staff_api.middleware', []), PrepareStaffApiRequest::class])
    ->prefix((string) config('padmission-tickets.staff_api.prefix', 'api/support/v1'))
    ->as('padmission-tickets::staff-api.')
    ->group(function () {
        Route::get('me', StaffApi\MeController::class)->name('me');
        Route::get('changes', StaffApi\ChangesController::class)->name('changes');

        Route::get('tickets', StaffApi\ListTicketsController::class)->name('tickets.index');
        Route::get('tickets/{ticket}', StaffApi\ShowTicketController::class)->whereNumber('ticket')->name('tickets.show');
        Route::get('tickets/{ticket}/activities', StaffApi\ListActivitiesController::class)->whereNumber('ticket')->name('tickets.activities');
        Route::get('tickets/{ticket}/options', StaffApi\TicketOptionsController::class)->whereNumber('ticket')->name('tickets.options');

        Route::middleware('throttle:padmission-tickets-writes')->group(function () {
            Route::patch('tickets/{ticket}', StaffApi\UpdateTicketController::class)->whereNumber('ticket')->name('tickets.update');
            Route::post('tickets/{ticket}/close', StaffApi\CloseTicketController::class)->whereNumber('ticket')->name('tickets.close');
            Route::post('tickets/{ticket}/reopen', StaffApi\ReopenTicketController::class)->whereNumber('ticket')->name('tickets.reopen');

            Route::post('tickets/{ticket}/messages', Api\CreateMessageController::class)->whereNumber('ticket')->name('tickets.messages.store');
            Route::post('tickets/{ticket}/seen', Api\MarkTicketSeenController::class)->whereNumber('ticket')->name('tickets.seen');
            Route::post('tickets/{ticket}/upload-url', Api\TemporaryAttachmentUploadUrlController::class)->whereNumber('ticket')->name('tickets.upload-url');
            Route::post('tickets/{ticket}/attachment-url', Api\TemporaryAttachmentUrlController::class)->whereNumber('ticket')->name('tickets.attachment-url');
        });
    });
