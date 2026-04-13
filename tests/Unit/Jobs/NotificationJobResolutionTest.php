<?php

use Padmission\Tickets\Events\TicketCreatedEvent;
use Padmission\Tickets\Jobs\NotificationJob;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\User;
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
    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();
    $event = new TicketCreatedEvent($ticket);

    $job = new NotificationJob($user, $ticket, $event);

    // Test that protected methods exist and are accessible to child classes
    $reflection = new ReflectionClass($job);

    expect($reflection->hasMethod('resolveUser'))->toBeTrue();
    expect($reflection->hasMethod('resolveModel'))->toBeTrue();
    expect($reflection->hasMethod('sendNotification'))->toBeTrue();
    expect($reflection->hasMethod('getNotificationClass'))->toBeTrue();
});

test('notification job generates unique id for debouncing', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->create();
    $event = new TicketCreatedEvent($ticket);

    $job = new NotificationJob($user, $ticket, $event);

    $uniqueId = $job->uniqueId();

    // Should contain ticket class, ticket key, and user id
    expect($uniqueId)->toContain('notification-');
    expect($uniqueId)->toContain((string) $ticket->getKey());
    expect($uniqueId)->toContain((string) $user->getKey());
});
