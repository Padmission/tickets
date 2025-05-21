<?php

use Illuminate\Notifications\AnonymousNotifiable;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Notifications\TicketCreatedNotification;
use Padmission\Tickets\NotificationStrategies\NotifyEmail;

it('it sends a notification to an email', function () {
    Notification::fake();
    $ticket = Ticket::factory()->make();

    (new NotifyEmail('info@example.com'))->notify($ticket);

    Notification::assertSentTo(
        new AnonymousNotifiable,
        TicketCreatedNotification::class,
        function ($notification, $channels, $notifiable) {
            return $notifiable->routes['mail'] === 'info@example.com';
        }
    );
});

it('can send to multiple emails', function () {
    Notification::fake();
    $ticket = Ticket::factory()->make();

    (new NotifyEmail(['info@example.com', 'info2@example.com']))->notify($ticket);

    Notification::assertSentTimes(
        TicketCreatedNotification::class,
        2
    );
});
