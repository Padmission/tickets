<?php

use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Events\TicketCreatedEvent;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Notifications\TicketNotification;
use Padmission\Tickets\Tests\User;

beforeEach(function () {
    $this->ticket = Ticket::factory()->create(['subject' => 'Test Ticket']);
    $this->user = User::factory()->create();
});

afterEach(function () {
    Mockery::close();
});

test('notification can be instantiated', function () {
    $ticket = Ticket::factory()->create();
    $event = new TicketCreatedEvent($ticket);
    $notification = new TicketNotification($ticket, $event);

    expect($notification)->toBeInstanceOf(TicketNotification::class);
});

test('notification returns correct email subject', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'subject' => 'Test Subject',
        'id' => 123,
    ]);
    $event = new TicketCreatedEvent($ticket);
    $notification = new TicketNotification($ticket, $event);

    // Create an activity so the notification has content
    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'content' => 'Test activity',
    ]);

    $mailMessage = $notification->toMail($user);

    expect($mailMessage->subject)->toContain('Test Subject')
        ->and($mailMessage->subject)->toContain('123');
});

test('respects debounce time window', function () {
    // Freeze time at a specific moment
    $this->freezeTime();

    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();

    // Create initial activity
    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'content' => 'Initial activity',
        'created_at' => now(),
    ]);

    $event = new TicketActivityEvent($ticket, ActivityType::Message);
    $notification = new TicketNotification($ticket, $event);

    // Send first notification
    $firstMail = $notification->toMail($user);

    // Verify notification record was created
    expect($ticket->ticketLastSeen()->where('user_id', $user->id)->count())
        ->toBe(1);

    $originalNotification = $ticket->ticketLastSeen()->where('user_id', $user->id)->first();
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
    $secondEvent = new TicketActivityEvent($ticket, ActivityType::Message);
    $secondNotification = new TicketNotification($ticket, $secondEvent);

    // Send another notification (simulating debounce behavior)
    $secondMail = $secondNotification->toMail($user);

    // Should STILL only have 1 notification record (updated, not created new)
    expect($ticket->ticketLastSeen()->where('user_id', $user->id)->count())
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

    // Create an activity so notification has something to track
    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'content' => 'Test activity',
        'created_at' => now(),
    ]);

    $event = new TicketActivityEvent($ticket, ActivityType::Message);
    $notification = new TicketNotification($ticket, $event);

    // Send notification to user1
    $notification->toMail($user1);

    // Send notification to user2
    $notification->toMail($user2);

    // Should have separate notification records
    expect($ticket->ticketLastSeen()->where('user_id', $user1->id)->count())->toBe(1);
    expect($ticket->ticketLastSeen()->where('user_id', $user2->id)->count())->toBe(1);
});

test('generates correct email subject for different types', function () {
    $event = new TicketCreatedEvent($this->ticket);
    $notification = new TicketNotification($this->ticket, $event);

    $subject = invade($notification)->getEmailSubject();

    // Should contain the ticket ID and subject
    expect($subject)->toContain((string) $this->ticket->id);
    expect($subject)->toContain('Test Ticket');
});
