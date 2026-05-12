<?php

use Illuminate\Support\Facades\Config;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketNotification;
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

test('can get unread activities within date range with latestNotification', function () {
    TicketNotification::factory()->create([
        'user_id' => $this->user->id,
        'ticket_id' => $this->ticket->id,
        'updated_at' => now()->subDays(3),
    ]);

    // Create an old activity (should be included, but is after lastNotification)
    TicketActivity::factory()->create([
        'ticket_id' => $this->ticket->id,
        'created_at' => now()->subDays(4),
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

test('can get last notification for user and ticket', function () {
    $userB = User::factory()->create();
    $ticketB = Ticket::factory()->create();

    $notification = TicketNotification::factory()->create([
        'ticket_id' => $this->ticket->id,
        'user_id' => $this->user->id,
    ]);

    TicketNotification::factory()->create([
        'ticket_id' => $this->ticket->id,
        'user_id' => $userB->id,
    ]);

    TicketNotification::factory()->create([
        'ticket_id' => $ticketB->id,
        'user_id' => $this->user->id,
    ]);

    $lastNotification = $this->service->getLastNotification($this->ticket, $this->user);

    expect($lastNotification)->not->toBeNull();
    expect($lastNotification->id)->toBe($notification->id);
});

test('returns null when no notification exists', function () {
    $lastNotification = $this->service->getLastNotification($this->ticket, $this->user);

    expect($lastNotification)->toBeNull();
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

    $notification = new Padmission\Tickets\Notifications\TicketNotification($ticket, 'history');

    // Use the activity service to get unread activities
    $activityService = app(TicketActivityService::class);
    $activities = $activityService->getUnreadActivities($ticket, $user, 2, 7);

    expect($activities)->toHaveCount(2); // Should limit to 2
});

test('returns null when user has no previous notifications for ticket', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();
    $notification = new Padmission\Tickets\Notifications\TicketNotification($ticket, 'history');

    // Use the activity service to get the last notification
    $activityService = app(TicketActivityService::class);
    $lastNotification = $activityService->getLastNotification($ticket, $user);

    expect($lastNotification)->toBeNull();
});

test('gets last notification for specific user and ticket', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();

    // Create a notification record
    $notificationRecord = $ticket->ticketNotifications()->create([
        'user_id' => $user->getKey(),
        'created_at' => now()->subHour(),
    ]);

    // Use the activity service to get the last notification
    $activityService = app(TicketActivityService::class);
    $lastNotification = $activityService->getLastNotification($ticket, $user);

    expect($lastNotification)
        ->not->toBeNull()
        ->id->toBe($notificationRecord->id)
        ->user_id->toBe($user->getKey());
});
