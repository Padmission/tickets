<?php

use Padmission\Tickets\AssignmentStrategies\AssignUserWithLeastTickets;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\User;

it('it assigns ticket to user with least tickets', function () {
    [$userA, $userB] = User::factory()->count(2)->create();

    Ticket::factory()
        ->recycle($userA)
        ->for($userA, 'assignee')
        ->create([
            'closed_at' => null,
        ]);

    Ticket::factory()
        ->recycle($userB)
        ->for($userB, 'assignee')
        ->count(2)
        ->create([
            'closed_at' => now(),
        ]);

    $newTicket = Ticket::factory()
        ->recycle($userB)
        ->make(['assignee_id' => null]);

    (new AssignUserWithLeastTickets)->assign($newTicket);

    expect($newTicket->assignee_id)->toBe($userB->id);
});
