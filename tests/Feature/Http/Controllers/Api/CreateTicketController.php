<?php

use Illuminate\Support\Facades\Gate;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Tests\User;

it('requires login ', function () {
    $this
        ->postJson(route('padmission-tickets::api.store'), [
            'subject' => 'Some subject',
        ])
        ->assertUnauthorized();
});

it('requires create permission', function () {
    Gate::policy(Ticket::class, null);
    Gate::define('create', fn (User $user) => false);

    $user = User::factory()->create();

    $this->actingAs($user);

    $this
        ->postJson(route('padmission-tickets::api.store'), [
            'subject' => 'Some subject',
        ])
        ->assertForbidden();
});

it('creates a new ticket', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);

    $this->actingAs($user);

    $status = TicketStatus::factory()->create();
    $priority = TicketPriority::factory()->create();

    $resp = $this
        ->postJson(route('padmission-tickets::api.store'), [
            'subject' => 'Some subject',
        ])
        ->assertStatus(200);

    $ticketId = $resp->json('id');

    $ticket = Ticket::findOrFail($ticketId);

    expect($ticket)
        ->subject->toBe('Some subject')
        ->turn->toBe(Turn::User)
        ->submitter_id->toBe($user->id)
        ->status_id->toBe($status->id)
        ->priority_id->toBe($priority->id);
});

it('requires a subject', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);

    $this->actingAs($user);

    $this
        ->postJson(route('padmission-tickets::api.store'), [
            'subject' => '',
        ])
        ->assertStatus(422);
});
