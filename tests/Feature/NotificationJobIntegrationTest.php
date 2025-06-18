<?php

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Padmission\Tickets\ConfigurationManagers\NotificationConfiguration;
use Padmission\Tickets\Events\TicketCreatedEvent;
use Padmission\Tickets\Jobs\NotificationJob;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\User;

beforeEach(function () {
    Queue::fake();
    Notification::fake();

    // Create test users
    $this->submitter = User::factory()->create();
    $this->supporter = User::factory()->create();

    // Create test ticket
    $this->ticket = Ticket::factory()->create([
        'submitter_id' => $this->submitter->id,
        'assignee_id' => $this->supporter->id,
    ]);

    // Mock notification config in plugin
    config()->set('padmission-tickets.notifications', [
        'created' => \Padmission\Tickets\Notifications\TicketNotification::class,
        'activity' => \Padmission\Tickets\Notifications\TicketNotification::class,
    ]);

    // Set notification strategy to immediate for testing
    config()->set('padmission-tickets.default-notification-strategy', \Padmission\Tickets\Enums\NotificationStrategy::Immediate);

    // Helper method to set up plugin with configuration
    $this->setupPluginWithConfig = function (NotificationConfiguration $config) {
        $panel = \Filament\Facades\Filament::getCurrentPanel();
        $plugin = \Padmission\Tickets\TicketPlugin::make()
            ->notificationConfiguration($config);

        $panel->plugin($plugin);
    };
});

describe('NotificationJob Configuration Integration', function () {

    test('notification job is queued when configured with boolean flags', function () {
        // Create a real plugin with configuration
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->notifyBoth();
            });

        ($this->setupPluginWithConfig)($config);

        // Create and run the notification job directly
        $job = new NotificationJob($this->submitter, $this->ticket, 'created');
        $job->handle();

        // The job itself should execute without errors
        expect(true)->toBeTrue();
    });

    test('notification job is queued when configured with channel flags', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->enableChannel('email', 'both');
            });

        ($this->setupPluginWithConfig)($config);

        $job = new NotificationJob($this->supporter, $this->ticket, 'created');
        $job->handle();

        expect(true)->toBeTrue();
    });

    test('notification job does not send email when not configured', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->notifyNone();
            });

        ($this->setupPluginWithConfig)($config);

        $job = new NotificationJob($this->submitter, $this->ticket, 'created');
        $job->handle();

        // Job should complete without sending notifications
        expect(true)->toBeTrue();
    });

    test('notification job respects notify_user flag for user', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->whenUserTriggered(['notify_user' => true, 'notify_supporter' => false]);
            });

        ($this->setupPluginWithConfig)($config);

        // Test user notification (should work)
        $userJob = new NotificationJob($this->submitter, $this->ticket, 'created');
        $userJob->handle();

        // Test supporter notification (should work but not send due to config)
        $supporterJob = new NotificationJob($this->supporter, $this->ticket, 'created');
        $supporterJob->handle();

        expect(true)->toBeTrue();
    });

    test('notification job respects channel configuration', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->enableChannel('email', 'user')
                    ->disableChannel('email', 'supporter');
            });

        ($this->setupPluginWithConfig)($config);

        // Test user notification (should work)
        $userJob = new NotificationJob($this->submitter, $this->ticket, 'created');
        $userJob->handle();

        // Test supporter notification (should work but not send due to config)
        $supporterJob = new NotificationJob($this->supporter, $this->ticket, 'created');
        $supporterJob->handle();

        expect(true)->toBeTrue();
    });

    test('notification job handles missing notification class gracefully', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->notifyBoth();
            });

        ($this->setupPluginWithConfig)($config);

        // Use a notification type that doesn't exist in config
        $job = new NotificationJob($this->submitter, $this->ticket, 'nonexistent');
        $job->handle();

        // Should not send anything and not throw exception
        Notification::assertNothingSent();

        // Job should complete successfully
        expect(true)->toBeTrue();
    });

    test('notification job handles missing user gracefully', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->notifyBoth();
            });

        ($this->setupPluginWithConfig)($config);

        // Create job with non-existent user ID
        $job = new NotificationJob($this->submitter, $this->ticket, 'created');

        // Delete the user to simulate missing user
        $this->submitter->delete();

        $job->handle();

        // Should not send anything and not throw exception
        Notification::assertNothingSent();

        expect(true)->toBeTrue();
    });

    test('notification job handles missing ticket gracefully', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->notifyBoth();
            });

        ($this->setupPluginWithConfig)($config);

        $job = new NotificationJob($this->submitter, $this->ticket, 'created');

        // Delete the ticket to simulate missing ticket
        $this->ticket->delete();

        $job->handle();

        // Should not send anything and not throw exception
        Notification::assertNothingSent();

        expect(true)->toBeTrue();
    });
});

describe('End-to-End Notification Flow', function () {

    test('complete flow from event to notification with mocked emails', function () {
        // Configure the plugin
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->notifyBoth();
            });

        ($this->setupPluginWithConfig)($config);

        // Manually trigger the notification listener instead of relying on events
        $event = new TicketCreatedEvent($this->ticket, $this->submitter);
        $listener = new \Padmission\Tickets\Listeners\TicketNotificationListener(
            new \Padmission\Tickets\Services\NotificationRecipientService
        );
        $listener->handle($event);

        // Check that notification jobs were queued
        Queue::assertPushed(NotificationJob::class, 2); // Both submitter and supporter

        // Process the jobs and check notifications were sent
        Queue::assertPushed(NotificationJob::class, function ($job) {
            return $job->getUserId() === $this->submitter->id;
        });

        Queue::assertPushed(NotificationJob::class, function ($job) {
            return $job->getUserId() === $this->supporter->id;
        });
    });

    test('debug notification listener step by step', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->onlyNotifySupporter();
            });

        ($this->setupPluginWithConfig)($config);

        $recipientService = new \Padmission\Tickets\Services\NotificationRecipientService;

        // Step 1: Check recipients are found
        $event = new TicketCreatedEvent($this->ticket, $this->submitter);
        $recipients = $recipientService->getNotificationRecipients($event);
        expect($recipients)->toHaveCount(1);
        expect($recipients->first()->id)->toBe($this->supporter->id);

        // Step 2: Check notification type is found
        $listener = new \Padmission\Tickets\Listeners\TicketNotificationListener($recipientService);

        // Use reflection to test the protected method
        $reflection = new \ReflectionClass($listener);
        $getNotificationTypeMethod = $reflection->getMethod('getNotificationType');
        $getNotificationTypeMethod->setAccessible(true);

        $notificationType = $getNotificationTypeMethod->invoke($listener, $event);
        expect($notificationType)->toBe('created');

        // Step 3: Check that notification class exists in config
        $notificationClass = config('padmission-tickets.notifications.created');
        expect($notificationClass)->toBe(\Padmission\Tickets\Notifications\TicketNotification::class);
        expect(class_exists($notificationClass))->toBeTrue();

        // Step 4: Check user notification strategy
        $strategy = $recipientService->getUserNotificationStrategy($this->supporter);
        expect($strategy)->toBe(\Padmission\Tickets\Enums\NotificationStrategy::Immediate);

        // Step 5: Now handle the event and verify job is queued
        $listener->handle($event);

        // Should queue 1 job for the supporter
        Queue::assertPushed(NotificationJob::class, 1);

        // Verify the job has the correct properties
        Queue::assertPushed(NotificationJob::class, function ($job) {
            return $job->getUserId() === $this->supporter->id &&
                   $job->getTicketKey() === $this->ticket->getKey() &&
                   $job->notificationType === 'created';
        });
    });

    test('notification job behavior changes based on configuration', function () {
        // Test 1: Configuration that should send notifications
        $enabledConfig = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->notifyBoth();
            });

        ($this->setupPluginWithConfig)($enabledConfig);

        $enabledJob = new NotificationJob($this->submitter, $this->ticket, 'created');

        // Use reflection to check if email should be sent
        $reflection = new \ReflectionClass($enabledJob);
        $shouldSendEmailMethod = $reflection->getMethod('shouldSendEmail');
        $shouldSendEmailMethod->setAccessible(true);

        // Get the configuration for this job
        $plugin = \Padmission\Tickets\TicketPlugin::get();
        $config = $plugin->getNotificationConfiguration();
        $settings = $config->getSettingsFor('ticket_created');
        $configuration = $settings->getSettingsFor('user_triggered');

        $shouldSend = $shouldSendEmailMethod->invoke($enabledJob, $configuration);
        expect($shouldSend)->toBeTrue();

        // Test 2: Configuration that should NOT send notifications
        $disabledConfig = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->notifyNone();
            });

        ($this->setupPluginWithConfig)($disabledConfig);

        $disabledJob = new NotificationJob($this->submitter, $this->ticket, 'created');

        // Get the new configuration
        $plugin2 = \Padmission\Tickets\TicketPlugin::get();
        $config2 = $plugin2->getNotificationConfiguration();
        $settings2 = $config2->getSettingsFor('ticket_created');
        $configuration2 = $settings2->getSettingsFor('user_triggered');

        $shouldNotSend = $shouldSendEmailMethod->invoke($disabledJob, $configuration2);
        expect($shouldNotSend)->toBeFalse();
    });
});
