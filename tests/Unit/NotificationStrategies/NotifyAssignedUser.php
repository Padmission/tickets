<?php

use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Notifications\TicketCreatedNotification;
use Padmission\Tickets\NotificationStrategies\NotifyAssignedUser;
use Padmission\Tickets\Tests\User;

it('it sends a notification to the assigned user', function () {
    Notification::fake();

    [, $userB] = User::factory()->count(2)->create();
    $ticket = Ticket::factory()->create([
        'assignee_id' => $userB->id,
    ]);

    (new NotifyAssignedUser)->notify($ticket);

    Notification::assertSentTo(
        $userB,
        TicketCreatedNotification::class,
    );
});
