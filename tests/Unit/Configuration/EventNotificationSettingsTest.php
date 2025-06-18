<?php

use Padmission\Tickets\Configuration\Data\EventNotificationSettings;

test('can create event notification settings with arrays', function () {
    $settings = new EventNotificationSettings(
        userTriggered: ['notify_user' => true, 'email_user' => true],
        supporterTriggered: ['notify_supporter' => true, 'slack_supporter' => true]
    );

    expect($settings->userTriggered)
        ->toHaveKey('notify_user', true)
        ->toHaveKey('email_user', true);

    expect($settings->supporterTriggered)
        ->toHaveKey('notify_supporter', true)
        ->toHaveKey('slack_supporter', true);
});

test('can get settings for trigger type', function () {
    $settings = new EventNotificationSettings(
        userTriggered: ['notify_user' => true],
        supporterTriggered: ['notify_supporter' => true]
    );

    expect($settings->getSettingsFor('user_triggered'))
        ->toBe(['notify_user' => true]);

    expect($settings->getSettingsFor('supporter_triggered'))
        ->toBe(['notify_supporter' => true]);

    expect($settings->getSettingsFor('invalid'))
        ->toBe([]);
});

test('can merge event notification settings', function () {
    $settings1 = new EventNotificationSettings(
        userTriggered: ['notify_user' => true],
        supporterTriggered: ['notify_supporter' => false]
    );

    $settings2 = new EventNotificationSettings(
        userTriggered: ['email_user' => true],
        supporterTriggered: ['slack_supporter' => true]
    );

    $merged = $settings1->merge($settings2);

    expect($merged->userTriggered)
        ->toHaveKey('notify_user', true)
        ->toHaveKey('email_user', true);

    expect($merged->supporterTriggered)
        ->toHaveKey('notify_supporter', false)
        ->toHaveKey('slack_supporter', true);
});

test('shouldNotify() works with legacy boolean format', function () {
    $settings = new EventNotificationSettings(
        userTriggered: ['notify_user' => true, 'notify_supporter' => false],
        supporterTriggered: ['notify_user' => false, 'notify_supporter' => true]
    );

    expect($settings->shouldNotify('user_triggered', 'user'))->toBeTrue();
    expect($settings->shouldNotify('user_triggered', 'supporter'))->toBeFalse();
    expect($settings->shouldNotify('supporter_triggered', 'user'))->toBeFalse();
    expect($settings->shouldNotify('supporter_triggered', 'supporter'))->toBeTrue();
});

test('shouldNotify() works with channel format', function () {
    $settings = new EventNotificationSettings(
        userTriggered: [
            'email_user' => true,
            'slack_user' => false,
            'sms_both' => true,
        ]
    );

    // Should return true if any channel is enabled for the recipient
    expect($settings->shouldNotify('user_triggered', 'user'))->toBeTrue();
});

test('getChannelsFor() returns enabled channels for recipient', function () {
    $settings = new EventNotificationSettings(
        userTriggered: [
            'email_user' => true,
            'slack_supporter' => true,
            'sms_both' => false,
            'webhook_both' => true,
        ]
    );

    $userChannels = $settings->getChannelsFor('user_triggered', 'user');
    expect($userChannels)
        ->toHaveKey('email', true)
        ->toHaveKey('webhook', true)
        ->not->toHaveKey('slack')
        ->not->toHaveKey('sms');

    $supporterChannels = $settings->getChannelsFor('user_triggered', 'supporter');
    expect($supporterChannels)
        ->toHaveKey('slack', true)
        ->toHaveKey('webhook', true)
        ->not->toHaveKey('email')
        ->not->toHaveKey('sms');
});

test('isChannelEnabledFor() checks specific channel for recipient', function () {
    $settings = new EventNotificationSettings(
        userTriggered: [
            'email_user' => true,
            'slack_supporter' => true,
            'sms_both' => false,
        ]
    );

    expect($settings->isChannelEnabledFor('user_triggered', 'user', 'email'))->toBeTrue();
    expect($settings->isChannelEnabledFor('user_triggered', 'user', 'slack'))->toBeFalse();
    expect($settings->isChannelEnabledFor('user_triggered', 'supporter', 'slack'))->toBeTrue();
    expect($settings->isChannelEnabledFor('user_triggered', 'supporter', 'email'))->toBeFalse();
    expect($settings->isChannelEnabledFor('user_triggered', 'user', 'sms'))->toBeFalse();
    expect($settings->isChannelEnabledFor('user_triggered', 'supporter', 'sms'))->toBeFalse();
});

test('toArray() returns proper array structure', function () {
    $settings = new EventNotificationSettings(
        userTriggered: ['notify_user' => true],
        supporterTriggered: ['notify_supporter' => true]
    );

    $array = $settings->toArray();

    expect($array)
        ->toHaveKey('user_triggered', ['notify_user' => true])
        ->toHaveKey('supporter_triggered', ['notify_supporter' => true]);
});

test('handles mixed legacy and channel format', function () {
    $settings = new EventNotificationSettings(
        userTriggered: [
            'notify_user' => true,          // Legacy format
            'notify_supporter' => false,    // Legacy format
            'email_user' => true,           // Channel format
            'slack_supporter' => true,      // Channel format
        ]
    );

    // Legacy format should still work
    expect($settings->shouldNotify('user_triggered', 'user'))->toBeTrue();
    expect($settings->shouldNotify('user_triggered', 'supporter'))->toBeFalse();

    // Channel format should also work
    expect($settings->isChannelEnabledFor('user_triggered', 'user', 'email'))->toBeTrue();
    expect($settings->isChannelEnabledFor('user_triggered', 'supporter', 'slack'))->toBeTrue();
});
