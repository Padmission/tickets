<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketAttachment;
use Padmission\Tickets\Tests\Fixtures\TestTicketPolicy;
use Padmission\Tickets\Tests\User;

beforeEach(function () {
    (new TicketStatusSeeder)->run();
});

it('requires login ', function () {
    $ticket = Ticket::factory()->create();

    $this
        ->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $ticket]))
        ->assertUnauthorized();
});

/*
 * `create` is the chat widget's audience: it decides who may talk to support
 * through the widget, so it holds the requester's replies, never support's.
 */
it('requires create permission from the requester', function () {
    Gate::before(fn (User $authUser, string $ability) => $ability === 'create' ? false : null);

    $user = User::factory()->create();
    $ticket = Ticket::factory()->open()->create(['submitter_id' => $user->id]);

    $this->actingAs($user);

    $this
        ->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $ticket]), [
            'content' => 'Hello',
        ])
        ->assertForbidden();
});

it('lets a supporter left out of the chat widget still reply', function () {
    Gate::before(fn (User $authUser, string $ability) => $ability === 'create' ? false : null);

    $supporter = User::factory()->create();
    $ticket = Ticket::factory()->open()->create();

    $this->actingAs($supporter);

    $this
        ->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $ticket]), [
            'content' => 'Happy to help',
        ])
        ->assertOk();

    expect($ticket->ticketActivities()->where('type', ActivityType::Message)->where('user_id', $supporter->id)->exists())->toBeTrue();
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
    $ticket = Ticket::factory()->open()->create(['submitter_id' => $user->id]);
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
    $ticket = Ticket::factory()->open()->create(['submitter_id' => $user->id]);
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
    $ticket = Ticket::factory()->open()->create(['submitter_id' => $user->id]);
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

it('refuses a reply to a closed ticket', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->closed()->create(['submitter_id' => $user->id]);

    $this->actingAs($user);

    $this
        ->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $ticket]), [
            'content' => 'Is anyone there?',
        ])
        ->assertUnprocessable()
        ->assertExactJson(['message' => 'This ticket is already closed.']);

    expect($ticket->ticketActivities()->where('type', ActivityType::Message)->exists())->toBeFalse();
});

it('forbids a team that may read and manage a ticket but not reply from posting on it', function () {
    Gate::policy(Ticket::class, ReadsButRepliesOnlyInOwnPanelPolicy::class);

    $responder = User::factory()->create();
    $original = Ticket::factory()->open()->create(['panel' => 'test2']);

    $this->actingAs($responder);

    $this
        ->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $original]), [
            'content' => 'Straight to the requester',
        ])
        ->assertForbidden();

    $this
        ->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $original]))
        ->assertOk();

    expect($original->ticketActivities()->where('type', ActivityType::Message)->exists())->toBeFalse();
});

it('lets a supporter reply when the policy has no reply ability', function () {
    $supporter = User::factory()->create();
    $ticket = Ticket::factory()->open()->create();

    $this->actingAs($supporter);

    $this
        ->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $ticket]), [
            'content' => 'Happy to help',
        ])
        ->assertOk();
});

it('names the message just sent as the sender\'s own', function () {
    $supporter = User::factory()->create(['name' => 'Test Admin']);
    $ticket = Ticket::factory()->open()->create();

    $this->actingAs($supporter);

    $this
        ->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $ticket]), [
            'content' => 'Happy to help',
        ])
        ->assertOk()
        ->assertJsonPath('messages.0.user_name', __('padmission-tickets::tickets.side_you'));
});

it('keeps the turn only when the answering side asks to', function (bool $fromSubmitter, Turn $expected) {
    $submitter = User::factory()->create();
    $supporter = User::factory()->create();
    $ticket = Ticket::factory()->open()->create([
        'submitter_id' => $submitter->id,
        'turn' => $fromSubmitter ? Turn::User : Turn::Supporter,
    ]);

    $this->actingAs($fromSubmitter ? $submitter : $supporter);

    $this
        ->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $ticket]), [
            'content' => 'Still on it',
            'lock_turn' => true,
        ])
        ->assertOk();

    expect($ticket->refresh()->turn)->toBe($expected);
})->with([
    'the submitter, which is ignored' => [true, Turn::Supporter],
    'a supporter' => [false, Turn::Supporter],
]);

class ReadsButRepliesOnlyInOwnPanelPolicy extends TestTicketPolicy
{
    public function reply(User $user, Ticket $ticket): bool
    {
        return $ticket->panel === 'test';
    }
}

it('rejects visually empty rich text without creating a reply', function (string $content) {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->open()->create(['submitter_id' => $user->id]);
    $this->actingAs($user);
    $before = $ticket->ticketActivities()->count();

    $this->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $ticket]), [
        'content' => $content,
    ])->assertUnprocessable()->assertJsonValidationErrors('content');

    expect($ticket->ticketActivities()->count())->toBe($before);
})->with(['<p></p>', '<p><br></p>', '<p>   </p>', '<p>&nbsp;</p>']);

it('does not reopen a closed ticket when the reply is visually empty', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->closed()->create(['submitter_id' => $user->id]);
    $this->actingAs($user);

    $this->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $ticket]), [
        'content' => '<p></p>',
        'reopen' => true,
    ])->assertUnprocessable()->assertJsonValidationErrors('content');

    expect($ticket->refresh()->isClosed)->toBeTrue();
});
