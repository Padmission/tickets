<?php

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Padmission\Tickets\ConfigurationManagers\NotificationConfiguration;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\NotificationRecipient;
use Padmission\Tickets\Enums\NotificationStrategy;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Events\TicketClosedEvent;
use Padmission\Tickets\Events\TicketReopenedEvent;
use Padmission\Tickets\Jobs\NotificationJob;
use Padmission\Tickets\Listeners\TicketNotificationListener;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Notifications\TicketNotification;
use Padmission\Tickets\Services\NotificationRecipientService;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    Queue::fake();
    (new TicketStatusSeeder)->run();
    Gate::define('update', fn () => true);
    config()->set('padmission-tickets.default-notification-strategy', NotificationStrategy::Immediate);

    $this->requester = User::factory()->create();
    $this->supporter = User::factory()->create();
});

it('never sends requester notifications when support closes or reopens', function (bool $escalated, string $action) {
    $attributes = ['submitter_id' => $this->requester->id, 'assignee_id' => $this->supporter->id];
    $ticket = $escalated ? escalationFrom(attributes: $attributes) : Ticket::factory()->open()->create($attributes);
    $original = $escalated ? Ticket::factory()->open()->create(['linked_ticket_id' => $ticket->id]) : null;

    $this->actingAs($this->supporter);
    if ($action === 'reopen') {
        $ticket->close(closedById: $this->supporter->id);
    }
    Queue::fake();
    $ticket->{$action}();

    Queue::assertNotPushed(NotificationJob::class, fn (NotificationJob $job): bool => $job->getUserId() === $this->requester->id && in_array($job->notificationType, ['closed', 'reopened'], true));
    // A status activity job may be queued, but requester mail excludes those notes.
    foreach (Queue::pushed(NotificationJob::class, fn (NotificationJob $job): bool => $job->getUserId() === $this->requester->id) as $job) {
        expect((new TicketNotification($ticket->refresh(), $job->event))->shouldSend($this->requester))->toBeFalse();
    }
    $event = $action === 'close' ? new TicketClosedEvent($ticket, $this->supporter) : new TicketReopenedEvent($ticket, $this->supporter);
    expect((new TicketNotification($ticket, $event))->shouldSend($this->requester))->toBeFalse();
    if ($original !== null) {
        expect($original->refresh()->isOpen)->toBeTrue()
            ->and($original->linked_ticket_id)->toBe($ticket->id);
        Queue::assertNotPushed(NotificationJob::class, fn (NotificationJob $job): bool => $job->getUserId() === $original->submitter_id);
    }
})->with([false, true])->with(['close', 'reopen']);

it('notifies the supporter when the requester closes or reopens', function (bool $escalated, string $action) {
    $attributes = ['submitter_id' => $this->requester->id, 'assignee_id' => $this->supporter->id];
    $ticket = $escalated ? escalationFrom(attributes: $attributes) : Ticket::factory()->open()->create($attributes);
    if ($action === 'reopen') {
        $ticket->close(closedById: $this->supporter->id);
    }
    $this->actingAs($this->requester);
    Queue::fake();
    $ticket->{$action}();

    $type = $action === 'close' ? 'closed' : 'reopened';
    Queue::assertPushed(NotificationJob::class, fn (NotificationJob $job): bool => $job->getUserId() === $this->supporter->id && $job->notificationType === $type);
    Queue::assertNotPushed(NotificationJob::class, fn (NotificationJob $job): bool => $job->getUserId() === $this->requester->id);
    $event = $action === 'close' ? new TicketClosedEvent($ticket, $this->requester) : new TicketReopenedEvent($ticket, $this->requester);
    expect((new TicketNotification($ticket->refresh(), $event))->shouldSend($this->supporter))->toBeTrue();
})->with([false, true])->with(['close', 'reopen']);

it('excludes the escalation submitter from both assigned and fallback supporters', function (bool $assigned) {
    $ticket = escalationFrom(attributes: ['submitter_id' => $this->requester->id, 'assignee_id' => $assigned ? $this->requester->id : null]);
    TicketPlugin::get('test2')->allSupportersQuery(fn () => User::query());
    $service = app(NotificationRecipientService::class);

    foreach ([new TicketClosedEvent($ticket, $this->requester), new TicketReopenedEvent($ticket, $this->supporter)] as $event) {
        expect($service->getNotificationRecipients($event)->pluck('id')->all())->not->toContain($this->requester->id);
    }
})->with([false, true]);

it('keeps close and reopen activity notes out of requester notifications and mail', function (ActivityType $type) {
    $ticket = Ticket::factory()->open()->create(['submitter_id' => $this->requester->id, 'assignee_id' => $this->supporter->id]);
    $this->actingAs($this->supporter);
    $ticket->addTicketActivity($type, userId: $this->supporter->id);
    $event = new TicketActivityEvent($ticket, $type, actor: $this->supporter);

    expect(app(NotificationRecipientService::class)->getNotificationRecipients($event))->toBeEmpty()
        ->and((new TicketNotification($ticket, $event))->shouldSend($this->requester))->toBeFalse()
        ->and($ticket->ticketActivities()->where('type', $type)->exists())->toBeTrue();
})->with([ActivityType::Closed, ActivityType::Reopened]);

it('preserves host opt in to requester close notifications and the close template', function () {
    TicketPlugin::get()->notificationConfiguration(NotificationConfiguration::make()->on(TicketClosedEvent::class, fn () => NotificationRecipient::User));
    $ticket = Ticket::factory()->open()->create(['submitter_id' => $this->requester->id, 'assignee_id' => $this->supporter->id]);
    $event = new TicketClosedEvent($ticket, $this->supporter);
    Queue::fake();
    app(TicketNotificationListener::class)->handle($event);

    Queue::assertPushed(NotificationJob::class, fn (NotificationJob $job): bool => $job->getUserId() === $this->requester->id);
    $notification = new TicketNotification($ticket, $event);
    expect($notification->shouldSend($this->requester))->toBeTrue()
        ->and($notification->toMail($this->requester)->markdown)->toBe('padmission-tickets::mails.ticket-closed');
});

it('never resolves a requester for reopen even when configuration selects both sides', function () {
    TicketPlugin::get()->notificationConfiguration(NotificationConfiguration::make()->on(TicketReopenedEvent::class, fn () => NotificationRecipient::Both));
    $ticket = escalationFrom(attributes: ['submitter_id' => $this->requester->id, 'assignee_id' => $this->supporter->id]);
    $event = new TicketReopenedEvent($ticket, $this->supporter);

    expect(app(NotificationRecipientService::class)->getNotificationRecipients($event)->pluck('id')->all())->toBe([$this->supporter->id]);
});
