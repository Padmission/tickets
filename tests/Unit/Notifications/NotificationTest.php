<?php

use Illuminate\Support\Facades\Config;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Notifications\TicketNotification;
use Padmission\Tickets\Tests\User;

test('notification can be instantiated', function () {
    $ticket = Ticket::factory()->create();
    $notification = new TicketNotification($ticket, 'history');

    expect($notification)->toBeInstanceOf(TicketNotification::class);
});

test('notification returns correct email subject', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'subject' => 'Test Subject',
        'id' => 123,
    ]);
    $notification = new TicketNotification($ticket, 'created');

    $mailMessage = $notification->toMail($user);

    expect($mailMessage->subject)->toContain('Test Subject')
        ->and($mailMessage->subject)->toContain('123');
});

test('respects debounce time window', function () {
    // Freeze time at a specific moment
    $this->freezeTime();

    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();
    $notification = new TicketNotification($ticket, 'history');

    // Send first notification
    $firstMail = $notification->toMail($user);

    // Verify notification record was created
    expect($ticket->ticketNotifications()->where('user_id', $user->id)->count())
        ->toBe(1);

    $originalNotification = $ticket->ticketNotifications()->where('user_id', $user->id)->first();
    $originalTimestamp = $originalNotification->updated_at->timestamp;

    // Travel forward in time (within debounce window)
    $this->travel(30)->seconds(); // 30 seconds later

    // Create new activity after time travel
    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'content' => 'Activity within debounce window',
        'created_at' => now(), // This will be 30 seconds after the freeze time
    ]);

    // Create a NEW notification instance to avoid memoization issues
    $secondNotification = new TicketNotification($ticket, 'history');

    // Send another notification (simulating debounce behavior)
    $secondMail = $secondNotification->toMail($user);

    // Should STILL only have 1 notification record (updated, not created new)
    expect($ticket->ticketNotifications()->where('user_id', $user->id)->count())
        ->toBe(1);

    // Refresh the notification record and check it was updated
    $updatedNotification = $originalNotification->fresh();
    expect($updatedNotification->updated_at->timestamp)
        ->toBeGreaterThan($originalTimestamp);

    // The second email should include the new activity
    expect($secondMail->viewData['activities'])
        ->toHaveCount(1) // Only the new activity since last notification
        ->first()->content->toBe('Activity within debounce window');
});

test('notifications are isolated per user', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $ticket = Ticket::factory()->create();

    $notification = new TicketNotification($ticket, 'history');

    // Send notification to user1
    $notification->toMail($user1);

    // Send notification to user2
    $notification->toMail($user2);

    // Should have separate notification records
    expect($ticket->ticketNotifications()->where('user_id', $user1->id)->count())->toBe(1);
    expect($ticket->ticketNotifications()->where('user_id', $user2->id)->count())->toBe(1);
});
