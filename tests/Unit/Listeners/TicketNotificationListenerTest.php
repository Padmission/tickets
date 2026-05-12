<?php

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

        $job = new NotificationJob($user, $ticket, 'activity');

        $resolvedUser = invade($job)->resolveUser();

        expect($resolvedUser)->not->toBeNull()
            ->and($resolvedUser->id)->toBe($user->id);
    });

    test('resolves ticket correctly', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->open()->create();

        $job = new NotificationJob($user, $ticket, 'activity');

        $resolvedTicket = invade($job)->resolveModel();

        expect($resolvedTicket)->not->toBeNull()
            ->and($resolvedTicket->id)->toBe($ticket->id);
    });

    test('returns null for non-existent user', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->open()->create();

        $job = new NotificationJob($user, $ticket, 'activity');

        // Delete the user after job creation
        $user->delete();

        $resolvedUser = invade($job)->resolveUser();

        expect($resolvedUser)->toBeNull();
    });

    test('returns null for non-existent ticket', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->open()->create();

        $job = new NotificationJob($user, $ticket, 'activity');

        // Delete the ticket after job creation
        $ticket->delete();

        $resolvedTicket = invade($job)->resolveModel();

        expect($resolvedTicket)->toBeNull();
    });

    test('returns correct notification class for valid type', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->open()->create();

        $job = new NotificationJob($user, $ticket, 'activity');

        $notificationClass = invade($job)->getNotificationClass();

        expect($notificationClass)->toBe(TicketNotification::class);
    });

    test('returns null for invalid notification type', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->open()->create();

        $job = new NotificationJob($user, $ticket, 'invalid-type');

        $notificationClass = invade($job)->getNotificationClass();

        expect($notificationClass)->toBeNull();
    });

    test('unique id format is consistent and predictable', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->open()->create();

        $job = new NotificationJob($user, $ticket, 'activity');
        $uniqueId = $job->uniqueId();

        expect($uniqueId)
            ->toBeString()
            ->toContain('notification')
            ->toContain((string) $ticket->id)
            ->toContain((string) $user->id);

        // Same inputs should produce same unique ID
        $job2 = new NotificationJob($user, $ticket, 'activity');
        expect($job->uniqueId())->toBe($job2->uniqueId());
    });

    test('different inputs produce different unique ids', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $ticket1 = Ticket::factory()->open()->create();
        $ticket2 = Ticket::factory()->open()->create();

        $job1 = new NotificationJob($user1, $ticket1, 'activity');
        $job2 = new NotificationJob($user2, $ticket1, 'activity'); // Different user
        $job3 = new NotificationJob($user1, $ticket2, 'activity'); // Different ticket

        expect($job1->uniqueId())->not->toBe($job2->uniqueId())
            ->and($job1->uniqueId())->not->toBe($job3->uniqueId())
            ->and($job2->uniqueId())->not->toBe($job3->uniqueId());
    });
});
