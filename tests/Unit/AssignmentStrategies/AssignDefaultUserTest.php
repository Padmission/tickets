<?php

use Padmission\Tickets\AssignmentStrategies\AssignDefaultUser;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    // Set up plugin with allSupportersQuery for tests
    TicketPlugin::get()
        ->allSupportersQuery(fn () => User::query())
        ->registerResources();
});

it('it assign default user with fixed user_id', function () {
    $user = User::factory()->create(['id' => 2]);
    $ticket = Ticket::factory()->make(['panel' => 'test']);

    (new AssignDefaultUser($user->id))->assign($ticket);

    expect($ticket->assignee_id)->toEqual(2);
});

it('it assign default user with user_id provided by closure', function () {
    $user = User::factory()->create(['id' => 2]);
    $ticket = Ticket::factory()->make(['panel' => 'test']);

    (new AssignDefaultUser(fn () => $user->id))->assign($ticket);

    expect($ticket->assignee_id)->toEqual(2);
});

it('throws exception if user is not in allSupportersQuery', function () {
    // Set up a restricted supporters query
    TicketPlugin::get()
        ->allSupportersQuery(fn () => User::query()->where('id', 999));

    $user = User::factory()->create(['id' => 2]);
    $ticket = Ticket::factory()->make(['panel' => 'test']);

    expect(fn () => (new AssignDefaultUser($user->id))->assign($ticket))
        ->toThrow(\RuntimeException::class, 'not in the allSupportersQuery');
});
