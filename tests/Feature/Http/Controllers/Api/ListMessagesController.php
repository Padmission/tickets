<?php

use Illuminate\Support\Facades\Gate;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Tests\User;

it('requires login ', function () {
    $ticket = Ticket::factory()->create();

    $this
        ->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $ticket]))
        ->assertUnauthorized();
});

it('needs to be submitter without permission', function () {
    Gate::policy(Ticket::class, null);
    Gate::define('manage', fn (User $user) => false);

    [$userA, $userB] = User::factory()->count(2)->create();

    $ticketA = Ticket::factory()->create(['submitter_id' => $userA->id]);
    $ticketB = Ticket::factory()->create(['submitter_id' => $userB->id]);

    $this->actingAs($userA);

    $this
        ->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $ticketB]))
        ->assertForbidden();

    $this
        ->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $ticketA]))
        ->assertOk();
});

it('lists messages', function () {
    $this->freezeTime();

    $user = User::factory()->create();

    $this->actingAs($user);

    $ticket = Ticket::factory()
        ->has(TicketActivity::factory()->count(2))
        ->create(['submitter_id' => $user->id]);

    $resp = $this
        ->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $ticket]))
        ->assertOk();

    $json = $resp->getData();

    expect($json)
        ->toHaveKeys(['ticket', 'messages'])
        ->and($json->messages)
        ->toHaveCount(2)
        ->{0}->toHaveKeys(['side', 'user_name', 'content', 'attachments', 'created_at']);
});

it('filters some messages without elevated rights', function () {
    $this->freezeTime();

    $user = User::factory()->create();

    $this->actingAs($user);

    $ticket = Ticket::factory()
        ->has(
            TicketActivity::factory()
                ->sequence(
                    ['type' => ActivityType::Opened],
                    ['type' => ActivityType::TurnChanged],
                )
                ->count(2)
        )
        ->create(['submitter_id' => $user->id]);

    $resp = $this
        ->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $ticket]))
        ->assertOk();

    $json = $resp->getData();

    expect($json->messages)->toHaveCount(1);
});

it('lists messages with offset', function () {
    $this->freezeTime();

    $user = User::factory()->create();

    $this->actingAs($user);

    $ticket = Ticket::factory()
        ->has(TicketActivity::factory()->count(2))
        ->create(['submitter_id' => $user->id]);

    $resp = $this
        ->getJson(route('padmission-tickets::api.messages.index', [
            'ticket' => $ticket,
            'offset' => 1,
        ]))
        ->assertOk();

    $json = $resp->getData();

    expect($json->messages)->toHaveCount(1);
});
