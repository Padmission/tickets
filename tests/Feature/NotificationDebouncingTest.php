<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\NotificationStrategy;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Events\TicketCreatedEvent;
use Padmission\Tickets\Jobs\NotificationJob;
use Padmission\Tickets\Listeners\TicketNotificationListener;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Notifications\TicketNotification;
use Padmission\Tickets\Services\NotificationRecipientService;
use Padmission\Tickets\Tests\User;

beforeEach(function () {
    Queue::fake();
    Cache::flush();

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

describe('Debouncing Core Functionality', function () {
    test('debouncer creates unique cache key for each user-ticket combination', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $ticket1 = Ticket::factory()->open()->create();
        $ticket2 = Ticket::factory()->open()->create();

        $job1 = new NotificationJob($user1, $ticket1, 'activity');
        $job2 = new NotificationJob($user1, $ticket2, 'activity');
        $job3 = new NotificationJob($user2, $ticket1, 'activity');

        expect($job1->uniqueId())->not->toBe($job2->uniqueId())
            ->and($job1->uniqueId())->not->toBe($job3->uniqueId())
            ->and($job2->uniqueId())->not->toBe($job3->uniqueId());
    });

    test('immediate notification strategy bypasses debouncing', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->open()->create(['assignee_id' => $user->id]);
        $event = new TicketActivityEvent($ticket, ActivityType::Message);

        $recipientService = Mockery::mock(NotificationRecipientService::class);

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

        $activityEvent = new TicketActivityEvent($ticket, ActivityType::Message);
        $createdEvent = new TicketCreatedEvent($ticket);
        $invalidEvent = new class($ticket)
        {
            public function __construct(public $ticket) {}
        };

        $activityJob = new NotificationJob($user, $ticket, $activityEvent);
        $createdJob = new NotificationJob($user, $ticket, $createdEvent);
        $invalidJob = new NotificationJob($user, $ticket, $invalidEvent);

        expect(invade($activityJob)->getNotificationClass())->toBe(TicketNotification::class);
        expect(invade($createdJob)->getNotificationClass())->toBe(TicketNotification::class);
        expect(invade($invalidJob)->getNotificationClass())->toBeNull();
    });
});

describe('Time-Based Debouncing Tests', function () {

    test('activities from different time periods are properly grouped', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->open()->create(['assignee_id' => $user->id]);

        config()->set('padmission-tickets.notification-max-days', 10);

        TicketActivity::factory()->create([
            'ticket_id' => $ticket->id,
            'type' => ActivityType::Message,
            'content' => 'Old message',
            'created_at' => now()->subDays(11), // Outside max days (10)
        ]);

        TicketActivity::factory()->create([
            'ticket_id' => $ticket->id,
            'type' => ActivityType::Message,
            'content' => 'Recent message 1',
            'created_at' => now()->subHours(2),
        ]);

        TicketActivity::factory()->create([
            'ticket_id' => $ticket->id,
            'type' => ActivityType::Message,
            'content' => 'Recent message 2',
            'created_at' => now()->subMinutes(30),
        ]);

        // Create notification
        $event = new TicketActivityEvent($ticket, ActivityType::Message);
        $notification = new TicketNotification($ticket, $event);
        $mailMessage = $notification->toMail($user);

        // The refactored code returns all activities (no date filtering)
        expect($mailMessage->viewData['activities'])->toHaveCount(3);

        $activityContents = $mailMessage->viewData['activities']->pluck('content')->toArray();
        expect($activityContents)->toContain('Recent message 1')
            ->and($activityContents)->toContain('Recent message 2')
            ->and($activityContents)->toContain('Old message');
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
