<?php

use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\NotificationStrategy;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Events\TicketAssignedEvent;
use Padmission\Tickets\Jobs\NotificationJob;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Notifications\TicketNotification;
use Padmission\Tickets\Services\TicketActivityService;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    Queue::fake();
    (new TicketStatusSeeder)->run();
    config()->set('padmission-tickets.default-notification-strategy', NotificationStrategy::Immediate);
    $this->requester = User::factory()->create(['name' => 'Organization Support']);
    $this->previous = User::factory()->create(['name' => 'Tess Support']);
    $this->assignee = User::factory()->create(['name' => 'Maria Lopez']);
});

function assignmentHistory(Ticket $ticket, User $viewer): array
{
    return app(TicketActivityService::class)->getActivities($ticket, user: $viewer)
        ->where('type', ActivityType::AssigneeChanged)->pluck('content')->values()->all();
}

it('shows the requester who now handles their ticket', function (bool $escalated) {
    $attributes = ['submitter_id' => $this->requester->id, 'assignee_id' => $this->previous->id];
    $ticket = $escalated ? escalationFrom(attributes: $attributes) : Ticket::factory()->create($attributes);
    $this->actingAs($this->previous);
    $ticket->update(['assignee_id' => $this->assignee->id]);
    $this->actingAs($this->requester);

    expect(assignmentHistory($ticket, $this->requester))->toBe(['Maria Lopez is now handling your ticket.']);
    $this->getJson(route('padmission-tickets::api.messages.index', ['ticket' => $ticket]))
        ->assertOk()->assertJsonFragment(['content' => 'Maria Lopez is now handling your ticket.']);
})->with([false, true]);

it('keeps the staff note while giving the requester their own wording', function () {
    $ticket = Ticket::factory()->create(['submitter_id' => $this->requester->id, 'assignee_id' => $this->previous->id]);
    $this->actingAs($this->previous);
    $ticket->update(['assignee_id' => $this->assignee->id]);

    expect(assignmentHistory($ticket, $this->previous))->toBe(['You handed this ticket to Maria Lopez']);
    $this->actingAs($this->requester);
    expect(assignmentHistory($ticket, $this->requester))->toBe(['Maria Lopez is now handling your ticket.']);
});

it('shows neutral requester wording when the ticket becomes unassigned', function (bool $escalated) {
    $attributes = ['submitter_id' => $this->requester->id, 'assignee_id' => $this->previous->id];
    $ticket = $escalated ? escalationFrom(attributes: $attributes) : Ticket::factory()->create($attributes);
    $this->actingAs($this->previous);
    $ticket->update(['assignee_id' => null]);

    expect(assignmentHistory($ticket, $this->previous))->toBe(['Unassigned']);
    $this->actingAs($this->requester);
    expect(assignmentHistory($ticket, $this->requester))->toBe(['Your ticket is waiting to be assigned.']);
})->with([false, true]);

it('never queues or sends requester notifications for an assignee change', function (bool $escalated, string $assignment) {
    $attributes = ['submitter_id' => $this->requester->id, 'assignee_id' => $this->previous->id];
    $ticket = $escalated ? escalationFrom(attributes: $attributes) : Ticket::factory()->create($attributes);
    $this->actingAs($this->previous);
    Queue::fake();
    Notification::fake();
    TicketPlugin::get()->allSupportersQuery(fn () => User::query());
    $to = match ($assignment) {
        'unassigned' => null,
        'requester' => $this->requester->id,
        default => $this->assignee->id,
    };
    $ticket->update(['assignee_id' => $to]);

    Queue::assertNotPushed(NotificationJob::class, fn (NotificationJob $job): bool => $job->getUserId() === $this->requester->id);
    if ($assignment === 'staff') {
        Queue::assertPushed(NotificationJob::class, fn (NotificationJob $job): bool => $job->getUserId() === $this->assignee->id && $job->notificationType === 'assigned');
    }
    foreach ([new TicketActivityEvent($ticket, ActivityType::AssigneeChanged, actor: $this->previous), new TicketAssignedEvent($ticket, $this->previous)] as $event) {
        // A pending job must also remain silent now that the history entry is visible.
        expect((new TicketNotification($ticket, $event))->shouldSend($this->requester))->toBeFalse();
        (new NotificationJob($this->requester, $ticket, $event))->handle();
    }
    Notification::assertNothingSentTo($this->requester);
    $mail = (new TicketNotification($ticket, new TicketActivityEvent($ticket, ActivityType::Message)))->toMail($this->requester);
    expect($mail->viewData['activities']->pluck('type')->all())->not->toContain(ActivityType::AssigneeChanged);
})->with([false, true])->with(['staff', 'unassigned', 'requester']);
