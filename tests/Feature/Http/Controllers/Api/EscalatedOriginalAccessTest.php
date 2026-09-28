<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\Fixtures\TestTicketPolicy;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    (new TicketStatusSeeder)->run();

    Gate::policy(Ticket::class, RepliesOnlyOnEscalationsPolicy::class);

    // Stands in for a host tenant scope that hides another tenant's tickets from the source panel.
    TicketPlugin::get('test')->customizeTicketQuery(fn (Builder $query): Builder => $query->where('subject', '!=', 'Another tenant'));

    $this->escalation = escalationFrom('test', ['subject' => 'Escalation']);
    $this->original = Ticket::factory()->open()->create(['panel' => 'test', 'subject' => 'Another tenant', 'linked_ticket_id' => $this->escalation->id]);
    $this->message = $this->original->ticketActivities()->create([
        'type' => ActivityType::Message,
        'sender' => ActivitySender::User,
        'user_id' => $this->original->submitter_id,
        'content' => 'My rent looks wrong',
    ]);

    $this->actingAs(User::factory()->create());
});

it('reads an original its own panel hides when its escalation is reachable', function () {
    $this
        ->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $this->original]))
        ->assertOk()
        ->assertJsonFragment(['content' => 'My rent looks wrong']);

    $this
        ->postJson(route('padmission-tickets::api.mark-seen', ['ticket' => $this->original]), [
            'last_seen_activity_id' => $this->message->id,
        ])
        ->assertOk();
});

it('refuses a post on that original with a 403 when the policy allows no reply, while the escalation takes one', function () {
    $this
        ->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $this->original]), [
            'content' => 'Straight to the requester',
        ])
        ->assertForbidden();

    $this
        ->postJson(route('padmission-tickets::api.attachment-url', ['ticket' => $this->original]), [
            'filename' => 'a.pdf',
            'content_type' => 'application/pdf',
            'content_length' => 10,
        ])
        ->assertForbidden();

    $this
        ->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $this->escalation]), [
            'content' => 'On the escalation',
        ])
        ->assertOk();

    expect($this->original->ticketActivities()->where('content', 'like', '%Straight to the requester%')->exists())->toBeFalse();
});

it('still answers 404 for an original whose escalation is hidden too', function () {
    TicketPlugin::get('test2')->customizeTicketQuery(fn (Builder $query): Builder => $query->where('subject', '!=', 'Escalation'));

    $this
        ->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $this->original]))
        ->assertNotFound();

    $this
        ->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $this->original]), [
            'content' => 'Straight to the requester',
        ])
        ->assertNotFound();

    $this
        ->postJson(route('padmission-tickets::api.temporary-attachment-url', ['ticket' => $this->original]), [
            'filepath' => 'tickets/x.pdf',
        ])
        ->assertNotFound();
});

it('answers 404, not 403, on every endpoint to a user who may not read that original', function () {
    $this->actingAs(User::factory()->create(['name' => 'Outsider']));

    $this
        ->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $this->original]))
        ->assertNotFound();

    $this
        ->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $this->original]), [
            'content' => 'Straight to the requester',
        ])
        ->assertNotFound();

    $this
        ->postJson(route('padmission-tickets::api.mark-seen', ['ticket' => $this->original]), [
            'last_seen_activity_id' => $this->message->id,
        ])
        ->assertNotFound();

    $this
        ->postJson(route('padmission-tickets::api.attachment-url', ['ticket' => $this->original]), [
            'filename' => 'a.pdf',
            'content_type' => 'application/pdf',
            'content_length' => 10,
        ])
        ->assertNotFound();

    $this
        ->postJson(route('padmission-tickets::api.temporary-attachment-url', ['ticket' => $this->original]), [
            'filepath' => 'tickets/x.pdf',
        ])
        ->assertNotFound();

    expect($this->original->ticketActivities()->where('content', 'like', '%Straight to the requester%')->exists())->toBeFalse();
});

it('still answers 404 for a hidden ticket that was never escalated', function () {
    $unescalated = Ticket::factory()->open()->create(['panel' => 'test', 'subject' => 'Another tenant']);

    $this
        ->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $unescalated]))
        ->assertNotFound();
});

class RepliesOnlyOnEscalationsPolicy extends TestTicketPolicy
{
    public function view(User $user, Ticket $ticket): bool
    {
        return $user->name !== 'Outsider';
    }

    public function manage(User $user, Ticket $ticket): bool
    {
        return $user->name !== 'Outsider';
    }

    public function reply(User $user, Ticket $ticket): bool
    {
        return $ticket->panel === 'test2';
    }
}
