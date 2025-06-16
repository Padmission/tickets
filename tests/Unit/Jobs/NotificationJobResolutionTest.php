<?php

use Padmission\Tickets\Jobs\NotificationJob;
use Padmission\Tickets\TicketPlugin;

test('it resolves default notification job class', function () {
    $resolvedClass = TicketPlugin::resolveJobClass(NotificationJob::class);

    expect($resolvedClass)->toBe(NotificationJob::class);
});

test('it resolves custom notification job class when configured', function () {
    // Mock custom job class
    $customJobClass = 'App\\Jobs\\CustomNotificationJob';

    // Temporarily set config
    config(['padmission-tickets.jobs' => [
        NotificationJob::class => $customJobClass,
    ]]);

    $resolvedClass = TicketPlugin::resolveJobClass(NotificationJob::class);

    expect($resolvedClass)->toBe($customJobClass);

    config(['padmission-tickets.jobs' => [
        NotificationJob::class => NotificationJob::class,
    ]]);
});

test('notification job has extensible methods', function () {
    $job = new NotificationJob(
        \Padmission\Tickets\Tests\User::factory()->create(),
        \Padmission\Tickets\Models\Ticket::factory()->create(),
        'test'
    );

    // Test that protected methods exist and are accessible to child classes
    $reflection = new ReflectionClass($job);

    expect($reflection->hasMethod('initializeJob'))->toBeTrue();
    expect($reflection->hasMethod('buildUniqueId'))->toBeTrue();
    expect($reflection->hasMethod('resolveUser'))->toBeTrue();
    expect($reflection->hasMethod('resolveModel'))->toBeTrue();
    expect($reflection->hasMethod('sendNotification'))->toBeTrue();
    expect($reflection->hasMethod('handleException'))->toBeTrue();

    // Test getter methods
    expect($reflection->hasMethod('getUserId'))->toBeTrue();
    expect($reflection->hasMethod('getTicketClass'))->toBeTrue();
    expect($reflection->hasMethod('getTicketKey'))->toBeTrue();
});
