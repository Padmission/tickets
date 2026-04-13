<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Padmission\Tickets\ConfigurationManagers\NotificationConfiguration;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\NotificationRecipient;
use Padmission\Tickets\Enums\NotificationStrategy;
use Padmission\Tickets\Enums\NotificationTrigger;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Events\TicketAssignedEvent;
use Padmission\Tickets\Events\TicketCreatedEvent;
use Padmission\Tickets\Jobs\NotificationJob;
use Padmission\Tickets\Listeners\TicketNotificationListener;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Services\NotificationRecipientService;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    Queue::fake();

    $this->submitter = User::factory()->create(['name' => 'Submitter']);
    $this->supporter = User::factory()->create(['name' => 'Supporter']);

    TicketStatus::factory()->create([
        'display_name' => 'Open',
        'order' => 1,
        'panel' => 'test',
    ]);

    TicketPriority::factory()->create([
        'display_name' => 'Normal',
        'order' => 1,
        'panel' => 'test',
    ]);

    Gate::define('update', function ($user, $ticket) {
        return $user->id === $this->supporter->id;
    });
});

function createListener()
{
    $recipientService = Mockery::mock(NotificationRecipientService::class)->makePartial();
    $recipientService->shouldReceive('getUserNotificationStrategy')
        ->andReturn(NotificationStrategy::Immediate);

    return new TicketNotificationListener($recipientService);
}

test('ticket creation with default configuration sends notifications correctly', function () {
    $this->modifyPlugin(
        fn (TicketPlugin $plugin) => $plugin->notificationConfiguration(NotificationConfiguration::make())
    );

    $listener = createListener();

    $this->actingAs($this->submitter);
    $ticket = Ticket::factory()->create([
        'submitter_id' => $this->submitter->id,
        'assignee_id' => $this->supporter->id,
    ]);

    $event = new TicketCreatedEvent($ticket, $this->submitter);
    $listener->handle($event);

    Queue::assertPushed(NotificationJob::class, function ($job) {
        return $job->getUserId() === $this->submitter->id &&
               $job->notificationType === 'created';
    });

    Queue::assertPushed(NotificationJob::class, 1);

    Queue::fake();

    $this->actingAs($this->supporter);
    $ticket2 = Ticket::factory()->create([
        'submitter_id' => $this->submitter->id,
        'assignee_id' => $this->supporter->id,
    ]);

    $event2 = new TicketCreatedEvent($ticket2, $this->supporter);
    $listener->handle($event2);

    Queue::assertPushed(NotificationJob::class, function ($job) {
        return $job->getUserId() === $this->submitter->id;
    });

    Queue::assertPushed(NotificationJob::class, function ($job) {
        return $job->getUserId() === $this->supporter->id;
    });

    Queue::assertPushed(NotificationJob::class, 2);
});

test('ticket activity with default configuration sends notifications correctly', function () {
    $this->modifyPlugin(
        fn (TicketPlugin $plugin) => $plugin->notificationConfiguration(NotificationConfiguration::make())
    );

    $listener = createListener();

    $ticket = Ticket::factory()->create([
        'submitter_id' => $this->submitter->id,
        'assignee_id' => $this->supporter->id,
    ]);

    $this->actingAs($this->submitter);
    $event = new TicketActivityEvent($ticket, ActivityType::Message, null, $this->submitter);
    $listener->handle($event);

    Queue::assertPushed(NotificationJob::class, function ($job) {
        return $job->getUserId() === $this->supporter->id &&
               $job->notificationType === 'activity';
    });

    Queue::assertPushed(NotificationJob::class, 1);

    Queue::fake();

    $this->actingAs($this->supporter);
    $event2 = new TicketActivityEvent($ticket, ActivityType::Message, null, $this->supporter);
    $listener->handle($event2);

    Queue::assertPushed(NotificationJob::class, function ($job) {
        return $job->getUserId() === $this->submitter->id;
    });

    Queue::assertPushed(NotificationJob::class, 1);
});

test('ticket assignment with default configuration sends notifications correctly', function () {
    $this->modifyPlugin(
        fn (TicketPlugin $plugin) => $plugin->notificationConfiguration(NotificationConfiguration::make())
    );

    $listener = createListener();

    $ticket = Ticket::factory()->create([
        'submitter_id' => $this->submitter->id,
        'assignee_id' => $this->supporter->id,
    ]);

    $this->actingAs($this->supporter);
    $event = new TicketAssignedEvent($ticket, $this->supporter);
    $listener->handle($event);

    Queue::assertPushed(NotificationJob::class, function ($job) {
        return $job->getUserId() === $this->supporter->id &&
               $job->notificationType === 'assigned';
    });

    Queue::assertPushed(NotificationJob::class, 1);
});

test('custom notification configuration overrides defaults correctly', function () {
    $customConfig = NotificationConfiguration::make()
        ->on(
            TicketCreatedEvent::class,
            fn (NotificationTrigger $trigger) => match ($trigger) {
                NotificationTrigger::User => NotificationRecipient::Supporter,
                default => NotificationRecipient::None
            });

    $plugin = TicketPlugin::get();
    $plugin->notificationConfiguration($customConfig);

    expect($plugin->getNotificationConfiguration())
        ->getConfigurationFor(
            TicketCreatedEvent::class,
            NotificationTrigger::User
        )
        ->toBe(NotificationRecipient::Supporter);

    $listener = createListener();

    $ticket = Ticket::factory()->create([
        'submitter_id' => $this->submitter->id,
        'assignee_id' => $this->supporter->id,
    ]);

    $this->actingAs($this->submitter);
    $event = new TicketCreatedEvent($ticket, $this->submitter);

    $recipientService = new NotificationRecipientService;
    $recipients = $recipientService->getNotificationRecipients($event);
    expect($recipients)->toHaveCount(1);
    expect($recipients->first()->id)->toBe($this->supporter->id);

    $listener->handle($event);

    Queue::assertPushed(NotificationJob::class, function ($job) {
        return $job->getUserId() === $this->supporter->id &&
               $job->notificationType === 'created';
    });
});

test('no notifications are sent when configuration disables them', function () {
    $plugin = TicketPlugin::get();

    $customConfig = NotificationConfiguration::make()
        ->on(
            TicketCreatedEvent::class,
            fn (NotificationTrigger $trigger) => NotificationRecipient::None
        );

    $plugin->notificationConfiguration($customConfig);

    $listener = createListener();

    $ticket = Ticket::factory()->create([
        'submitter_id' => $this->submitter->id,
        'assignee_id' => $this->supporter->id,
    ]);

    $event = new TicketCreatedEvent($ticket, $this->submitter);
    $listener->handle($event);

    Queue::assertNothingPushed();
});

test('notifications handle missing assignee gracefully', function () {
    $this->modifyPlugin(
        fn (TicketPlugin $plugin) => $plugin->notificationConfiguration(NotificationConfiguration::make())
    );

    $listener = createListener();

    $ticket = Ticket::factory()->create([
        'submitter_id' => $this->submitter->id,
        'assignee_id' => null,
    ]);

    $event = new TicketCreatedEvent($ticket, $this->submitter);
    $listener->handle($event);

    Queue::assertPushed(NotificationJob::class, function ($job) {
        return $job->getUserId() === $this->submitter->id;
    });

    Queue::assertPushed(NotificationJob::class, 1);
});

test('actor determination works correctly without explicit actor', function () {
    $this->modifyPlugin(
        fn (TicketPlugin $plugin) => $plugin->notificationConfiguration(NotificationConfiguration::make())
    );

    $listener = createListener();

    $ticket = Ticket::factory()->create([
        'submitter_id' => $this->submitter->id,
        'assignee_id' => $this->supporter->id,
    ]);

    $event = new TicketActivityEvent($ticket, ActivityType::Message, null, null);
    $listener->handle($event);

    Queue::assertPushed(NotificationJob::class, function ($job) {
        return $job->getUserId() === $this->supporter->id;
    });

    Queue::assertPushed(NotificationJob::class, 1);
});

test('custom notification configuration for activities works correctly', function () {
    $plugin = TicketPlugin::get();

    $customConfig = NotificationConfiguration::make()
        ->on(
            TicketActivityEvent::class,
            fn (NotificationTrigger $trigger) => NotificationRecipient::Both,
        );

    $plugin->notificationConfiguration($customConfig);

    $listener = createListener();

    $ticket = Ticket::factory()->create([
        'submitter_id' => $this->submitter->id,
        'assignee_id' => $this->supporter->id,
    ]);

    $this->actingAs($this->submitter);
    $event = new TicketActivityEvent($ticket, ActivityType::Message, null, $this->submitter);
    $listener->handle($event);

    Queue::assertPushed(NotificationJob::class, function ($job) {
        return $job->getUserId() === $this->submitter->id &&
               $job->notificationType === 'activity';
    });

    Queue::assertPushed(NotificationJob::class, function ($job) {
        return $job->getUserId() === $this->supporter->id &&
               $job->notificationType === 'activity';
    });
});
