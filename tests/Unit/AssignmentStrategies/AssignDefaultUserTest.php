<?php

use Padmission\Tickets\AssignmentStrategies\AssignDefaultUser;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\User;

it('it assign default user with fixed user_id', function () {
    $user = User::factory()->make(['id' => 2]);
    $ticket = Ticket::factory()->make();

    (new AssignDefaultUser($user->id))->assign($ticket);

    expect($ticket->assignee_id)->toEqual(2);
});

it('it assign default user with user_id provided by closure', function () {
    $user = User::factory()->make(['id' => 2]);
    $ticket = Ticket::factory()->make();

    (new AssignDefaultUser(fn () => $user->id))->assign($ticket);

    expect($ticket->assignee_id)->toEqual(2);
});
