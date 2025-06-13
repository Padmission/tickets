<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Notifications\AbstractTicketHistoryNotification;
use Padmission\Tickets\Tests\User;

// Create a concrete test implementation
class TestTicketHistoryNotification extends AbstractTicketHistoryNotification
{
    public function getEmailSubject(): string
    {
        return 'Test Ticket Notification';
    }

    public function getEmailView(): string
    {
        return 'padmission-tickets::emails.ticket-history';
    }
}

beforeEach(function () {
    // Disable events AND observers to prevent notification system from triggering
    Event::fake();

    // Disable model observers that trigger notifications
    //    Ticket::unsetEventDispatcher();
    //  TicketActivity::unsetEventDispatcher();
});

test('notification can be instantiated', function () {
    $ticket = Ticket::factory()->create();
    $notification = new TestTicketHistoryNotification($ticket);

    expect($notification)->toBeInstanceOf(AbstractTicketHistoryNotification::class);
    expect($notification->ticket->id)->toBe($ticket->id);
});

test('notification returns correct email subject', function () {
    $ticket = Ticket::factory()->create();
    $notification = new TestTicketHistoryNotification($ticket);

    expect($notification->getEmailSubject())->toBe('Test Ticket Notification');
});

test('notification returns correct email view', function () {
    $ticket = Ticket::factory()->create();
    $notification = new TestTicketHistoryNotification($ticket);

    expect($notification->getEmailView())->toBe('padmission-tickets::emails.ticket-history');
});

test('returns null when user has no previous notifications for ticket', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();
    $notification = new TestTicketHistoryNotification($ticket);

    $lastNotification = $notification->getLastNotification($user);

    expect($lastNotification)->toBeNull();
});

test('gets last notification for specific user and ticket', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();
    $notification = new TestTicketHistoryNotification($ticket);

    // Create a notification record
    $notificationRecord = $ticket->ticketNotifications()->create([
        'user_id' => $user->getKey(),
        'created_at' => now()->subHour(),
    ]);

    $lastNotification = $notification->getLastNotification($user);

    expect($lastNotification)
        ->not->toBeNull()
        ->id->toBe($notificationRecord->id)
        ->user_id->toBe($user->getKey());
});

test('gets unread actions when no previous notification exists', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();
    $notification = new TestTicketHistoryNotification($ticket);

    // Create some activities using the factory
    $activity1 = TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'type' => ActivityType::Message,
        'sender' => ActivitySender::User,
        'content' => 'First activity',
        'created_at' => now()->subHours(2),
    ]);

    $activity2 = TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'type' => ActivityType::Message,
        'sender' => ActivitySender::User,
        'content' => 'Second activity',
        'created_at' => now()->subHour(),
    ]);

    $activities = $notification->getUnreadActions($user, 10, 7);

    expect($activities)
        ->toHaveCount(2)
        ->first()->id->toBe($activity1->id) // Should be chronological order
        ->last()->id->toBe($activity2->id);
});

test('respects debounce time window', function () {
    // Freeze time at a specific moment
    $this->freezeTime();

    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();
    $notification = new TestTicketHistoryNotification($ticket);

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
    $secondNotification = new TestTicketHistoryNotification($ticket);

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

test('respects max events configuration', function () {
    Config::set('padmission-tickets.notification-max-events', 2);

    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();

    // Create 4 activities
    for ($i = 0; $i < 4; $i++) {
        TicketActivity::factory()->create([
            'ticket_id' => $ticket->id,
            'content' => "Activity {$i}",
            'created_at' => now()->subMinutes(60 - $i),
        ]);
    }

    $notification = new TestTicketHistoryNotification($ticket);
    $activities = $notification->getUnreadActions($user, 2, 7);

    expect($activities)->toHaveCount(2); // Should limit to 2
});

test('respects max days configuration', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();

    // Create old activity (outside limit)
    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'content' => 'Too old',
        'created_at' => now()->subDays(8),
    ]);

    // Create recent activity (within limit)
    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'content' => 'Recent',
        'created_at' => now()->subDays(3),
    ]);

    $notification = new TestTicketHistoryNotification($ticket);
    $activities = $notification->getUnreadActions($user, 10, 7);

    expect($activities)
        ->toHaveCount(1)
        ->first()->content->toBe('Recent');
});

test('notifications are isolated per user', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $ticket = Ticket::factory()->create();

    $notification = new TestTicketHistoryNotification($ticket);

    // Send notification to user1
    $notification->toMail($user1);

    // Send notification to user2
    $notification->toMail($user2);

    // Should have separate notification records
    expect($ticket->ticketNotifications()->where('user_id', $user1->id)->count())->toBe(1);
    expect($ticket->ticketNotifications()->where('user_id', $user2->id)->count())->toBe(1);
    expect($ticket->ticketNotifications()->count())->toBe(2);
});
