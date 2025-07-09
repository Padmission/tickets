<?php

use Padmission\Tickets\AssignmentStrategies\AssignUserWithLeastTickets;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    // Set up plugin with allSupportersQuery for tests
    TicketPlugin::get()
        ->allSupportersQuery(fn () => User::query())
        ->registerResources();
});

it('it assigns ticket to user with least tickets', function () {
    (new TicketStatusSeeder)->run();

    [$userA, $userB] = User::factory()->count(2)->create();

    // userA has 1 OPEN ticket
    Ticket::factory()
        ->recycle($userA)
        ->for($userA, 'assignee')
        ->open()
        ->create();

    // userB has 2 CLOSED tickets
    Ticket::factory()
        ->recycle($userB)
        ->for($userB, 'assignee')
        ->closed()
        ->count(2)
        ->create();

    $newTicket = Ticket::factory()
        ->recycle($userB)
        ->make(['assignee_id' => null, 'panel' => 'test']);

    (new AssignUserWithLeastTickets)->assign($newTicket);

    expect($newTicket->assignee_id)->toBe($userB->id);
});
