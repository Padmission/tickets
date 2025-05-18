<?php

use Padmission\Tickets\AssignmentStrategies\AssignUserWithLeastTickets;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\User;

it('it assigns ticket to user with least tickets', function () {
    [$userA, $userB] = User::factory()->count(2)->create();

    Ticket::factory()
        ->for($userA, 'assignee')
        ->create();

    $newTicket = Ticket::factory()->make(['assignee_id' => null]);

    (new AssignUserWithLeastTickets)->assign($newTicket);

    expect($newTicket->assignee_id)->toBe($userB->id);
});
