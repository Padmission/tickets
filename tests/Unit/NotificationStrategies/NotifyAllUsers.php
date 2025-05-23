<?php

use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Notifications\TicketCreatedNotification;
use Padmission\Tickets\NotificationStrategies\NotifyAllUsers;
use Padmission\Tickets\Tests\User;

it('it sends a notification to the assigned user', function () {
    Notification::fake();

    $users = User::factory()->count(3)->create();
    $ticket = Ticket::factory()
        ->recycle($users)
        ->create();

    (new NotifyAllUsers)->notify($ticket);

    Notification::assertSentTimes(
        TicketCreatedNotification::class,
        3
    );
});
