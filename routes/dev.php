<?php

use App\Models\User;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Events\TicketAssignedEvent;
use Padmission\Tickets\Events\TicketClosedEvent;
use Padmission\Tickets\Events\TicketCreatedEvent;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Notifications\OtpNotification;
use Padmission\Tickets\Notifications\TicketNotification;

Route::get('/mail/created', function () {
    $ticket = Ticket::first();
    $user = User::first();

    return (new TicketNotification($ticket, new TicketCreatedEvent($ticket, $user)))->toMail($user);
});

Route::get('/mail/activity', function () {
    $ticket = Ticket::first();
    $user = User::first();

    return (new TicketNotification($ticket, new TicketActivityEvent($ticket, $user)))->toMail($user);
});

Route::get('/mail/closed', function () {
    $ticket = Ticket::first();
    $user = User::first();

    return (new TicketNotification($ticket, new TicketClosedEvent($ticket, $user)))->toMail($user);
});

Route::get('/mail/assigned', function () {
    $ticket = Ticket::first();
    $user = User::first();

    return (new TicketNotification($ticket, new TicketAssignedEvent($ticket, $user)))->toMail($user);
});

Route::get('/mail/otp', function () {
    return (new OtpNotification(Ticket::first(), 'closed'))->toMail(User::first());
});
