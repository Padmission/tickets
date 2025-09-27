<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketAttachment;
use Padmission\Tickets\Tests\User;

it('requires login ', function () {
    $ticket = Ticket::factory()->create();

    $this
        ->postJson(route('padmission-tickets::api.temporary-attachment-url', [
            'ticket' => $ticket,
        ]))
        ->assertUnauthorized();
});

it('requires create permission', function () {
    Gate::policy(Ticket::class, null);
    Gate::define('create', fn (User $user) => false);

    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();

    $this->actingAs($user);

    $this
        ->postJson(route('padmission-tickets::api.temporary-attachment-url', [
            'ticket' => $ticket,
        ]))
        ->assertForbidden();
});

it('returns a temporary url', function () {
    Storage::fake('s3');

    $filename = 'test.jpg';

    $user = User::factory()->create();
    $ticket = Ticket::factory()
        ->has(TicketAttachment::factory([
            'filename' => $filename,
        ]), 'attachments')
        ->create([
            'submitter_id' => $user->id,
        ]);

    $this->actingAs($user);

    $resp = $this
        ->postJson(
            route('padmission-tickets::api.temporary-attachment-url', ['ticket' => $ticket]),
            [
                'filename' => $filename,
            ]
        )
        ->assertOk();

    expect($resp->getData())
        ->toHaveKeys(['url'])
        ->url->toContain($filename)
        ->url->toContain('expiration');
});
