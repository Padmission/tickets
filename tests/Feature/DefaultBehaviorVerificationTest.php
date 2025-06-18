<?php

use Padmission\Tickets\ConfigurationManagers\NotificationConfiguration;

describe('Default Behavior Verification (DBV)', function () {

    test('FR4.1: Ticket Created defaults match requirements', function () {
        $config = NotificationConfiguration::make();
        $settings = $config->getSettingsFor('ticket_created');

        // FR4.1: Ticket Created
        // User-triggered: notify_user: true, notify_supporter: false
        $userTriggered = $settings->getSettingsFor('user_triggered');
        expect($userTriggered['notify_user'] ?? false)->toBe(true);
        expect($userTriggered['notify_supporter'] ?? false)->toBe(false);

        // Supporter-triggered: notify_user: true, notify_supporter: true
        $supporterTriggered = $settings->getSettingsFor('supporter_triggered');
        expect($supporterTriggered['notify_user'] ?? false)->toBe(true);
        expect($supporterTriggered['notify_supporter'] ?? false)->toBe(true);
    });

    test('FR4.2: Ticket Assigned defaults match requirements', function () {
        $config = NotificationConfiguration::make();
        $settings = $config->getSettingsFor('ticket_assigned');

        // FR4.2: Ticket Assigned
        // User-triggered: notify_user: false, notify_supporter: false
        $userTriggered = $settings->getSettingsFor('user_triggered');
        expect($userTriggered['notify_user'] ?? false)->toBe(false);
        expect($userTriggered['notify_supporter'] ?? false)->toBe(false);

        // Supporter-triggered: notify_user: false, notify_supporter: true
        $supporterTriggered = $settings->getSettingsFor('supporter_triggered');
        expect($supporterTriggered['notify_user'] ?? false)->toBe(false);
        expect($supporterTriggered['notify_supporter'] ?? false)->toBe(true);
    });

    test('FR4.3: Ticket Activity defaults match requirements', function () {
        $config = NotificationConfiguration::make();
        $settings = $config->getSettingsFor('ticket_activity');

        // FR4.3: Ticket Activity
        // User-triggered: notify_user: false, notify_supporter: true
        $userTriggered = $settings->getSettingsFor('user_triggered');
        expect($userTriggered['notify_user'] ?? false)->toBe(false);
        expect($userTriggered['notify_supporter'] ?? false)->toBe(true);

        // Supporter-triggered: notify_user: true, notify_supporter: false
        $supporterTriggered = $settings->getSettingsFor('supporter_triggered');
        expect($supporterTriggered['notify_user'] ?? false)->toBe(true);
        expect($supporterTriggered['notify_supporter'] ?? false)->toBe(false);
    });

    test('FR4.4: Ticket Closed defaults match requirements', function () {
        $config = NotificationConfiguration::make();
        $settings = $config->getSettingsFor('ticket_closed');

        // FR4.4: Ticket Closed
        // User-triggered: notify_user: true, notify_supporter: false
        $userTriggered = $settings->getSettingsFor('user_triggered');
        expect($userTriggered['notify_user'] ?? false)->toBe(true);
        expect($userTriggered['notify_supporter'] ?? false)->toBe(false);

        // Supporter-triggered: notify_user: true, notify_supporter: false
        $supporterTriggered = $settings->getSettingsFor('supporter_triggered');
        expect($supporterTriggered['notify_user'] ?? false)->toBe(true);
        expect($supporterTriggered['notify_supporter'] ?? false)->toBe(false);
    });

    test('all default configurations are valid and complete', function () {
        $config = NotificationConfiguration::make();
        $events = ['ticket_created', 'ticket_assigned', 'ticket_activity', 'ticket_closed'];
        $triggers = ['user_triggered', 'supporter_triggered'];

        foreach ($events as $event) {
            $settings = $config->getSettingsFor($event);

            foreach ($triggers as $trigger) {
                $configuration = $settings->getSettingsFor($trigger);

                // Verify configuration has expected structure
                expect($configuration)->toBeArray();
                expect($configuration)->toHaveKeys(['notify_user', 'notify_supporter']);

                // Verify values are boolean
                expect($configuration['notify_user'])->toBeIn([true, false]);
                expect($configuration['notify_supporter'])->toBeIn([true, false]);
            }
        }
    });
});
