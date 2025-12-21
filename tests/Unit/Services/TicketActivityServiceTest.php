<?php

use Illuminate\Support\Facades\Config;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketLastSeen;
use Padmission\Tickets\Services\TicketActivityService;
use Padmission\Tickets\Tests\User;

beforeEach(function () {
    $this->service = new TicketActivityService;
    $this->ticket = Ticket::factory()->create();
    $this->user = User::factory()->create();
});

test('can get unread activities within date range', function () {
    // Create an old activity (should be excluded)
    TicketActivity::factory()->create([
        'ticket_id' => $this->ticket->id,
        'created_at' => now()->subDays(15),
    ]);

    // Create a new activity (should be included)
    $newActivity = TicketActivity::factory()->create([
        'ticket_id' => $this->ticket->id,
        'created_at' => now()->subDays(2),
    ]);

    $activities = $this->service->getUnreadActivities($this->ticket, $this->user, 10, 7);

    expect($activities)->toHaveCount(1);
    expect($activities->first()->id)->toBe($newActivity->id);
});

test('can get unread activities after last notified activity', function () {
    $oldActivity = TicketActivity::factory()->create([
        'ticket_id' => $this->ticket->id,
        'created_at' => now()->subDays(4),
    ]);

    TicketLastSeen::factory()->create([
        'user_id' => $this->user->id,
        'ticket_id' => $this->ticket->id,
        'last_notified_activity_id' => $oldActivity->id,
    ]);

    // Create a new activity (should be included - after the last notified activity)
    $newActivity = TicketActivity::factory()->create([
        'ticket_id' => $this->ticket->id,
        'created_at' => now()->subDays(2),
    ]);

    $activities = $this->service->getUnreadActivities($this->ticket, $this->user, 10, 7);

    expect($activities)->toHaveCount(1);
    expect($activities->first()->id)->toBe($newActivity->id);
});

test('can get last seen for user and ticket', function () {
    $userB = User::factory()->create();
    $ticketB = Ticket::factory()->create();

    $lastSeen = TicketLastSeen::factory()->create([
        'ticket_id' => $this->ticket->id,
        'user_id' => $this->user->id,
    ]);

    TicketLastSeen::factory()->create([
        'ticket_id' => $this->ticket->id,
        'user_id' => $userB->id,
    ]);

    TicketLastSeen::factory()->create([
        'ticket_id' => $ticketB->id,
        'user_id' => $this->user->id,
    ]);

    $result = $this->service->getLastSeen($this->ticket, $this->user);

    expect($result)->not->toBeNull();
    expect($result->id)->toBe($lastSeen->id);
});

test('returns null when no last seen exists', function () {
    $lastSeen = $this->service->getLastSeen($this->ticket, $this->user);

    expect($lastSeen)->toBeNull();
});

test('respects max events configuration', function () {
    Config::set('padmission-tickets.notification-max-events', 2);

    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();

    // Create 4 activities
    for ($i = 0; $i < 4; $i++) {
        TicketActivity::factory()->create([
            'ticket_id' => $ticket->id,
        ]);
    }

    $notification = new \Padmission\Tickets\Notifications\TicketNotification($ticket, 'history');

    // Use the activity service to get unread activities
    $activityService = app(TicketActivityService::class);
    $activities = $activityService->getUnreadActivities($ticket, $user, 2, 7);

    expect($activities)->toHaveCount(2); // Should limit to 2
});

test('returns null when user has no previous last seen for ticket', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();
    $notification = new \Padmission\Tickets\Notifications\TicketNotification($ticket, 'history');

    // Use the activity service to get the last seen
    $activityService = app(TicketActivityService::class);
    $lastSeen = $activityService->getLastSeen($ticket, $user);

    expect($lastSeen)->toBeNull();
});

test('gets last seen for specific user and ticket', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();

    // Create a last seen record
    $lastSeenRecord = $ticket->ticketLastSeen()->create([
        'user_id' => $user->getKey(),
        'created_at' => now()->subHour(),
    ]);

    // Use the activity service to get the last seen
    $activityService = app(TicketActivityService::class);
    $lastSeen = $activityService->getLastSeen($ticket, $user);

    expect($lastSeen)
        ->not->toBeNull()
        ->id->toBe($lastSeenRecord->id)
        ->user_id->toBe($user->getKey());
});
