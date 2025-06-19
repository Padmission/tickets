<?php

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Padmission\Tickets\ConfigurationManagers\NotificationConfiguration;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Tests\User;
use Carbon\Carbon;

beforeEach(function () {
    Queue::fake();
    Notification::fake();

    // Create test users
    $this->submitter = User::factory()->create(['name' => 'Customer User']);
    $this->supporter = User::factory()->create(['name' => 'Support Agent']);

    // Seed priorities if they don't exist
    if (!TicketPriority::where('display_name', 'Urgent')->exists()) {
        TicketPriority::factory()->create(['display_name' => 'Urgent', 'order' => 1]);
    }
    if (!TicketPriority::where('display_name', 'Normal')->exists()) {
        TicketPriority::factory()->create(['display_name' => 'Normal', 'order' => 2]);
    }
});

describe('Documentation Examples - Basic Configuration', function () {

    test('basic configuration example works', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(
                userTriggered: ['notify_user' => true, 'notify_supporter' => false],
                supporterTriggered: ['notify_user' => true, 'notify_supporter' => true]
            )
            ->onTicketActivity(
                userTriggered: ['notify_user' => false, 'notify_supporter' => true],
                supporterTriggered: ['notify_user' => true, 'notify_supporter' => false]
            );

        // Test ticket created configuration
        $createdSettings = $config->getSettingsFor('ticket_created');
        
        $userTriggered = $createdSettings->getSettingsFor('user_triggered');
        expect($userTriggered)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('notify_supporter', false);

        $supporterTriggered = $createdSettings->getSettingsFor('supporter_triggered');
        expect($supporterTriggered)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('notify_supporter', true);

        // Test ticket activity configuration
        $activitySettings = $config->getSettingsFor('ticket_activity');
        
        $activityUserTriggered = $activitySettings->getSettingsFor('user_triggered');
        expect($activityUserTriggered)
            ->toHaveKey('notify_user', false)
            ->toHaveKey('notify_supporter', true);

        $activitySupporterTriggered = $activitySettings->getSettingsFor('supporter_triggered');
        expect($activitySupporterTriggered)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('notify_supporter', false);
    });

    test('fluent API configuration example works', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->notifyBoth(); // Both user and supporter
            })
            ->onTicketActivity(function ($context) {
                $context->whenUserTriggered(['notify_user' => false, 'notify_supporter' => true])
                        ->whenSupporterTriggered(['notify_user' => true, 'notify_supporter' => false]);
            })
            ->onTicketClosed(function ($context) {
                $context->onlyNotifyUser(); // Only the ticket submitter
            });

        // Verify ticket created
        $createdSettings = $config->getSettingsFor('ticket_created');
        $createdConfig = $createdSettings->getSettingsFor('user_triggered');
        expect($createdConfig)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('notify_supporter', true);

        // Verify ticket activity
        $activitySettings = $config->getSettingsFor('ticket_activity');
        $activityUserConfig = $activitySettings->getSettingsFor('user_triggered');
        $activitySupporterConfig = $activitySettings->getSettingsFor('supporter_triggered');
        expect($activityUserConfig)
            ->toHaveKey('notify_user', false)
            ->toHaveKey('notify_supporter', true);
        expect($activitySupporterConfig)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('notify_supporter', false);

        // Verify ticket closed
        $closedSettings = $config->getSettingsFor('ticket_closed');
        $closedConfig = $closedSettings->getSettingsFor('user_triggered');
        expect($closedConfig)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('notify_supporter', false);
    });
});

describe('Documentation Examples - Boolean Helper Methods', function () {

    test('boolean helper methods work correctly', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->onlyNotifyUser();           // Only notify ticket submitter
            })
            ->onTicketActivity(function ($context) {
                $context->onlyNotifySupporter();      // Only notify ticket assignee  
            })
            ->onTicketClosed(function ($context) {
                $context->notifyBoth();           // Notify both
            })
            ->onTicketAssigned(function ($context) {
                $context->notifyNone();           // Notify neither
            });

        // Test onlyNotifyUser
        $createdSettings = $config->getSettingsFor('ticket_created');
        $createdConfig = $createdSettings->getSettingsFor('user_triggered');
        expect($createdConfig)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('notify_supporter', false);

        // Test onlyNotifySupporter  
        $activitySettings = $config->getSettingsFor('ticket_activity');
        $activityConfig = $activitySettings->getSettingsFor('user_triggered');
        expect($activityConfig)
            ->toHaveKey('notify_user', false)
            ->toHaveKey('notify_supporter', true);

        // Test notifyBoth
        $closedSettings = $config->getSettingsFor('ticket_closed');
        $closedConfig = $closedSettings->getSettingsFor('user_triggered');
        expect($closedConfig)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('notify_supporter', true);

        // Test notifyNone
        $assignedSettings = $config->getSettingsFor('ticket_assigned');
        $assignedConfig = $assignedSettings->getSettingsFor('user_triggered');
        expect($assignedConfig)
            ->toHaveKey('notify_user', false)
            ->toHaveKey('notify_supporter', false);
    });
});

describe('Documentation Examples - Conditional Configuration', function () {

    test('conditional configuration with when/unless works', function () {
        $config = NotificationConfiguration::make()
            ->onTicketActivity(function ($context) {
                $context->when(true, function ($ctx) {
                    $ctx->notifyBoth();
                })->unless(false, function ($ctx) {
                    $ctx->notifySupporter();
                });
            });

        $settings = $config->getSettingsFor('ticket_activity');
        $userTriggered = $settings->getSettingsFor('user_triggered');

        expect($userTriggered)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('notify_supporter', true);
    });

    test('environment-specific configuration works', function () {
        // Test in testing environment (current)
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->inEnvironment('production', function ($ctx) {
                    $ctx->notifyBoth();
                })->inEnvironment('testing', function ($ctx) {
                    $ctx->notifyNone(); // Don't spam in testing
                });
            });

        $settings = $config->getSettingsFor('ticket_created');
        $userTriggered = $settings->getSettingsFor('user_triggered');

        expect($userTriggered)
            ->toHaveKey('notify_user', false)
            ->toHaveKey('notify_supporter', false);
    });

    test('multiple environment configuration works', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->inEnvironment(['staging', 'testing'], function ($ctx) {
                    $ctx->onlyNotifySupporter();
                })->inEnvironment('production', function ($ctx) {
                    $ctx->notifyBoth();
                });
            });

        $settings = $config->getSettingsFor('ticket_created');
        $userTriggered = $settings->getSettingsFor('user_triggered');

        // Should match testing environment
        expect($userTriggered)
            ->toHaveKey('notify_user', false)
            ->toHaveKey('notify_supporter', true);
    });
});

describe('Documentation Examples - Business Hours Configuration', function () {

    test('business hours configuration works during business hours', function () {
        // Mock business hours (Monday 10 AM)
        Carbon::setTestNow(Carbon::parse('Monday 10:00'));

        $config = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $isBusinessHours = now()->between('09:00', '17:00') && now()->isWeekday();
                
                $context->when($isBusinessHours, function ($ctx) {
                    $ctx->notifyBoth();
                })->unless($isBusinessHours, function ($ctx) {
                    $ctx->onlyNotifySupporter(); // Only email after hours
                });
            });

        $settings = $config->getSettingsFor('ticket_created');
        $userTriggered = $settings->getSettingsFor('user_triggered');

        expect($userTriggered)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('notify_supporter', true);

        Carbon::setTestNow(); // Reset
    });

    test('business hours configuration works after hours', function () {
        // Mock after hours (Monday 8 PM)
        Carbon::setTestNow(Carbon::parse('Monday 20:00'));

        $config = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $isBusinessHours = now()->between('09:00', '17:00') && now()->isWeekday();
                
                $context->when($isBusinessHours, function ($ctx) {
                    $ctx->notifyBoth();
                })->unless($isBusinessHours, function ($ctx) {
                    $ctx->onlyNotifySupporter(); // Only email after hours
                });
            });

        $settings = $config->getSettingsFor('ticket_created');
        $userTriggered = $settings->getSettingsFor('user_triggered');

        expect($userTriggered)
            ->toHaveKey('notify_user', false)
            ->toHaveKey('notify_supporter', true);

        Carbon::setTestNow(); // Reset
    });
});

describe('Documentation Examples - Configuration Structure', function () {

    test('configuration methods exist and work', function () {
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

    test('panel-specific configurations work independently', function () {
        // Support panel configuration - notify everyone
        $supportConfig = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->notifyBoth();
            });

        // Customer portal configuration - only notify support
        $customerConfig = NotificationConfiguration::make()
            ->onTicketCreated(function ($context) {
                $context->onlyNotifySupporter();
            });

        // Test support panel behavior
        $supportSettings = $supportConfig->getSettingsFor('ticket_created');
        $supportUserTriggered = $supportSettings->getSettingsFor('user_triggered');
        expect($supportUserTriggered)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('notify_supporter', true);

        // Test customer panel behavior
        $customerSettings = $customerConfig->getSettingsFor('ticket_created');
        $customerUserTriggered = $customerSettings->getSettingsFor('user_triggered');
        expect($customerUserTriggered)
            ->toHaveKey('notify_user', false)
            ->toHaveKey('notify_supporter', true);
    });
});
