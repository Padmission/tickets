<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\NotificationStrategy;
use Padmission\Tickets\Events\TicketActivityEvent;
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

    test('debounced notification strategy stores cache key for debouncing', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->open()->create(['assignee_id' => $user->id]);
        $event = new TicketActivityEvent($ticket, ActivityType::Message);

        $recipientService = Mockery::mock(NotificationRecipientService::class);

        $recipientService->shouldReceive('getNotificationRecipients')
            ->andReturn(collect([$user]));

        $recipientService->shouldReceive('getUserNotificationStrategy')
            ->with($user)
            ->andReturn(NotificationStrategy::Debounced);

        $listener = new TicketNotificationListener($recipientService);
        $listener->handle($event);

        // For debounced strategy, a cache key should be set for debouncing
        $job = new NotificationJob($user, $ticket, 'activity');
        $cacheKey = $job->uniqueId();

        // The cache should have a value (the unique identifier for this dispatch)
        expect(Cache::has($cacheKey))->toBeTrue();
    });

    test('subsequent events within debounce period replace the cache identifier', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->open()->create(['assignee_id' => $user->id]);
        $event = new TicketActivityEvent($ticket, ActivityType::Message);

        $recipientService = Mockery::mock(NotificationRecipientService::class);

        $recipientService->shouldReceive('getNotificationRecipients')
            ->andReturn(collect([$user]));

        $recipientService->shouldReceive('getUserNotificationStrategy')
            ->with($user)
            ->andReturn(NotificationStrategy::Debounced);

        $listener = new TicketNotificationListener($recipientService);

        // First event
        $listener->handle($event);

        $job = new NotificationJob($user, $ticket, 'activity');
        $cacheKey = $job->uniqueId();
        $firstIdentifier = Cache::get($cacheKey);

        // Second event (simulating another message within debounce period)
        $listener->handle($event);

        $secondIdentifier = Cache::get($cacheKey);

        // The identifier should be different (new dispatch replaces old one)
        expect($secondIdentifier)->not->toBe($firstIdentifier);
    });

    test('notification job resolves correct notification class for different types', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->open()->create();

        $activityJob = new NotificationJob($user, $ticket, 'activity');
        $createdJob = new NotificationJob($user, $ticket, 'created');
        $invalidJob = new NotificationJob($user, $ticket, 'invalid-type');

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
        $notification = new TicketNotification($ticket, 'activity');
        $mailMessage = $notification->toMail($user);

        expect($mailMessage->viewData['activities'])->toHaveCount(2);

        $activityContents = $mailMessage->viewData['activities']->pluck('content')->toArray();
        expect($activityContents)->toContain('Recent message 1')
            ->and($activityContents)->toContain('Recent message 2')
            ->and($activityContents)->not->toContain('Old message');
    });

    test('subsequent notifications only include activities since last notification', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->open()->create(['assignee_id' => $user->id]);

        config()->set('padmission-tickets.notification-max-days', 10);

        // Create first activity
        TicketActivity::factory()->create([
            'ticket_id' => $ticket->id,
            'type' => ActivityType::Message,
            'content' => 'First message',
            'created_at' => now()->subHours(2),
        ]);

        // Send first notification
        $notification1 = new TicketNotification($ticket, 'activity');
        $mailMessage1 = $notification1->toMail($user);

        expect($mailMessage1->viewData['activities'])->toHaveCount(1);
        $activityContents1 = $mailMessage1->viewData['activities']->pluck('content')->toArray();
        expect($activityContents1)->toContain('First message');

        // Create second activity AFTER the first notification
        TicketActivity::factory()->create([
            'ticket_id' => $ticket->id,
            'type' => ActivityType::Message,
            'content' => 'Second message',
            'created_at' => now()->subMinutes(30),
        ]);

        // Send second notification
        $notification2 = new TicketNotification($ticket, 'activity');
        $mailMessage2 = $notification2->toMail($user);

        // Should only include the second message
        expect($mailMessage2->viewData['activities'])->toHaveCount(1);
        $activityContents2 = $mailMessage2->viewData['activities']->pluck('content')->toArray();
        expect($activityContents2)->toContain('Second message')
            ->and($activityContents2)->not->toContain('First message');
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

    test('default notification strategy is debounced', function () {
        $recipientService = app(NotificationRecipientService::class);
        $user = User::factory()->create();

        expect($recipientService->getUserNotificationStrategy($user))->toBe(NotificationStrategy::Debounced);
    });

    test('debounce time can be configured', function () {
        config()->set('padmission-tickets.notification-debounce', 600); // 10 minutes

        expect(config('padmission-tickets.notification-debounce'))->toBe(600);

        // Reset to default
        config()->set('padmission-tickets.notification-debounce', 300);
    });

    test('user can customize notification strategy via ticketNotificationStrategy method', function () {
        // Create a user class that implements ticketNotificationStrategy
        $userWithImmediateStrategy = new class extends User {
            public function ticketNotificationStrategy(): NotificationStrategy
            {
                return NotificationStrategy::Immediate;
            }
        };
        $userWithImmediateStrategy->id = 999;
        $userWithImmediateStrategy->name = 'Test User';
        $userWithImmediateStrategy->email = 'test@example.com';

        $recipientService = app(NotificationRecipientService::class);

        // User with custom ticketNotificationStrategy should return Immediate
        expect($recipientService->getUserNotificationStrategy($userWithImmediateStrategy))
            ->toBe(NotificationStrategy::Immediate);

        // User without ticketNotificationStrategy should return default (Debounced)
        $normalUser = User::factory()->create();
        expect($recipientService->getUserNotificationStrategy($normalUser))
            ->toBe(NotificationStrategy::Debounced);
    });
});
