<?php

use Illuminate\Support\Facades\Gate;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\User;

it('requires login ', function () {
    $ticket = Ticket::factory()->create();

    $this
        ->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $ticket]))
        ->assertUnauthorized();
});

it('requires create permission', function () {
    Gate::policy(Ticket::class, null);
    Gate::define('create', fn (User $user) => false);

    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();

    $this->actingAs($user);

    $this
        ->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $ticket]))
        ->assertForbidden();
});
