<?php

use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\NotificationStrategy;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Services\NotificationRecipientService;
use Padmission\Tickets\Tests\User;

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

    $event = new TicketActivityEvent($ticket, ActivityType::Message);
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

test('user notification strategy defaults to debounced', function () {
    $recipientService = app(NotificationRecipientService::class);
    $user = User::factory()->create();

    expect($recipientService->getUserNotificationStrategy($user))->toBe(NotificationStrategy::Debounced);
});
