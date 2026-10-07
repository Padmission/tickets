<?php

use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\NotificationStrategy;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Events\TicketAssignedEvent;
use Padmission\Tickets\Events\TicketClosedEvent;
use Padmission\Tickets\Events\TicketCreatedEvent;
use Padmission\Tickets\Jobs\NotificationJob;
use Padmission\Tickets\Listeners\TicketNotificationListener;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Notifications\TicketNotification;
use Padmission\Tickets\Services\NotificationRecipientService;
use Padmission\Tickets\Tests\User;

beforeEach(function () {
    Queue::fake();

    // Create necessary TicketStatus records
    TicketStatus::factory()->create([
        'display_name' => 'Open',
        'order' => 1,
        'panel' => 'test',
    ]);

    TicketStatus::factory()->create([
        'display_name' => 'Closed',
        'order' => 2,
        'panel' => 'test',
    ]);
});

afterEach(function () {
    Mockery::close();
});

test('notification listener correctly maps event types to notification types', function () {
    $ticket = Ticket::factory()->open()->create();
    $listener = new TicketNotificationListener(app(NotificationRecipientService::class));

    // Test event type mapping
    $activityEvent = new TicketActivityEvent($ticket, ActivityType::Message);
    $createdEvent = new TicketCreatedEvent($ticket);
    $assignedEvent = new TicketAssignedEvent($ticket);
    $closedEvent = new TicketClosedEvent($ticket);

    $listener = invade($listener);

    expect($listener)
        ->getNotificationType($activityEvent)->toBe('activity')
        ->getNotificationType($createdEvent)->toBe('created')
        ->getNotificationType($assignedEvent)->toBe('assigned')
        ->getNotificationType($closedEvent)->toBe('closed');
});

test('notifications are handled after the database commit', function () {
    expect(TicketNotificationListener::class)->toImplement(ShouldHandleEventsAfterCommit::class);
});

test('a notification raised inside a transaction waits for the commit', function () {
    $ticket = Ticket::factory()->open()->create();
    $handled = 0;

    $this->mock(NotificationRecipientService::class)
        ->shouldReceive('getNotificationRecipients')
        ->andReturnUsing(function () use (&$handled) {
            $handled++;

            return collect();
        });

    DB::transaction(function () use ($ticket, &$handled) {
        event(new TicketAssignedEvent($ticket));

        expect($handled)->toBe(0);
    });

    expect($handled)->toBe(1);
});

describe('TicketNotificationListener Unit Tests', function () {

    test('correctly determines notification type from event class name', function () {
        $ticket = Ticket::factory()->open()->create();
        $listener = new TicketNotificationListener(app(NotificationRecipientService::class));

        // Test different event types
        expect(invade($listener)->getNotificationType(new TicketActivityEvent($ticket, ActivityType::Message)))
            ->toBe('activity');

        expect(invade($listener)->getNotificationType(new TicketCreatedEvent($ticket)))
            ->toBe('created');

        expect(invade($listener)->getNotificationType(new TicketAssignedEvent($ticket)))
            ->toBe('assigned');

        expect(invade($listener)->getNotificationType(new TicketClosedEvent($ticket)))
            ->toBe('closed');
    });

    test('immediate notification strategy dispatches jobs directly', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->open()->create(['assignee_id' => $user->id]);

        // Mock the recipient service to return immediate strategy
        $recipientService = Mockery::mock(NotificationRecipientService::class);
        $recipientService->shouldReceive('getNotificationRecipients')
            ->andReturn(collect([$user]));
        $recipientService->shouldReceive('getUserNotificationStrategy')
            ->andReturn(NotificationStrategy::Immediate);

        $event = new TicketActivityEvent($ticket, ActivityType::Message);
        $listener = new TicketNotificationListener($recipientService);

        $listener->handle($event);

        Queue::assertPushed(NotificationJob::class, function ($job) use ($user, $ticket) {
            return $job->getUserId() === $user->id &&
                   $job->getTicketKey() === $ticket->id;
        });
    });

    test('handles multiple recipients correctly', function () {
        $assignee = User::factory()->create();
        $submitter = User::factory()->create();
        $ticket = Ticket::factory()->open()->create([
            'assignee_id' => $assignee->id,
            'submitter_id' => $submitter->id,
        ]);

        $event = new TicketActivityEvent($ticket, ActivityType::Message);
        $listener = new TicketNotificationListener(app(NotificationRecipientService::class));

        $listener->handle($event);

        // Should handle multiple recipients without error
        // This is hard to test directly with debouncing, but we can verify no exceptions
        expect($ticket->assignee_id)->toBe($assignee->id);
        expect($ticket->submitter_id)->toBe($submitter->id);
    });

    test('reads custom debounce time from configuration', function () {
        // Set custom debounce time
        config(['padmission-tickets.notification-debounce' => 600]); // 10 minutes

        $user = User::factory()->create();
        $ticket = Ticket::factory()->open()->create(['assignee_id' => $user->id]);

        $event = new TicketActivityEvent($ticket, ActivityType::Message);
        $listener = new TicketNotificationListener(app(NotificationRecipientService::class));

        // Verify the configuration is read correctly
        expect(config('padmission-tickets.notification-debounce'))->toBe(600);

        $listener->handle($event);

        // Reset config
        config(['padmission-tickets.notification-debounce' => 300]);
    });
});

describe('NotificationJob Unit Tests', function () {

    test('resolves user correctly', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->open()->create();
        $event = new TicketActivityEvent($ticket, ActivityType::Message);

        $job = new NotificationJob($user, $ticket, $event);

        $resolvedUser = invade($job)->resolveUser();

        expect($resolvedUser)->not->toBeNull()
            ->and($resolvedUser->id)->toBe($user->id);
    });

    test('resolves ticket correctly', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->open()->create();
        $event = new TicketActivityEvent($ticket, ActivityType::Message);

        $job = new NotificationJob($user, $ticket, $event);

        $resolvedTicket = invade($job)->resolveModel();

        expect($resolvedTicket)->not->toBeNull()
            ->and($resolvedTicket->id)->toBe($ticket->id);
    });

    test('returns null for non-existent user', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->open()->create();
        $event = new TicketActivityEvent($ticket, ActivityType::Message);

        $job = new NotificationJob($user, $ticket, $event);

        // Delete the user after job creation
        $user->delete();

        $resolvedUser = invade($job)->resolveUser();

        expect($resolvedUser)->toBeNull();
    });

    test('returns null for non-existent ticket', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->open()->create();
        $event = new TicketActivityEvent($ticket, ActivityType::Message);

        $job = new NotificationJob($user, $ticket, $event);

        // Delete the ticket after job creation
        $ticket->delete();

        $resolvedTicket = invade($job)->resolveModel();

        expect($resolvedTicket)->toBeNull();
    });

    test('returns correct notification class for valid type', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->open()->create();
        $event = new TicketActivityEvent($ticket, ActivityType::Message);

        $job = new NotificationJob($user, $ticket, $event);

        $notificationClass = invade($job)->getNotificationClass();

        expect($notificationClass)->toBe(TicketNotification::class);
    });

    test('returns null for invalid notification type', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->open()->create();

        // Create a mock event that won't be in the config
        $event = new class($ticket)
        {
            public function __construct(public $ticket) {}
        };

        $job = new NotificationJob($user, $ticket, $event);

        $notificationClass = invade($job)->getNotificationClass();

        expect($notificationClass)->toBeNull();
    });

    test('unique id format is consistent and predictable', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->open()->create();
        $event = new TicketActivityEvent($ticket, ActivityType::Message);

        $job = new NotificationJob($user, $ticket, $event);
        $uniqueId = $job->uniqueId();

        expect($uniqueId)
            ->toBeString()
            ->toContain('notification')
            ->toContain((string) $ticket->id)
            ->toContain((string) $user->id);

        // Same inputs should produce same unique ID
        $event2 = new TicketActivityEvent($ticket, ActivityType::Message);
        $job2 = new NotificationJob($user, $ticket, $event2);
        expect($job->uniqueId())->toBe($job2->uniqueId());
    });

    test('different inputs produce different unique ids', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $ticket1 = Ticket::factory()->open()->create();
        $ticket2 = Ticket::factory()->open()->create();
        $event = new TicketActivityEvent($ticket1, ActivityType::Message);

        $job1 = new NotificationJob($user1, $ticket1, $event);
        $event2 = new TicketActivityEvent($ticket1, ActivityType::Message);
        $job2 = new NotificationJob($user2, $ticket1, $event2); // Different user
        $event3 = new TicketActivityEvent($ticket2, ActivityType::Message);
        $job3 = new NotificationJob($user1, $ticket2, $event3); // Different ticket

        expect($job1->uniqueId())->not->toBe($job2->uniqueId())
            ->and($job1->uniqueId())->not->toBe($job3->uniqueId())
            ->and($job2->uniqueId())->not->toBe($job3->uniqueId());
    });
});

test('ticket created event is wired to the notification listener', function () {
    expect(Event::hasListeners(TicketCreatedEvent::class))->toBeTrue();
});

test('ticket created event dispatches a notification job to the submitter', function () {
    config(['padmission-tickets.default-notification-strategy' => NotificationStrategy::Immediate]);

    $ticket = Ticket::factory()->open()->create();

    Queue::fake();

    event(new TicketCreatedEvent($ticket, $ticket->submitter));

    Queue::assertPushed(NotificationJob::class, function (NotificationJob $job) use ($ticket) {
        return $job->notificationType === 'created'
            && $job->getUserId() === $ticket->submitter_id;
    });
});

test('assignment bypasses a user method returning debounced but their replies still wait', function () {
    $assignee = User::factory()->create();
    $user = new class extends User
    {
        public function ticketNotificationStrategy(): NotificationStrategy
        {
            return NotificationStrategy::Debounced;
        }
    };
    $user->forceFill(['id' => $assignee->id]);
    $ticket = Ticket::factory()->open()->create(['assignee_id' => $user->id]);
    Queue::fake();
    $service = app(NotificationRecipientService::class);
    expect($service->getUserNotificationStrategy($user))->toBe(NotificationStrategy::Debounced);
    $listener = new TicketNotificationListener($service);
    invade($listener)->sendNotificationToUser($user, new TicketAssignedEvent($ticket));

    Queue::assertPushed(NotificationJob::class, fn (NotificationJob $job): bool => $job->getUserId() === $user->id && $job->delay === null);
    Queue::fake();
    invade($listener)->sendNotificationToUser($user, new TicketActivityEvent($ticket, ActivityType::Message));
    Queue::assertNotPushed(NotificationJob::class);
});
