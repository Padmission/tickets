<?php

use Filament\Facades\Filament;
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

it('draws an escalation from the initial pool of the panel it goes to, not the panel it was made on', function () {
    (new TicketStatusSeeder)->run();

    [$appSupporter, $adminSupporter] = User::factory()->count(2)->create();

    TicketPlugin::get('test')->initialAssignmentSupportersQuery(fn () => User::query()->whereKey($appSupporter));
    TicketPlugin::get('test2')->initialAssignmentSupportersQuery(fn () => User::query()->whereKey($adminSupporter));

    $escalation = Ticket::factory()->make(['assignee_id' => null, 'panel' => 'test2']);

    (new AssignUserWithLeastTickets)->assign($escalation);

    expect(Filament::getCurrentPanel()->getId())->toBe('test')
        ->and($escalation->assignee_id)->toBe($adminSupporter->id);
});

it('keeps drawing a panel\'s own tickets from its own initial pool', function () {
    (new TicketStatusSeeder)->run();

    [$appSupporter, $adminSupporter] = User::factory()->count(2)->create();

    TicketPlugin::get('test')->initialAssignmentSupportersQuery(fn () => User::query()->whereKey($appSupporter));
    TicketPlugin::get('test2')->initialAssignmentSupportersQuery(fn () => User::query()->whereKey($adminSupporter));

    $ticket = Ticket::factory()->make(['assignee_id' => null, 'panel' => 'test']);

    (new AssignUserWithLeastTickets)->assign($ticket);

    expect($ticket->assignee_id)->toBe($appSupporter->id);
});

it('leaves a ticket unassigned when the target panel\'s initial pool is empty', function () {
    (new TicketStatusSeeder)->run();

    User::factory()->count(2)->create();

    TicketPlugin::get('test2')->initialAssignmentSupportersQuery(fn () => User::query()->whereRaw('1 = 0'));

    $escalation = Ticket::factory()->make(['assignee_id' => null, 'panel' => 'test2']);

    (new AssignUserWithLeastTickets)->assign($escalation);

    expect($escalation->assignee_id)->toBeNull();
});
