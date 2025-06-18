<?php

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Padmission\Tickets\ConfigurationManagers\NotificationConfiguration;
use Padmission\Tickets\Events\TicketCreatedEvent;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\NotificationRecipientService;
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

    // Create notification recipient service
    $this->recipientService = new NotificationRecipientService;
});

describe('Notification Configuration - Boolean Methods', function () {

    test('notifyUser() configures user notifications correctly', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->notifyUser();
            });

        $settings = $config->getSettingsFor('ticket_created');
        $userTriggered = $settings->getSettingsFor('user_triggered');

        expect($userTriggered)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('notify_supporter', false);
    });

    test('notifySupporter() configures supporter notifications correctly', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->notifySupporter();
            });

        $settings = $config->getSettingsFor('ticket_created');
        $userTriggered = $settings->getSettingsFor('user_triggered');

        expect($userTriggered)
            ->toHaveKey('notify_user', true)        // From defaults
            ->toHaveKey('notify_supporter', true);  // Set by notifySupporter()
    });

    test('notifyBoth() configures both notifications correctly', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->notifyBoth();
            });

        $settings = $config->getSettingsFor('ticket_created');
        $userTriggered = $settings->getSettingsFor('user_triggered');

        expect($userTriggered)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('notify_supporter', true);
    });

    test('notifyNone() disables all notifications', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->notifyNone();
            });

        $settings = $config->getSettingsFor('ticket_created');
        $userTriggered = $settings->getSettingsFor('user_triggered');

        expect($userTriggered)
            ->toHaveKey('notify_user', false)
            ->toHaveKey('notify_supporter', false);
    });
});

describe('Notification Configuration - Channel Methods', function () {

    test('enableChannel() adds channel configuration', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->enableChannel('email', 'both')
                    ->enableChannel('slack', 'supporter');
            });

        $settings = $config->getSettingsFor('ticket_created');
        $userTriggered = $settings->getSettingsFor('user_triggered');
        $supporterTriggered = $settings->getSettingsFor('supporter_triggered');

        // email_both should be in both userTriggered and supporterTriggered
        expect($userTriggered)
            ->toHaveKey('email_both', true);

        expect($supporterTriggered)
            ->toHaveKey('email_both', true);

        // slack_supporter should only be in supporterTriggered
        expect($supporterTriggered)
            ->toHaveKey('slack_supporter', true);

        expect($userTriggered)
            ->not->toHaveKey('slack_supporter');
    });

    test('disableChannel() removes channel configuration', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->enableChannel('email', 'both')
                    ->disableChannel('email', 'both');
            });

        $settings = $config->getSettingsFor('ticket_created');
        $userTriggered = $settings->getSettingsFor('user_triggered');
        $supporterTriggered = $settings->getSettingsFor('supporter_triggered');

        expect($userTriggered)
            ->toHaveKey('email_both', false);

        expect($supporterTriggered)
            ->toHaveKey('email_both', false);
    });

    test('viaChannels() configures multiple channels at once', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->viaChannels([
                    'email' => true,
                    'slack' => false,
                    'sms' => true,
                ], 'supporter');
            });

        $settings = $config->getSettingsFor('ticket_created');
        $supporterTriggered = $settings->getSettingsFor('supporter_triggered');

        // All these should be in supporterTriggered since we specified 'supporter'
        expect($supporterTriggered)
            ->toHaveKey('email_supporter', true)
            ->toHaveKey('slack_supporter', false)
            ->toHaveKey('sms_supporter', true);
    });
});

describe('Notification Configuration - Array Format', function () {

    test('array format works with user and supporter triggered', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(
                userTriggered: ['notify_user' => true, 'notify_supporter' => false],
                supporterTriggered: ['notify_user' => true, 'notify_supporter' => true]
            );

        $settings = $config->getSettingsFor('ticket_created');

        $userTriggered = $settings->getSettingsFor('user_triggered');
        expect($userTriggered)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('notify_supporter', false);

        $supporterTriggered = $settings->getSettingsFor('supporter_triggered');
        expect($supporterTriggered)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('notify_supporter', true);
    });

    test('array format supports channel configuration', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(
                userTriggered: [
                    'notify_user' => true,
                    'email_user' => true,
                    'slack_supporter' => true,
                ]
            );

        $settings = $config->getSettingsFor('ticket_created');
        $userTriggered = $settings->getSettingsFor('user_triggered');

        expect($userTriggered)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('email_user', true)
            ->toHaveKey('slack_supporter', true);
    });
});

describe('Notification Configuration - Conditional Methods', function () {

    test('when() applies configuration conditionally', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->notifyUser()
                    ->when(true, function ($ctx) {
                        $ctx->enableChannel('sms', 'user');
                    })
                    ->when(false, function ($ctx) {
                        $ctx->enableChannel('slack', 'user');
                    });
            });

        $settings = $config->getSettingsFor('ticket_created');
        $userTriggered = $settings->getSettingsFor('user_triggered');

        expect($userTriggered)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('sms_user', true)
            ->not->toHaveKey('slack_user');
    });

    test('unless() applies configuration conditionally', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->notifyUser()
                    ->unless(false, function ($ctx) {
                        $ctx->enableChannel('email', 'user');
                    });
            });

        $settings = $config->getSettingsFor('ticket_created');
        $userTriggered = $settings->getSettingsFor('user_triggered');

        expect($userTriggered)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('email_user', true);
    });

    test('inEnvironment() applies configuration based on environment', function () {
        // Test in current environment (testing)
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->notifyUser()
                    ->inEnvironment('testing', function ($ctx) {
                        $ctx->enableChannel('log', 'user');
                    })
                    ->inEnvironment('production', function ($ctx) {
                        $ctx->enableChannel('slack', 'user');
                    });
            });

        $settings = $config->getSettingsFor('ticket_created');
        $userTriggered = $settings->getSettingsFor('user_triggered');

        expect($userTriggered)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('log_user', true)
            ->not->toHaveKey('slack_user');
    });
});

describe('Notification Recipient Service Integration', function () {

    test('recipient service uses configuration to determine recipients', function () {
        // Create a real plugin with our configuration
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->notifyBoth();
            });

        // Set up the plugin in the panel (using the test panel from TestCase)
        $panel = \Filament\Facades\Filament::getCurrentPanel();
        $plugin = \Padmission\Tickets\TicketPlugin::make()
            ->notificationConfiguration($config);

        // Register the plugin with the panel
        $panel->plugin($plugin);

        // Create event and get recipients
        $event = new TicketCreatedEvent($this->ticket, $this->submitter);
        $recipients = $this->recipientService->getNotificationRecipients($event);

        expect($recipients)
            ->toHaveCount(2)
            ->and($recipients->pluck('id')->toArray())
            ->toContain($this->submitter->id, $this->supporter->id);
    });

    test('recipient service respects notify user only configuration', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->onlyNotifyUser(); // Use onlyNotifyUser() for exclusive behavior
            });

        $panel = \Filament\Facades\Filament::getCurrentPanel();
        $plugin = \Padmission\Tickets\TicketPlugin::make()
            ->notificationConfiguration($config);

        $panel->plugin($plugin);

        $event = new TicketCreatedEvent($this->ticket, $this->submitter);
        $recipients = $this->recipientService->getNotificationRecipients($event);

        expect($recipients)
            ->toHaveCount(1)
            ->and($recipients->first()->id)
            ->toBe($this->submitter->id);
    });

    test('recipient service respects notify supporter only configuration', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->onlyNotifySupporter(); // Use onlyNotifySupporter() for exclusive behavior
            });

        $panel = \Filament\Facades\Filament::getCurrentPanel();
        $plugin = \Padmission\Tickets\TicketPlugin::make()
            ->notificationConfiguration($config);

        $panel->plugin($plugin);

        $event = new TicketCreatedEvent($this->ticket, $this->submitter);
        $recipients = $this->recipientService->getNotificationRecipients($event);

        expect($recipients)
            ->toHaveCount(1)
            ->and($recipients->first()->id)
            ->toBe($this->supporter->id);
    });

    test('recipient service handles channel-based configuration', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->enableChannel('email', 'both')
                    ->enableChannel('slack', 'supporter');
            });

        $panel = \Filament\Facades\Filament::getCurrentPanel();
        $plugin = \Padmission\Tickets\TicketPlugin::make()
            ->notificationConfiguration($config);

        $panel->plugin($plugin);

        $event = new TicketCreatedEvent($this->ticket, $this->submitter);
        $recipients = $this->recipientService->getNotificationRecipients($event);

        // Should notify both because channels are enabled
        expect($recipients)
            ->toHaveCount(2)
            ->and($recipients->pluck('id')->toArray())
            ->toContain($this->submitter->id, $this->supporter->id);
    });
});

describe('Multiple Event Types', function () {

    test('different events can have different configurations', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->notifyBoth();
            })
            ->onTicketActivity(function ($context) {
                $context->notifySupporter();
            })
            ->onTicketClosed(function ($context) {
                $context->notifyUser();
            });

        $createdSettings = $config->getSettingsFor('ticket_created');
        $activitySettings = $config->getSettingsFor('ticket_activity');
        $closedSettings = $config->getSettingsFor('ticket_closed');

        // Check ticket_created
        $createdConfig = $createdSettings->getSettingsFor('user_triggered');
        expect($createdConfig)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('notify_supporter', true);

        // Check ticket_activity
        $activityConfig = $activitySettings->getSettingsFor('user_triggered');
        expect($activityConfig)
            ->toHaveKey('notify_user', false)
            ->toHaveKey('notify_supporter', true);

        // Check ticket_closed
        $closedConfig = $closedSettings->getSettingsFor('user_triggered');
        expect($closedConfig)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('notify_supporter', false);
    });
});
