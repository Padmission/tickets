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
    $user = User::factory()->create();
    $ticket = Ticket::factory()
        ->has(TicketAttachment::factory([
            'filepath' => 'test.jpg',
        ]), 'attachments')
        ->create();

    Gate::before(fn (User $authUser, string $ability) => $ability === 'manage' ? false : null);

    $this->actingAs($user);

    $this
        ->postJson(route('padmission-tickets::api.temporary-attachment-url', [
            'ticket' => $ticket,
        ]), ['filepath' => 'test.jpg'])
        ->assertForbidden();
});

it('returns a temporary url', function () {
    Storage::fake('s3');

    $filepath = 'test.jpg';

    $user = User::factory()->create();
    $ticket = Ticket::factory()
        ->has(TicketAttachment::factory([
            'filepath' => $filepath,
        ]), 'attachments')
        ->create([
            'submitter_id' => $user->id,
        ]);

    $this->actingAs($user);

    $resp = $this
        ->postJson(
            route('padmission-tickets::api.temporary-attachment-url', ['ticket' => $ticket]),
            [
                'filepath' => $filepath,
            ]
        )
        ->assertOk();

    expect($resp->getData())
        ->toHaveKeys(['url'])
        ->url->toContain($filepath)
        ->url->toContain('expiration');
});
