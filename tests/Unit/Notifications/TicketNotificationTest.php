<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivitySide;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Events\TicketClosedEvent;
use Padmission\Tickets\Events\TicketCreatedEvent;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketUserState;
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
    expect($ticket->ticketUserStates()->where('user_id', $user->id)->count())
        ->toBe(1);

    $originalNotification = $ticket->ticketUserStates()->where('user_id', $user->id)->first();
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
    expect($ticket->ticketUserStates()->where('user_id', $user->id)->count())
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
    expect($ticket->ticketUserStates()->where('user_id', $user1->id)->count())->toBe(1);
    expect($ticket->ticketUserStates()->where('user_id', $user2->id)->count())->toBe(1);
});

test('notification sending records the last notified activity without changing last seen state', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();

    $seenActivity = TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'created_at' => now()->subMinutes(10),
    ]);

    $notifiedActivity = TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'created_at' => now()->subMinute(),
    ]);

    TicketUserState::factory()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $user->id,
        'last_seen_activity_id' => $seenActivity->id,
    ]);

    $event = new TicketActivityEvent($ticket, ActivityType::Message);
    (new TicketNotification($ticket, $event))->toMail($user);

    $state = $ticket->ticketUserStates()->where('user_id', $user->id)->first();

    expect($state)
        ->last_seen_activity_id->toBe($seenActivity->id)
        ->last_notified_activity_id->toBe($notifiedActivity->id);
});

test('generates correct email subject for different types', function () {
    $event = new TicketCreatedEvent($this->ticket);
    $notification = new TicketNotification($this->ticket, $event);

    $subject = invade($notification)->getEmailSubject();

    // Should contain the ticket ID and subject
    expect($subject)->toContain((string) $this->ticket->id);
    expect($subject)->toContain('Test Ticket');
});

test('queued notification renders correct message sides for each recipient without auth', function () {
    Queue::fake();
    $submitter = User::factory()->create();
    $supporter = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'submitter_id' => $submitter->id,
        'assignee_id' => $supporter->id,
    ]);

    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $supporter->id,
        'sender' => ActivitySender::Supporter,
        'type' => ActivityType::Message,
        'content' => 'Support reply',
    ]);

    expect(auth()->user())->toBeNull();

    $event = new TicketActivityEvent($ticket, ActivityType::Message);

    $submitterMail = (new TicketNotification($ticket, $event))->toMail($submitter);
    $submitterActivity = $submitterMail->viewData['activities']->first();

    expect($submitterActivity)
        ->side->toBe(ActivitySide::Other)
        ->userName->not->toBe(__('padmission-tickets::tickets.side_you'));

    $supporterMail = (new TicketNotification($ticket, $event))->toMail($supporter);
    $supporterActivity = $supporterMail->viewData['activities']->first();

    expect($supporterActivity)
        ->side->toBe(ActivitySide::Me)
        ->userName->toBe(__('padmission-tickets::tickets.side_you'));
});

test('queued notification includes management activities for the supporter recipient without auth', function () {
    Queue::fake();
    $submitter = User::factory()->create();
    $supporter = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'submitter_id' => $submitter->id,
        'assignee_id' => $supporter->id,
    ]);

    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'sender' => ActivitySender::System,
        'type' => ActivityType::StatusChanged,
        'data' => ['from' => null, 'to' => null],
    ]);

    expect(auth()->user())->toBeNull();

    $event = new TicketActivityEvent($ticket, ActivityType::StatusChanged);

    expect((new TicketNotification($ticket, $event))->shouldSend($supporter))->toBeTrue();

    $supporterMail = (new TicketNotification($ticket, $event))->toMail($supporter);

    expect($supporterMail->viewData['activities']->pluck('type'))
        ->toContain(ActivityType::StatusChanged);

    expect((new TicketNotification($ticket, $event))->shouldSend($submitter))->toBeFalse();
});

test('created notification sends even when the ticket has no unread activities', function () {
    Queue::fake();
    $submitter = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'submitter_id' => $submitter->id,
        'assignee_id' => null,
    ]);

    expect($ticket->ticketActivities()->count())->toBe(0);

    $event = new TicketCreatedEvent($ticket);

    expect((new TicketNotification($ticket, $event))->shouldSend($submitter))->toBeTrue();
});

test('closed notification sends with the closed subject even after the activity notification consumed all activities', function () {
    Queue::fake();
    $submitter = User::factory()->create();
    $supporter = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'submitter_id' => $submitter->id,
        'assignee_id' => $supporter->id,
    ]);

    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $supporter->id,
        'sender' => ActivitySender::Supporter,
        'type' => ActivityType::Message,
        'content' => 'Support reply',
    ]);

    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'sender' => ActivitySender::System,
        'type' => ActivityType::Closed,
        'data' => ['closed_by' => $supporter->id, 'disposition_id' => null],
    ]);

    $activityEvent = new TicketActivityEvent($ticket, ActivityType::Message);
    (new TicketNotification($ticket, $activityEvent))->toMail($submitter);

    $closedEvent = new TicketClosedEvent($ticket, $supporter);
    $closedNotification = new TicketNotification($ticket, $closedEvent);

    expect($closedNotification->shouldSend($submitter))->toBeTrue();

    $mail = $closedNotification->toMail($submitter);

    expect($mail->subject)->toBe(__('padmission-tickets::notifications.ticket-closed.subject', [
        'subject' => $ticket->subject,
        'ticket_id' => $ticket->id,
    ]));

    $rendered = $mail->render();

    expect((string) $rendered)->toContain(__('padmission-tickets::notifications.ticket-closed.headline'));
});

test('closed notification renders pending activities when it fires before the activity notification', function () {
    Queue::fake();
    $submitter = User::factory()->create();
    $supporter = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'submitter_id' => $submitter->id,
        'assignee_id' => $supporter->id,
    ]);

    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $supporter->id,
        'sender' => ActivitySender::Supporter,
        'type' => ActivityType::Message,
        'content' => 'Support reply',
    ]);

    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'sender' => ActivitySender::System,
        'type' => ActivityType::Closed,
        'data' => ['closed_by' => $supporter->id, 'disposition_id' => null],
    ]);

    $closedEvent = new TicketClosedEvent($ticket, $supporter);
    $closedNotification = new TicketNotification($ticket, $closedEvent);

    expect($closedNotification->shouldSend($submitter))->toBeTrue();

    $rendered = (string) $closedNotification->toMail($submitter)->render();

    expect($rendered)->toContain('Support reply')
        ->and($rendered)->toContain(__('padmission-tickets::notifications.ticket-closed.headline'));
});

test('activity notification is still gated on unread activities', function () {
    Queue::fake();
    $submitter = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'submitter_id' => $submitter->id,
        'assignee_id' => null,
    ]);

    $event = new TicketActivityEvent($ticket, ActivityType::Message);

    expect((new TicketNotification($ticket, $event))->shouldSend($submitter))->toBeFalse();
});

test('notifications go out through the configured channels', function () {
    $event = new TicketActivityEvent($this->ticket, ActivityType::Message);
    $notification = new TicketNotification($this->ticket, $event);

    expect($notification->via($this->user))->toBe(['mail']);

    config()->set('padmission-tickets.notification-channels', ['mail', 'database']);

    expect($notification->via($this->user))->toBe(['mail', 'database']);
});

test('a database notification carries the ticket and its latest unread message', function () {
    Queue::fake();
    $submitter = User::factory()->create();
    $supporter = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'submitter_id' => $submitter->id,
        'assignee_id' => $supporter->id,
    ]);

    $activity = TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $supporter->id,
        'sender' => ActivitySender::Supporter,
        'type' => ActivityType::Message,
        'content' => '<p>We fixed the <strong>rent</strong> calculation.</p>',
    ]);

    $event = new TicketActivityEvent($ticket, ActivityType::Message);
    $data = (new TicketNotification($ticket, $event))->toDatabase($submitter);

    expect($data)
        ->format->toBe('filament')
        ->title->toBe(__('padmission-tickets::notifications.ticket-activity.subject', [
            'subject' => $ticket->subject,
            'ticket_id' => $ticket->id,
        ]))
        ->body->toBe('We fixed the rent calculation.')
        ->and($data['actions'][0]['url'])->toEndWith("#ticket-{$ticket->id}")
        ->and($ticket->ticketUserStates()->where('user_id', $submitter->id)->value('last_notified_activity_id'))
        ->toBe($activity->id);
});

test('mail and database channels of one send both deliver the same unread batch', function () {
    Queue::fake();
    config()->set('mail.default', 'array');
    config()->set('padmission-tickets.notification-channels', ['mail', 'database']);

    Schema::create('notifications', function (Blueprint $table) {
        $table->uuid('id')->primary();
        $table->string('type');
        $table->morphs('notifiable');
        $table->text('data');
        $table->timestamp('read_at')->nullable();
        $table->timestamps();
    });

    $submitter = User::factory()->create();
    $supporter = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'submitter_id' => $submitter->id,
        'assignee_id' => $supporter->id,
    ]);

    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'user_id' => $supporter->id,
        'sender' => ActivitySender::Supporter,
        'type' => ActivityType::Message,
        'content' => 'Support reply',
    ]);

    NotificationFacade::sendNow($submitter, new TicketNotification($ticket, new TicketActivityEvent($ticket, ActivityType::Message)));

    $mailsToSubmitter = collect(app('mailer')->getSymfonyTransport()->messages())
        ->filter(fn ($message) => $message->getEnvelope()->getRecipients()[0]->getAddress() === $submitter->email);

    expect($mailsToSubmitter)->toHaveCount(1)
        ->and($submitter->notifications()->sole()->data['body'])->toBe('Support reply');
});
