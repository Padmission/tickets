<?php

use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Tests\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

test('can mark ticket as seen with valid activity', function () {
    $ticket = Ticket::factory()->create(['submitter_id' => $this->user->id]);
    $activity = TicketActivity::factory()->create(['ticket_id' => $ticket->id]);

    $response = $this->postJson("/padmission-tickets/api/tickets/{$ticket->id}/mark-seen", [
        'last_seen_activity_id' => $activity->id,
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);

    $lastSeen = $ticket->ticketLastSeen()->where('user_id', $this->user->id)->first();
    expect($lastSeen)->not->toBeNull();
    expect($lastSeen->last_seen_activity_id)->toBe($activity->id);
});

test('cannot mark ticket as seen with invalid activity id', function () {
    $ticket = Ticket::factory()->create(['submitter_id' => $this->user->id]);

    $response = $this->postJson("/padmission-tickets/api/tickets/{$ticket->id}/mark-seen", [
        'last_seen_activity_id' => 99999,
    ]);

    $response->assertUnprocessable();
});

test('cannot mark ticket as seen with activity from different ticket', function () {
    $ticket = Ticket::factory()->create(['submitter_id' => $this->user->id]);
    $otherTicket = Ticket::factory()->create();
    $otherActivity = TicketActivity::factory()->create(['ticket_id' => $otherTicket->id]);

    $response = $this->postJson("/padmission-tickets/api/tickets/{$ticket->id}/mark-seen", [
        'last_seen_activity_id' => $otherActivity->id,
    ]);

    $response->assertNotFound();
});

test('updates existing last_seen record', function () {
    $ticket = Ticket::factory()->create([
        'submitter_id' => $this->user->id,
        'assignee_id' => null, // Prevent auto-creation of assignee that would trigger notifications
    ]);
    $activity1 = TicketActivity::factory()->create(['ticket_id' => $ticket->id]);
    $activity2 = TicketActivity::factory()->create(['ticket_id' => $ticket->id]);

    // First mark
    $this->postJson("/padmission-tickets/api/tickets/{$ticket->id}/mark-seen", [
        'last_seen_activity_id' => $activity1->id,
    ]);

    // Second mark (should update, not create new)
    $this->postJson("/padmission-tickets/api/tickets/{$ticket->id}/mark-seen", [
        'last_seen_activity_id' => $activity2->id,
    ]);

    expect($ticket->ticketLastSeen()->count())->toBe(1);
    expect($ticket->ticketLastSeen()->first()->last_seen_activity_id)->toBe($activity2->id);
});

test('requires authentication', function () {
    auth()->logout();

    $ticket = Ticket::factory()->create();
    $activity = TicketActivity::factory()->create(['ticket_id' => $ticket->id]);

    $response = $this->postJson("/padmission-tickets/api/tickets/{$ticket->id}/mark-seen", [
        'last_seen_activity_id' => $activity->id,
    ]);

    $response->assertUnauthorized();
});

test('validates last_seen_activity_id is required', function () {
    $ticket = Ticket::factory()->create(['submitter_id' => $this->user->id]);

    $response = $this->postJson("/padmission-tickets/api/tickets/{$ticket->id}/mark-seen", []);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['last_seen_activity_id']);
});

test('validates last_seen_activity_id must be integer', function () {
    $ticket = Ticket::factory()->create(['submitter_id' => $this->user->id]);

    $response = $this->postJson("/padmission-tickets/api/tickets/{$ticket->id}/mark-seen", [
        'last_seen_activity_id' => 'not-an-integer',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['last_seen_activity_id']);
});
