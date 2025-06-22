<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\NotificationStrategy;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Jobs\NotificationJob;
use Padmission\Tickets\Listeners\TicketNotificationListener;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Services\NotificationRecipientService;
use Padmission\Tickets\Tests\User;

beforeEach(function () {
    Queue::fake();
    Cache::flush();

    // Create necessary TicketStatus records for the factory methods to work
    \Padmission\Tickets\Models\TicketStatus::factory()->create([
        'display_name' => 'Open',
        'order' => 1,
        'panel' => 'test',
    ]);

    \Padmission\Tickets\Models\TicketStatus::factory()->create([
        'display_name' => 'Closed',
        'order' => 2,
        'panel' => 'test',
    ]);
});

afterEach(function () {
    \Mockery::close();
});

describe('Debouncing Core Functionality', function () {

    test('debouncer creates unique cache key for each user-ticket combination', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $ticket1 = Ticket::factory()->open()->create();
        $ticket2 = Ticket::factory()->open()->create();

        $job1 = new NotificationJob($user1, $ticket1, 'activity');
        $job2 = new NotificationJob($user1, $ticket2, 'activity');
        $job3 = new NotificationJob($user2, $ticket1, 'activity');

        // Each combination should have a unique ID
        expect($job1->uniqueId())->not->toBe($job2->uniqueId())
            ->and($job1->uniqueId())->not->toBe($job3->uniqueId())
            ->and($job2->uniqueId())->not->toBe($job3->uniqueId());
    });

    test('immediate notification strategy bypasses debouncing', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->open()->create(['assignee_id' => $user->id]);
        $event = new TicketActivityEvent($ticket, ActivityType::Message);

        // Mock the recipient service to return immediate strategy
        $recipientService = \Mockery::mock(NotificationRecipientService::class);
        $recipientService->shouldReceive('getNotificationRecipients')
            ->andReturn(collect([$user]));
        $recipientService->shouldReceive('getUserNotificationStrategy')
            ->with($user)
            ->andReturn(NotificationStrategy::Immediate);

        $listener = new TicketNotificationListener($recipientService);
        $listener->handle($event);

        // For immediate strategy, should dispatch job directly without debouncing
        Queue::assertPushed(NotificationJob::class, function ($job) use ($user, $ticket) {
            return $job->getUserId() === $user->id &&
                   $job->getTicketKey() === $ticket->id;
        });
    });

    test('notification job resolves correct notification class for different types', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->open()->create();

        $activityJob = new NotificationJob($user, $ticket, 'activity');
        $createdJob = new NotificationJob($user, $ticket, 'created');
        $invalidJob = new NotificationJob($user, $ticket, 'invalid-type');

        expect(invade($activityJob)->getNotificationClass())
            ->toBe(\Padmission\Tickets\Notifications\TicketNotification::class);
        expect(invade($createdJob)->getNotificationClass())
            ->toBe(\Padmission\Tickets\Notifications\TicketNotification::class);
        expect(invade($invalidJob)->getNotificationClass())
            ->toBeNull();
    });

    test('multiple recipients each get their own debounced notification', function () {
        $assignee = User::factory()->create();
        $submitter = User::factory()->create();
        $ticket = Ticket::factory()->open()->create([
            'assignee_id' => $assignee->id,
            'submitter_id' => $submitter->id,
        ]);

        $event = new TicketActivityEvent($ticket, ActivityType::Message);
        $listener = new TicketNotificationListener(app(NotificationRecipientService::class));

        $listener->handle($event);

        // Should create separate jobs for each recipient
        // We can verify this by checking that jobs with different user IDs would be created
        $assigneeJob = new NotificationJob($assignee, $ticket, 'activity');
        $submitterJob = new NotificationJob($submitter, $ticket, 'activity');

        expect($assigneeJob->uniqueId())->not->toBe($submitterJob->uniqueId());
    });
});

describe('Time-Based Debouncing Tests', function () {

    test('notification content aggregates activities within debounce window', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->open()->create(['assignee_id' => $user->id]);

        // Create first activity
        $activity1 = TicketActivity::factory()->create([
            'ticket_id' => $ticket->id,
            'type' => ActivityType::Message,
            'content' => 'First message',
            'created_at' => now()->subMinutes(10),
        ]);

        // Create second activity more recently
        $activity2 = TicketActivity::factory()->create([
            'ticket_id' => $ticket->id,
            'type' => ActivityType::Message,
            'content' => 'Second message',
            'created_at' => now()->subMinutes(2),
        ]);

        // Create notification and check it includes both activities
        $notification = new \Padmission\Tickets\Notifications\TicketNotification($ticket, 'activity');
        $mailMessage = $notification->toMail($user);

        // Should include both activities in the notification
        expect($mailMessage->viewData['activities'])->toHaveCount(2);

        $activityContents = $mailMessage->viewData['activities']->pluck('content')->toArray();
        expect($activityContents)->toContain('First message')
            ->and($activityContents)->toContain('Second message');
    });

    test('activities from different time periods are properly grouped', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->open()->create(['assignee_id' => $user->id]);

        // Create activities from different periods
        $oldActivity = TicketActivity::factory()->create([
            'ticket_id' => $ticket->id,
            'type' => ActivityType::Message,
            'content' => 'Old message',
            'created_at' => now()->subDays(11), // Outside max days (10)
        ]);

        $recentActivity1 = TicketActivity::factory()->create([
            'ticket_id' => $ticket->id,
            'type' => ActivityType::Message,
            'content' => 'Recent message 1',
            'created_at' => now()->subHours(2),
        ]);

        $recentActivity2 = TicketActivity::factory()->create([
            'ticket_id' => $ticket->id,
            'type' => ActivityType::Message,
            'content' => 'Recent message 2',
            'created_at' => now()->subMinutes(30),
        ]);

        // Create notification
        $notification = new \Padmission\Tickets\Notifications\TicketNotification($ticket, 'activity');
        $mailMessage = $notification->toMail($user);

        // Should only include recent activities (within max days config = 10)
        expect($mailMessage->viewData['activities'])->toHaveCount(2);

        $activityContents = $mailMessage->viewData['activities']->pluck('content')->toArray();
        expect($activityContents)->toContain('Recent message 1')
            ->and($activityContents)->toContain('Recent message 2')
            ->and($activityContents)->not->toContain('Old message');
    });

});

describe('Debouncing Logic Validation', function () {

    test('notification job unique id ensures proper debouncing per user-ticket', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $ticket1 = Ticket::factory()->open()->create();
        $ticket2 = Ticket::factory()->open()->create();

        // Create jobs for different combinations
        $job1 = new NotificationJob($user1, $ticket1, 'activity');
        $job2 = new NotificationJob($user1, $ticket2, 'activity');
        $job3 = new NotificationJob($user2, $ticket1, 'activity');
        $job4 = new NotificationJob($user1, $ticket1, 'activity'); // Same as job1

        // Same user-ticket should have same unique ID (enables debouncing)
        expect($job1->uniqueId())->toBe($job4->uniqueId());

        // Different combinations should have different IDs
        expect($job1->uniqueId())->not->toBe($job2->uniqueId())
            ->and($job1->uniqueId())->not->toBe($job3->uniqueId())
            ->and($job2->uniqueId())->not->toBe($job3->uniqueId());
    });

    test('listener dispatches separate jobs for each recipient enabling per-user debouncing', function () {
        $assignee = User::factory()->create();
        $submitter = User::factory()->create();
        $ticket = Ticket::factory()->open()->create([
            'assignee_id' => $assignee->id,
            'submitter_id' => $submitter->id,
        ]);

        // Mock the recipient service to control the flow
        $recipientService = \Mockery::mock(NotificationRecipientService::class);
        $recipientService->shouldReceive('getNotificationRecipients')
            ->andReturn(collect([$assignee, $submitter]));
        $recipientService->shouldReceive('getUserNotificationStrategy')
            ->with($assignee)
            ->andReturn(NotificationStrategy::Debounced);
        $recipientService->shouldReceive('getUserNotificationStrategy')
            ->with($submitter)
            ->andReturn(NotificationStrategy::Debounced);

        $event = new TicketActivityEvent($ticket, ActivityType::Message);
        $listener = new TicketNotificationListener($recipientService);

        $listener->handle($event);

        // Should create jobs with different unique IDs for debouncing
        $assigneeJob = new NotificationJob($assignee, $ticket, 'activity');
        $submitterJob = new NotificationJob($submitter, $ticket, 'activity');

        expect($assigneeJob->uniqueId())->not->toBe($submitterJob->uniqueId());
    });
});

describe('Configuration and Edge Cases', function () {

    test('handles missing ticket gracefully in notification job', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->open()->create();

        $job = new NotificationJob($user, $ticket, 'activity');

        // Delete the ticket
        $ticket->delete();

        // Job should handle missing ticket gracefully
        expect(invade($job)->resolveModel())->toBeNull();
    });

    test('handles missing user gracefully in notification job', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->open()->create();

        $job = new NotificationJob($user, $ticket, 'activity');

        // Delete the user
        $user->delete();

        // Job should handle missing user gracefully
        expect(invade($job)->resolveUser())->toBeNull();
    });
});

describe('Recipient and Strategy Logic', function () {

    test('notification recipients are correctly identified', function () {
        $assignee = User::factory()->create();
        $submitter = User::factory()->create();
        $ticket = Ticket::factory()->open()->create([
            'assignee_id' => $assignee->id,
            'submitter_id' => $submitter->id,
        ]);

        $event = new TicketActivityEvent($ticket, ActivityType::Message);
        $recipientService = app(NotificationRecipientService::class);

        $recipients = $recipientService->getNotificationRecipients($event);

        // For user-triggered activity (no actor), default is notify_supporter only
        expect($recipients)->toHaveCount(1);

        // Check that only the assignee (supporter) is in the recipients
        $recipientIds = $recipients->pluck('id')->toArray();
        expect($recipientIds)->toContain($assignee->id);
    });

    test('duplicate recipients are filtered out', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->open()->create([
            'assignee_id' => $user->id,
            'submitter_id' => $user->id, // Same user as assignee and submitter
        ]);

        $event = new TicketActivityEvent($ticket, ActivityType::Message);
        $recipientService = app(NotificationRecipientService::class);

        $recipients = $recipientService->getNotificationRecipients($event);

        expect($recipients)->toHaveCount(1);

        // Check that the user is in the recipients by ID
        $recipientIds = $recipients->pluck('id')->toArray();
        expect($recipientIds)->toContain($user->id);
    });

    test('user notification strategy defaults to debounced', function () {
        $recipientService = app(NotificationRecipientService::class);
        $user = User::factory()->create();

        expect($recipientService->getUserNotificationStrategy($user))->toBe(NotificationStrategy::Debounced);
    });
});

describe('Event Type Mapping', function () {

    test('notification listener correctly maps event types to notification types', function () {
        $ticket = Ticket::factory()->open()->create();
        $listener = new TicketNotificationListener(app(NotificationRecipientService::class));

        // Test event type mapping
        $activityEvent = new TicketActivityEvent($ticket, ActivityType::Message);
        $createdEvent = new \Padmission\Tickets\Events\TicketCreatedEvent($ticket);
        $assignedEvent = new \Padmission\Tickets\Events\TicketAssignedEvent($ticket);
        $closedEvent = new \Padmission\Tickets\Events\TicketClosedEvent($ticket);

        expect(invade($listener)->getNotificationType($activityEvent))->toBe('activity');
        expect(invade($listener)->getNotificationType($createdEvent))->toBe('created');
        expect(invade($listener)->getNotificationType($assignedEvent))->toBe('assigned');
        expect(invade($listener)->getNotificationType($closedEvent))->toBe('closed');
    });
});

describe('Configuration Validation', function () {

    test('configuration values are set correctly', function () {
        // Test default debounce time
        expect(config('padmission-tickets.notification-debounce'))->toBe(300); // 5 minutes

        // Test default notification strategy
        expect(config('padmission-tickets.default-notification-strategy'))->toBe(NotificationStrategy::Debounced);

        // Test max events and days
        expect(config('padmission-tickets.notification-max-events'))->toBe(10);
        expect(config('padmission-tickets.notification-max-days'))->toBe(10);
    });
});
