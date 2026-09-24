<?php

use Filament\Facades\Filament;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\NotificationStrategy;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Events\TicketCreatedEvent;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Services\NotificationRecipientService;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    TicketStatus::factory()->count(2)->create();
    TicketPriority::factory()->count(2)->create();
});

test('notification recipients are correctly identified', function () {
    $assignee = User::factory()->create();
    $submitter = User::factory()->create();
    $ticket = Ticket::factory()->open()->create([
        'assignee_id' => $assignee->id,
        'submitter_id' => $submitter->id,
    ]);

    Gate::define('update', fn () => true);

    $event = new TicketCreatedEvent($ticket, $assignee);
    $recipientService = app(NotificationRecipientService::class);

    $recipients = $recipientService->getNotificationRecipients($event);

    expect($recipients)->toHaveCount(2);

    // Check that both users are in the recipients by ID
    $recipientIds = $recipients->pluck('id')->toArray();
    expect($recipientIds)->toContain($assignee->id)
        ->and($recipientIds)->toContain($submitter->id);
});

test('duplicate recipients are filtered out', function () {
    $user = User::factory()->create();
    $ticket = Ticket::factory()->open()->create([
        'assignee_id' => $user->id,
        'submitter_id' => $user->id, // Same user as assignee and submitter
    ]);

    $event = new TicketActivityEvent($ticket, ActivityType::Message);
    $recipientService = app(NotificationRecipientService::class);

    $recipients = $recipientService->getNotificationRecipients($event);

    expect($recipients)->toHaveCount(1);

    // Check that the user is in the recipients by ID
    $recipientIds = $recipients->pluck('id')->toArray();
    expect($recipientIds)->toContain($user->id);
});

test('all supporters are notified when a user-triggered event targets supporters and the ticket has no assignee', function () {
    $submitter = User::factory()->create();
    $supporter1 = User::factory()->create();
    $supporter2 = User::factory()->create();
    $ticket = Ticket::factory()->open()->create([
        'assignee_id' => null,
        'submitter_id' => $submitter->id,
    ]);

    $event = new TicketActivityEvent($ticket, ActivityType::Message, actor: $submitter);
    $recipientService = app(NotificationRecipientService::class);

    $recipients = $recipientService->getNotificationRecipients($event);

    $recipientIds = $recipients->pluck('id')->toArray();
    expect($recipientIds)->toContain($supporter1->id)
        ->and($recipientIds)->toContain($supporter2->id)
        ->and($recipientIds)->not->toContain($submitter->id);
});

test('only the assignee is notified when the ticket has an assignee', function () {
    $submitter = User::factory()->create();
    $assignee = User::factory()->create();
    User::factory()->create();
    $ticket = Ticket::factory()->open()->create([
        'assignee_id' => $assignee->id,
        'submitter_id' => $submitter->id,
    ]);

    $event = new TicketActivityEvent($ticket, ActivityType::Message, actor: $submitter);
    $recipientService = app(NotificationRecipientService::class);

    $recipients = $recipientService->getNotificationRecipients($event);

    expect($recipients->pluck('id')->toArray())->toBe([$assignee->id]);
});

test('fallback supporters are resolved from the ticket panel plugin, not the current panel', function () {
    $submitter = User::factory()->create();
    $panelSupporter = User::factory()->create();
    User::factory()->create();

    Filament::getPanel('test2')->plugin(
        TicketPlugin::make()
            ->allSupportersQuery(fn () => User::query()->whereKey($panelSupporter->id))
            ->registerResources()
    );

    $ticket = Ticket::factory()->open()->create([
        'assignee_id' => null,
        'submitter_id' => $submitter->id,
        'panel' => 'test2',
    ]);

    expect(Filament::getCurrentOrDefaultPanel()->getId())->toBe('test');

    $event = new TicketActivityEvent($ticket, ActivityType::Message, actor: $submitter);
    $recipients = app(NotificationRecipientService::class)->getNotificationRecipients($event);

    expect($recipients->pluck('id')->toArray())->toBe([$panelSupporter->id]);
});

test('fallback supporters closure receives the ticket when it declares a ticket parameter', function () {
    $submitter = User::factory()->create();
    $supporter = User::factory()->create();
    $receivedTicket = null;

    Filament::getPanel('test')->plugin(
        TicketPlugin::make()
            ->allSupportersQuery(function (Ticket $ticket) use (&$receivedTicket) {
                $receivedTicket = $ticket;

                return User::query();
            })
            ->registerResources()
    );

    $ticket = Ticket::factory()->open()->create([
        'assignee_id' => null,
        'submitter_id' => $submitter->id,
    ]);

    $event = new TicketActivityEvent($ticket, ActivityType::Message, actor: $submitter);
    $recipients = app(NotificationRecipientService::class)->getNotificationRecipients($event);

    expect($receivedTicket?->getKey())->toBe($ticket->getKey())
        ->and($recipients->pluck('id')->toArray())->toContain($supporter->id);
});

test('fallback excludes the ticket submitter even when the submitter is not the actor', function () {
    $submitter = User::factory()->create();
    $supporter = User::factory()->create();
    $ticket = Ticket::factory()->open()->create([
        'assignee_id' => null,
        'submitter_id' => $submitter->id,
    ]);

    $event = new TicketActivityEvent($ticket, ActivityType::Message, actor: null);
    $recipients = app(NotificationRecipientService::class)->getNotificationRecipients($event);

    $recipientIds = $recipients->pluck('id')->toArray();
    expect($recipientIds)->toContain($supporter->id)
        ->and($recipientIds)->not->toContain($submitter->id);
});

test('user notification strategy defaults to debounced', function () {
    $recipientService = app(NotificationRecipientService::class);
    $user = User::factory()->create();

    expect($recipientService->getUserNotificationStrategy($user))->toBe(NotificationStrategy::Debounced);
});

test('the assignee is resolved through the ticket panel relationship scopes, not the current panel', function () {
    $submitter = User::factory()->create();
    $assignee = User::factory()->create();
    User::factory()->create();

    // Stands in for a host scope that hides the assignee from the acting
    // panel, such as a tenant scope when a ticket is linked into a
    // cross-tenant panel whose relationship modifier lifts it.
    User::addGlobalScope('acting-tenant', fn ($query) => $query->whereKeyNot($assignee->id));

    Filament::getPanel('test2')->plugin(
        TicketPlugin::make()
            ->allSupportersQuery(fn () => User::query())
            ->modifyRelationshipScopes(fn ($relation) => $relation->withoutGlobalScope('acting-tenant'))
            ->registerResources()
    );

    $ticket = Ticket::factory()->open()->create([
        'assignee_id' => $assignee->id,
        'submitter_id' => $submitter->id,
        'panel' => 'test2',
    ]);

    $event = new TicketActivityEvent($ticket, ActivityType::Message, actor: $submitter);
    $recipients = app(NotificationRecipientService::class)->getNotificationRecipients($event);

    expect($recipients->pluck('id')->toArray())->toBe([$assignee->id]);
})->after(fn () => User::clearBootedModels());
