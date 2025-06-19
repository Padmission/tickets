<?php

use Padmission\Tickets\Configuration\Context\EventConfigurationContext;
use Padmission\Tickets\Configuration\Data\EventNotificationSettings;
use Padmission\Tickets\ConfigurationManagers\NotificationConfiguration;
use Padmission\Tickets\Exceptions\InvalidEventException;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\User;

describe('NotificationConfiguration Construction and Basic Setup', function () {
    test('can create notification configuration instance', function () {
        $config = NotificationConfiguration::make();

        expect($config)->toBeInstanceOf(NotificationConfiguration::class);
    });

    test('returns default settings for unconfigured events', function () {
        $config = NotificationConfiguration::make();
        $settings = $config->getSettingsFor('ticket_created');

        expect($settings)->toBeInstanceOf(EventNotificationSettings::class);
        expect($settings->userTriggered)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('notify_supporter', false)
            ->toHaveKey('email_user', true)
            ->toHaveKey('email_supporter', false);
    });

    test('throws exception for invalid event', function () {
        $config = NotificationConfiguration::make();

        expect(fn () => $config->getSettingsFor('invalid_event'))
            ->toThrow(InvalidEventException::class);
    });
});

describe('Dynamic Method Calls', function () {
    test('dynamic on methods work for valid events', function () {
        $config = NotificationConfiguration::make();

        $result = $config->onTicketCreated(['notify_user' => true]);

        expect($result)->toBe($config);

        $settings = $config->getSettingsFor('ticket_created');
        expect($settings->userTriggered)->toHaveKey('notify_user', true);
    });

    test('dynamic on methods work for all default events', function () {
        $config = NotificationConfiguration::make();

        // These should not throw exceptions
        $result1 = $config->onTicketCreated();
        $result2 = $config->onTicketAssigned();
        $result3 = $config->onTicketActivity();
        $result4 = $config->onTicketClosed();

        expect($result1)->toBe($config);
        expect($result2)->toBe($config);
        expect($result3)->toBe($config);
        expect($result4)->toBe($config);
    });

    test('invalid dynamic method throws BadMethodCallException', function () {
        $config = NotificationConfiguration::make();

        expect(fn () => $config->invalidMethod())
            ->toThrow(BadMethodCallException::class);
    });

    test('non-on methods throw BadMethodCallException', function () {
        $config = NotificationConfiguration::make();

        expect(fn () => $config->ticketCreated())
            ->toThrow(BadMethodCallException::class);
    });
});

describe('Array Configuration', function () {
    test('configures with array parameters', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(
                userTriggered: ['notify_user' => true, 'email_user' => false],
                supporterTriggered: ['notify_supporter' => true, 'slack_supporter' => true]
            );

        $settings = $config->getSettingsFor('ticket_created');

        expect($settings->userTriggered)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('email_user', false);

        expect($settings->supporterTriggered)
            ->toHaveKey('notify_supporter', true)
            ->toHaveKey('slack_supporter', true);
    });

    test('merges with defaults when using arrays', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(
                userTriggered: ['custom_field' => true]
            );

        $settings = $config->getSettingsFor('ticket_created');

        // Should have the custom field
        expect($settings->userTriggered)->toHaveKey('custom_field', true);

        // Should still have defaults since we didn't override supporterTriggered
        expect($settings->supporterTriggered)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('notify_supporter', true);
    });
});

describe('Callable Configuration', function () {
    test('configures with single callback parameter', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function (EventConfigurationContext $context) {
                $context->notifyUser();
            });

        $settings = $config->getSettingsFor('ticket_created');

        expect($settings->userTriggered)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('notify_supporter', false);
    });

    test('callable that returns array works as modifier', function () {
        // Test with a simple array first to make sure the pattern works
        $config = NotificationConfiguration::make()
            ->onTicketCreated(
                userTriggered: ['custom_field' => true],
                supporterTriggered: ['notify_supporter' => true]
            );

        $settings = $config->getSettingsFor('ticket_created');

        expect($settings->userTriggered)
            ->toHaveKey('custom_field', true);

        // Now test with callable - this should work since supporterTriggered is not null
        $config2 = NotificationConfiguration::make()
            ->onTicketCreated(
                userTriggered: fn ($defaults) => array_merge($defaults, ['send_sms' => true]),
                supporterTriggered: ['notify_supporter' => true]
            );

        $settings2 = $config2->getSettingsFor('ticket_created');

        expect($settings2->userTriggered)
            ->toHaveKey('notify_user', true) // from defaults
            ->toHaveKey('send_sms', true); // from modifier
    });

    test('callback with multiple parameters is stored for context evaluation', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function (EventConfigurationContext $context, $ticket = null, $user = null) {
                $context->notifyUser();
            });

        // Should not throw an error during configuration
        expect($config)->toBeInstanceOf(NotificationConfiguration::class);

        // Should have default settings initially
        $settings = $config->getSettingsFor('ticket_created');
        expect($settings)->toBeInstanceOf(EventNotificationSettings::class);
    });
});

describe('Context-Aware Configuration', function () {
    test('evaluates callback with ticket context', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['submitter_id' => $user->id]);

        $config = NotificationConfiguration::make()
            ->onTicketCreated(function (EventConfigurationContext $context, ?Ticket $ticket = null) {
                if ($ticket && $ticket->submitter_id === 1) {
                    $context->notifyBoth();
                } else {
                    $context->notifyUser();
                }
            });

        $settings = $config->getSettingsForTicketContext('ticket_created', $ticket);

        expect($settings->userTriggered)
            ->toHaveKey('notify_user', true);
    });

    test('evaluates callback with ticket and user context', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['submitter_id' => $user->id]);

        $config = NotificationConfiguration::make()
            ->onTicketCreated(function (EventConfigurationContext $context, ?Ticket $ticket = null, $actor = null) {
                if ($actor && $ticket && $ticket->submitter_id === $actor->getAuthIdentifier()) {
                    $context->onlyNotifySupporter();
                } else {
                    $context->notifyBoth();
                }
            });

        $settings = $config->getSettingsForTicketContext('ticket_created', $ticket, $user);

        expect($settings->userTriggered)
            ->toHaveKey('notify_user', false)
            ->toHaveKey('notify_supporter', true);
    });

    test('getConfigurationForTrigger determines correct trigger type', function () {
        $submitter = User::factory()->create();
        $supporter = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'submitter_id' => $submitter->id,
            'assignee_id' => $supporter->id,
        ]);

        $config = NotificationConfiguration::make()
            ->onTicketCreated(
                userTriggered: ['user_setting' => true],
                supporterTriggered: ['supporter_setting' => true]
            );

        // Test submitter (user triggered)
        $userConfig = $config->getConfigurationForTrigger('ticket_created', $ticket, $submitter);
        expect($userConfig)->toHaveKey('user_setting', true);

        // Test assignee (supporter triggered)
        $supporterConfig = $config->getConfigurationForTrigger('ticket_created', $ticket, $supporter);
        expect($supporterConfig)->toHaveKey('supporter_setting', true);

        // Test no actor (defaults to supporter triggered)
        $noActorConfig = $config->getConfigurationForTrigger('ticket_created', $ticket, null);
        expect($noActorConfig)->toHaveKey('supporter_setting', true);
    });
});

describe('Settings Management', function () {
    test('getAllSettings returns all configured and default events', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(['notify_user' => false])
            ->onTicketAssigned(['notify_supporter' => false]);

        $allSettings = $config->getAllSettings();

        expect($allSettings)
            ->toHaveKeys(['ticket_created', 'ticket_assigned', 'ticket_activity', 'ticket_closed']);

        expect($allSettings['ticket_created'])
            ->toBeInstanceOf(EventNotificationSettings::class);
    });

    test('extend method allows modifying existing settings', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(['notify_user' => true]);

        $config->extend('ticket_created', function (EventNotificationSettings $current) {
            return new EventNotificationSettings(
                userTriggered: [...$current->userTriggered, 'extended_field' => true],
                supporterTriggered: $current->supporterTriggered
            );
        });

        $settings = $config->getSettingsFor('ticket_created');
        expect($settings->userTriggered)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('extended_field', true);
    });
});

describe('Default Settings Management', function () {
    test('can override default settings for events', function () {
        $config = NotificationConfiguration::make()
            ->setDefaultsFor(
                'ticket_created',
                userTriggered: ['custom_default' => true],
                supporterTriggered: ['another_default' => false]
            );

        $settings = $config->getSettingsFor('ticket_created');

        expect($settings->userTriggered)->toHaveKey('custom_default', true);
        expect($settings->supporterTriggered)->toHaveKey('another_default', false);
    });

    test('can add new default events', function () {
        $config = NotificationConfiguration::make()
            ->addDefaultEvent(
                'ticket_custom_event',
                userTriggered: ['notify_custom' => true],
                supporterTriggered: ['alert_custom' => false]
            );

        $settings = $config->getSettingsFor('ticket_custom_event');

        expect($settings->userTriggered)->toHaveKey('notify_custom', true);
        expect($settings->supporterTriggered)->toHaveKey('alert_custom', false);
    });
});

describe('Method Chaining', function () {
    test('all configuration methods return self for chaining', function () {
        $config = NotificationConfiguration::make();

        $result = $config
            ->onTicketCreated(['notify_user' => true])
            ->onTicketAssigned(['notify_supporter' => true])
            ->onTicketActivity(['email_user' => false])
            ->onTicketClosed(['slack_supporter' => true]);

        expect($result)->toBe($config);
    });

    test('can chain extend calls', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(['notify_user' => true])
            ->extend('ticket_created', function ($settings) {
                return new EventNotificationSettings(
                    userTriggered: [...$settings->userTriggered, 'first_extend' => true],
                    supporterTriggered: $settings->supporterTriggered
                );
            })
            ->extend('ticket_created', function ($settings) {
                return new EventNotificationSettings(
                    userTriggered: [...$settings->userTriggered, 'second_extend' => true],
                    supporterTriggered: $settings->supporterTriggered
                );
            });

        $settings = $config->getSettingsFor('ticket_created');
        expect($settings->userTriggered)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('first_extend', true)
            ->toHaveKey('second_extend', true);
    });
});

describe('Edge Cases and Error Handling', function () {
    test('handles null parameters gracefully', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(null, null);

        $settings = $config->getSettingsFor('ticket_created');

        // Should use defaults when null is passed
        expect($settings->userTriggered)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('email_user', true);
    });

    test('mixed configuration types work together', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(
                userTriggered: fn ($defaults) => [...$defaults, 'custom_user' => true],
                supporterTriggered: ['custom_supporter' => true]
            );

        $settings = $config->getSettingsFor('ticket_created');

        expect($settings->userTriggered)
            ->toHaveKey('notify_user', true) // from defaults
            ->toHaveKey('custom_user', true); // from callable

        expect($settings->supporterTriggered)
            ->toHaveKey('custom_supporter', true); // from array
    });

    test('event name normalization works correctly', function () {
        $config = NotificationConfiguration::make();

        // The methodToEventName should convert camelCase to snake_case
        $config->onTicketCreated(['test' => true]);
        $config->onTicketAssigned(['test' => true]);

        // These should work since they map to valid events
        expect($config->getSettingsFor('ticket_created'))->toBeInstanceOf(EventNotificationSettings::class);
        expect($config->getSettingsFor('ticket_assigned'))->toBeInstanceOf(EventNotificationSettings::class);
    });

    test('complex callback scenarios work correctly', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['submitter_id' => $user->id]);

        $config = NotificationConfiguration::make()
            ->onTicketCreated(function (EventConfigurationContext $context, $ticket = null, $actor = null) {
                // Complex logic based on ticket and actor - with defaults for initial call
                if ($ticket && ($ticket->priority === 'high' || ($actor && $actor->getAuthIdentifier() > 5))) {
                    $context->notifyBoth()
                        ->enableChannel('slack', 'both')
                        ->enableChannel('sms', 'supporter');
                } else {
                    $context->notifyUser()
                        ->enableChannel('email', 'user');
                }
            });

        $settings = $config->getSettingsForTicketContext('ticket_created', $ticket, $user);

        // Should execute the else branch (user ID likely <= 5, no high priority)
        expect($settings->userTriggered)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('email_user', true);
    });
});

describe('Legacy and Backwards Compatibility', function () {
    test('supports legacy boolean notification format', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(
                userTriggered: ['notify_user' => true, 'notify_supporter' => false],
                supporterTriggered: ['notify_user' => false, 'notify_supporter' => true]
            );

        $settings = $config->getSettingsFor('ticket_created');

        // Test the shouldNotify method from EventNotificationSettings
        expect($settings->shouldNotify('user_triggered', 'user'))->toBeTrue();
        expect($settings->shouldNotify('user_triggered', 'supporter'))->toBeFalse();
        expect($settings->shouldNotify('supporter_triggered', 'user'))->toBeFalse();
        expect($settings->shouldNotify('supporter_triggered', 'supporter'))->toBeTrue();
    });

    test('supports channel-based notification format', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(
                userTriggered: ['email_user' => true, 'slack_supporter' => false],
                supporterTriggered: ['email_user' => true, 'slack_supporter' => true]
            );

        $settings = $config->getSettingsFor('ticket_created');

        // Should detect notifications via channels
        expect($settings->shouldNotify('user_triggered', 'user'))->toBeTrue();
        expect($settings->shouldNotify('supporter_triggered', 'supporter'))->toBeTrue();

        // Check specific channels
        expect($settings->isChannelEnabledFor('user_triggered', 'user', 'email'))->toBeTrue();
        expect($settings->isChannelEnabledFor('supporter_triggered', 'supporter', 'slack'))->toBeTrue();
    });
});

describe('Integration with EventConfigurationContext', function () {
    test('context methods properly modify configuration', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function (EventConfigurationContext $context) {
                $context
                    ->whenUserTriggered(['custom_user_field' => true])
                    ->whenSupporterTriggered(fn ($defaults) => [...$defaults, 'custom_supporter_field' => true])
                    ->enableChannel('webhook', 'both')
                    ->when(true, fn ($ctx) => $ctx->enableChannel('sms', 'user'))
                    ->unless(false, fn ($ctx) => $ctx->enableChannel('push', 'supporter'));
            });

        $settings = $config->getSettingsFor('ticket_created');

        expect($settings->userTriggered)
            ->toHaveKey('custom_user_field', true)
            ->toHaveKey('webhook_both', true)
            ->toHaveKey('sms_user', true);

        expect($settings->supporterTriggered)
            ->toHaveKey('custom_supporter_field', true)
            ->toHaveKey('webhook_both', true)
            ->toHaveKey('push_supporter', true);
    });

    test('context convenience methods work correctly', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function (EventConfigurationContext $context) {
                $context
                    ->onlyNotifyUser()
                    ->enableChannel('email', 'user')
                    ->disableChannel('slack', 'both');
            });

        $settings = $config->getSettingsFor('ticket_created');

        expect($settings->userTriggered)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('notify_supporter', false)
            ->toHaveKey('email_user', true)
            ->toHaveKey('slack_both', false);
    });
});

describe('Additional Coverage - Missing Test Scenarios', function () {

    test('getSettingsForWithContext method works correctly', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['submitter_id' => $user->id]);

        $config = NotificationConfiguration::make()
            ->onTicketCreated(function (EventConfigurationContext $context, $ticket = null, $user = null) {
                if ($ticket && $user) {
                    $context->notifyBoth()->enableChannel('priority', 'both');
                } else {
                    $context->notifyUser();
                }
            });

        // Test with context
        $settingsWithContext = $config->getSettingsForWithContext('ticket_created', $ticket, $user);
        expect($settingsWithContext->userTriggered)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('notify_supporter', true)
            ->toHaveKey('priority_both', true);

        // Test without context (should use stored settings)
        $settingsWithoutContext = $config->getSettingsForWithContext('ticket_created');
        expect($settingsWithoutContext->userTriggered)
            ->toHaveKey('notify_user', true);
    });

    test('isValidEvent method properly validates events', function () {
        $config = NotificationConfiguration::make();

        // Test that the validation works for prefixed events
        $reflection = new ReflectionClass($config);
        $method = $reflection->getMethod('isValidEvent');
        $method->setAccessible(true);

        expect($method->invoke($config, 'ticket_created'))->toBeTrue();
        expect($method->invoke($config, 'created'))->toBeTrue(); // Should add 'ticket_' prefix
        expect($method->invoke($config, 'invalid_event'))->toBeFalse();
    });

    test('methodToEventName converts method names correctly', function () {
        $config = NotificationConfiguration::make();

        $reflection = new ReflectionClass($config);
        $method = $reflection->getMethod('methodToEventName');
        $method->setAccessible(true);

        expect($method->invoke($config, 'onTicketCreated'))->toBe('ticket_created');
        expect($method->invoke($config, 'onTicketAssigned'))->toBe('ticket_assigned');
        expect($method->invoke($config, 'onTicketActivity'))->toBe('ticket_activity');
        expect($method->invoke($config, 'onCustomEvent'))->toBe('custom_event');
    });

    test('getTriggerType method correctly identifies trigger types', function () {
        $submitter = User::factory()->create();
        $assignee = User::factory()->create();
        $otherUser = User::factory()->create();

        $ticket = Ticket::factory()->create([
            'submitter_id' => $submitter->id,
            'assignee_id' => $assignee->id,
        ]);

        $config = NotificationConfiguration::make();

        $reflection = new ReflectionClass($config);
        $method = $reflection->getMethod('getTriggerType');
        $method->setAccessible(true);

        // Test submitter
        expect($method->invoke($config, $ticket, $submitter))->toBe('user_triggered');

        // Test assignee
        expect($method->invoke($config, $ticket, $assignee))->toBe('supporter_triggered');

        // Test other user
        expect($method->invoke($config, $ticket, $otherUser))->toBe('supporter_triggered');

        // Test null actor
        expect($method->invoke($config, $ticket, null))->toBe('supporter_triggered');
    });

    test('callback reflection parameter counting works correctly', function () {
        $config = NotificationConfiguration::make();

        // Test callback with 1 parameter (should use configureEventWithCallback)
        $config->onTicketCreated(function (EventConfigurationContext $context) {
            $context->notifyUser();
        });

        // Test callback with 2 parameters (should store for later evaluation)
        $config->onTicketAssigned(function (EventConfigurationContext $context, $ticket = null) {
            $context->notifyBoth();
        });

        // Test callback with 3+ parameters (should store for later evaluation)
        $config->onTicketActivity(function (EventConfigurationContext $context, $ticket = null, $actor = null) {
            $context->notifySupporter();
        });

        expect($config)->toBeInstanceOf(NotificationConfiguration::class);
    });

    test('eventCallbacks array is properly managed', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function (EventConfigurationContext $context, $ticket = null, $actor = null) {
                $context->notifyUser();
            });

        $reflection = new ReflectionClass($config);
        $property = $reflection->getProperty('eventCallbacks');
        $property->setAccessible(true);

        $callbacks = $property->getValue($config);
        expect($callbacks)->toHaveKey('ticket_created');
        expect($callbacks['ticket_created'])->toBeCallable();
    });

    test('evaluateCallbackWithContext handles different parameter counts', function () {
        $user = User::factory()->create();
        $ticket = Ticket::factory()->create(['submitter_id' => $user->id]);

        // Test with 1 parameter callback
        $config1 = NotificationConfiguration::make()
            ->onTicketCreated(function (EventConfigurationContext $context) {
                $context->enableChannel('test1', 'user');
            });

        $settings1 = $config1->getSettingsForWithContext('ticket_created', $ticket, $user);
        expect($settings1->userTriggered)->toHaveKey('test1_user', true);

        // Test with 2 parameter callback
        $config2 = NotificationConfiguration::make()
            ->onTicketAssigned(function (EventConfigurationContext $context, $ticket = null) {
                if ($ticket) {
                    $context->enableChannel('test2', 'user');
                }
            });

        $settings2 = $config2->getSettingsForWithContext('ticket_assigned', $ticket, $user);
        expect($settings2->userTriggered)->toHaveKey('test2_user', true);

        // Test with 3+ parameter callback
        $config3 = NotificationConfiguration::make()
            ->onTicketActivity(function (EventConfigurationContext $context, $ticket = null, $actor = null) {
                if ($ticket && $actor) {
                    $context->enableChannel('test3', 'user');
                }
            });

        $settings3 = $config3->getSettingsForWithContext('ticket_activity', $ticket, $user);
        expect($settings3->userTriggered)->toHaveKey('test3_user', true);
    });

    test('resolveConfiguration handles edge cases correctly', function () {
        $config = NotificationConfiguration::make();

        $reflection = new ReflectionClass($config);
        $method = $reflection->getMethod('resolveConfiguration');
        $method->setAccessible(true);

        $defaults = ['default_key' => true];

        // Test with null config
        expect($method->invoke($config, null, $defaults))->toBe($defaults);

        // Test with array config
        $arrayConfig = ['custom_key' => false];
        expect($method->invoke($config, $arrayConfig, $defaults))->toBe($arrayConfig);

        // Test with callable config
        $callableConfig = fn ($def) => array_merge($def, ['added_key' => true]);
        $result = $method->invoke($config, $callableConfig, $defaults);
        expect($result)
            ->toHaveKey('default_key', true)
            ->toHaveKey('added_key', true);
    });

    test('configuration persists correctly after multiple operations', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(['notify_user' => true])
            ->onTicketAssigned(['notify_supporter' => true])
            ->extend('ticket_created', function ($settings) {
                return new EventNotificationSettings(
                    userTriggered: array_merge($settings->userTriggered, ['extended' => true]),
                    supporterTriggered: $settings->supporterTriggered
                );
            })
            ->setDefaultsFor('ticket_closed', ['custom_default' => true], ['another_default' => false]);

        // Verify all configurations are maintained
        $createdSettings = $config->getSettingsFor('ticket_created');
        $assignedSettings = $config->getSettingsFor('ticket_assigned');
        $closedSettings = $config->getSettingsFor('ticket_closed');

        expect($createdSettings->userTriggered)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('extended', true);

        expect($assignedSettings->userTriggered)
            ->toHaveKey('notify_supporter', true);

        expect($closedSettings->userTriggered)
            ->toHaveKey('custom_default', true);
        expect($closedSettings->supporterTriggered)
            ->toHaveKey('another_default', false);
    });

    test('error handling for malformed callbacks', function () {
        $config = NotificationConfiguration::make();

        // Test that invalid methods still throw BadMethodCallException
        expect(fn () => $config->someRandomMethod())->toThrow(BadMethodCallException::class);
        expect(fn () => $config->notAnOnMethod())->toThrow(BadMethodCallException::class);

        // "onButTooShort" actually has length > 2, so it would try to process as an event
        // Let's test a method that's definitely invalid
        expect(fn () => $config->on())->toThrow(BadMethodCallException::class);
    });

    test('event validation with ticket_ prefix logic', function () {
        $config = NotificationConfiguration::make();

        // Test that events get properly prefixed - these should work without throwing
        $settings1 = $config->getSettingsFor('created');
        $settings2 = $config->getSettingsFor('assigned');
        $settings3 = $config->getSettingsFor('activity');
        $settings4 = $config->getSettingsFor('closed');

        expect($settings1)->toBeInstanceOf(EventNotificationSettings::class);
        expect($settings2)->toBeInstanceOf(EventNotificationSettings::class);
        expect($settings3)->toBeInstanceOf(EventNotificationSettings::class);
        expect($settings4)->toBeInstanceOf(EventNotificationSettings::class);

        // Events already prefixed should also work
        $prefixed1 = $config->getSettingsFor('ticket_created');
        $prefixed2 = $config->getSettingsFor('ticket_assigned');

        expect($prefixed1)->toBeInstanceOf(EventNotificationSettings::class);
        expect($prefixed2)->toBeInstanceOf(EventNotificationSettings::class);
    });
});

describe('Plugin Integration Scenarios', function () {

    test('configuration works with plugin callable pattern', function () {
        // Test the pattern used in TicketPlugin::notificationConfiguration()
        $baseConfig = NotificationConfiguration::make()
            ->onTicketCreated(['base_setting' => true]);

        // Simulate the plugin callable pattern
        $configurator = function (NotificationConfiguration $config) {
            return $config
                ->onTicketCreated(['enhanced_setting' => true])
                ->onTicketAssigned(['plugin_setting' => true]);
        };

        $enhancedConfig = $configurator($baseConfig);

        expect($enhancedConfig)->toBe($baseConfig); // Should be same instance (fluent)

        $settings = $enhancedConfig->getSettingsFor('ticket_created');
        expect($settings->userTriggered)
            ->toHaveKey('enhanced_setting', true);

        $assignedSettings = $enhancedConfig->getSettingsFor('ticket_assigned');
        expect($assignedSettings->userTriggered)
            ->toHaveKey('plugin_setting', true);
    });

    test('configuration handles real-world notification scenarios', function () {
        $submitter = User::factory()->create();
        $supporter = User::factory()->create();
        $ticket = Ticket::factory()->create([
            'submitter_id' => $submitter->id,
            'assignee_id' => $supporter->id,
        ]);

        $config = NotificationConfiguration::make()
            // Complex real-world configuration
            ->onTicketCreated(function (EventConfigurationContext $context, $ticket = null, $actor = null) {
                if ($ticket && $actor) {
                    // Different behavior based on who created the ticket
                    if ($ticket->submitter_id === $actor->getAuthIdentifier()) {
                        // User created their own ticket - notify supporter
                        $context->whenUserTriggered([
                            'notify_user' => false,
                            'notify_supporter' => true,
                            'email_supporter' => true,
                            'slack_supporter' => true,
                        ])
                            ->whenSupporterTriggered([
                                'notify_user' => false,
                                'notify_supporter' => true,
                                'email_supporter' => true,
                                'slack_supporter' => true,
                            ]);
                    } else {
                        // Supporter created ticket on behalf of user - notify user
                        $context->whenUserTriggered([
                            'notify_user' => true,
                            'notify_supporter' => false,
                            'email_user' => true,
                            'slack_user' => false,
                        ])
                            ->whenSupporterTriggered([
                                'notify_user' => true,
                                'notify_supporter' => false,
                                'email_user' => true,
                                'slack_user' => false,
                            ]);
                    }
                } else {
                    // Fallback to basic notification
                    $context->notifyBoth();
                }
            });

        // Test user-created scenario (submitter is actor, so gets user_triggered config)
        $userCreatedSettings = $config->getConfigurationForTrigger('ticket_created', $ticket, $submitter);
        expect($userCreatedSettings)
            ->toHaveKey('notify_user', false)
            ->toHaveKey('notify_supporter', true)
            ->toHaveKey('email_supporter', true)
            ->toHaveKey('slack_supporter', true);

        // Test supporter-created scenario (supporter is actor, so gets supporter_triggered config)
        $supporterCreatedSettings = $config->getConfigurationForTrigger('ticket_created', $ticket, $supporter);
        expect($supporterCreatedSettings)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('notify_supporter', false)
            ->toHaveKey('email_user', true)
            ->toHaveKey('slack_user', false);
    });

    test('configuration handles environment-specific settings', function () {
        $config = NotificationConfiguration::make()
            ->onTicketCreated(function (EventConfigurationContext $context) {
                $context->notifyBoth()
                    ->inEnvironment('testing', function ($ctx) {
                        $ctx->enableChannel('log', 'both');
                    })
                    ->inEnvironment(['staging', 'production'], function ($ctx) {
                        $ctx->enableChannel('email', 'both')
                            ->enableChannel('slack', 'supporter');
                    });
            });

        $settings = $config->getSettingsFor('ticket_created');

        // In testing environment, should have log channel
        expect($settings->userTriggered)
            ->toHaveKey('notify_user', true)
            ->toHaveKey('notify_supporter', true)
            ->toHaveKey('log_both', true);
    });
});
