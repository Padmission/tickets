<?php

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
    $oldActivity = TicketActivity::factory()->create([
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

test('can get last notification for user and ticket', function () {
    // Create a notification
    $notification = TicketNotification::factory()->create([
        'ticket_id' => $this->ticket->id,
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
