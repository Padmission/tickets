<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketAttachment;
use Padmission\Tickets\Tests\User;

it('requires login ', function () {
    $ticket = Ticket::factory()->create();

    $this
        ->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $ticket]))
        ->assertUnauthorized();
});

it('requires create permission', function () {
    Gate::before(fn (User $authUser, string $ability) => $ability === 'create' ? false : null);

    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();

    $this->actingAs($user);

    $this
        ->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $ticket]))
        ->assertForbidden();
});

it('forbids posting a message to a ticket the user cannot access', function () {
    Gate::before(fn (User $authUser, string $ability) => $ability === 'manage' ? false : null);

    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();

    $this->actingAs($user);

    $this
        ->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $ticket]), [
            'content' => 'Hello',
        ])
        ->assertForbidden();
});

it('rejects attachment ids belonging to another ticket without deleting them', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->create(['submitter_id' => $user->id]);
    TicketActivity::factory()->create(['ticket_id' => $ticket->id]);

    $otherTicket = Ticket::factory()->create();
    $foreignAttachment = TicketAttachment::factory()->create([
        'ticket_id' => $otherTicket->id,
        'activity_id' => null,
        'created_by' => null,
    ]);

    $this->actingAs($user);

    $this
        ->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $ticket]), [
            'attachment_ids' => [$foreignAttachment->id],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('attachment_ids');

    $foreignAttachment->refresh();

    expect($foreignAttachment->activity_id)->toBeNull();
});

it('rejects attachment ids created by another user without re-parenting them', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $ticket = Ticket::factory()->create(['submitter_id' => $user->id]);
    TicketActivity::factory()->create(['ticket_id' => $ticket->id]);

    $othersAttachment = TicketAttachment::factory()->create([
        'ticket_id' => $ticket->id,
        'activity_id' => null,
        'created_by' => $otherUser->id,
    ]);

    $this->actingAs($user);

    $this
        ->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $ticket]), [
            'attachment_ids' => [$othersAttachment->id],
        ])
        ->assertUnprocessable();

    $othersAttachment->refresh();

    expect($othersAttachment->activity_id)->toBeNull();
});

it('attaches the user\'s own pending attachments for the ticket', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('tickets/attachment.jpg', 'content');

    $user = User::factory()->create();
    $ticket = Ticket::factory()->create(['submitter_id' => $user->id]);
    TicketActivity::factory()->create(['ticket_id' => $ticket->id]);

    $attachment = TicketAttachment::factory()->create([
        'ticket_id' => $ticket->id,
        'activity_id' => null,
        'created_by' => $user->id,
        'filepath' => 'tickets/attachment.jpg',
        'file_size' => strlen('content'),
    ]);

    $this->actingAs($user);

    $this
        ->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $ticket]), [
            'attachment_ids' => [$attachment->id],
        ])
        ->assertOk();

    $attachment->refresh();

    expect($attachment->activity_id)->not->toBeNull();
});

it('forbids posting a message to a ticket the user cannot view even when manage passes', function () {
    Gate::before(fn (User $authUser, string $ability) => $ability === 'view' ? false : null);

    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();

    $this->actingAs($user);

    $this
        ->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $ticket]), [
            'content' => 'Hello',
        ])
        ->assertForbidden();
});
