<?php

use Illuminate\Support\Facades\Gate;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\Fixtures\Models\CustomTicket;
use Padmission\Tickets\Tests\User;

it('requires login ', function () {
    $this
        ->getJson(route('padmission-tickets::api.index'))
        ->assertUnauthorized();
});

it('requires create permission', function () {
    Gate::policy(Ticket::class, null);
    Gate::define('create', fn (User $user) => false);

    $user = User::factory()->create();

    $this->actingAs($user);

    $this
        ->getJson(route('padmission-tickets::api.index'))
        ->assertForbidden();
});

it('lists users tickets', function () {
    $this->freezeTime();

    [$userA, $userB] = User::factory()->count(2)->create();

    $ticketA = CustomTicket::factory()->create(['submitter_id' => $userA->id]);
    CustomTicket::factory()->create(['submitter_id' => $userB->id]);

    $this->actingAs($userA);

    $resp = $this
        ->getJson(route('padmission-tickets::api.index'))
        ->assertStatus(200);

    expect($resp->json('tickets'))
        ->toHaveCount(1)
        ->{0}->toEqual([
            'id' => $ticketA->id,
            'subject' => $ticketA->subject,
            'latest_message' => null,
            'is_closed' => $ticketA->isClosed,
            'updated_at' => now()->diffForHumans(),
        ]);
});
