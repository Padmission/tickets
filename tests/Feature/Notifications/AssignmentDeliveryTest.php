<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Padmission\Tickets\AssignmentStrategies\AssignDefaultUser;
use Padmission\Tickets\ConfigurationManagers\NotificationConfiguration;
use Padmission\Tickets\Database\Seeders\TicketPrioritySeeder;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\NotificationRecipient;
use Padmission\Tickets\Enums\NotificationStrategy;
use Padmission\Tickets\Events\TicketClosedEvent;
use Padmission\Tickets\Events\TicketCreatedEvent;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CreateLinkedTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\EditTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\HandOverEscalationAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\ReassignTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Notifications\TicketNotification;
use Padmission\Tickets\Policies\TicketPolicy;
use Padmission\Tickets\Services\NotificationRecipientService;
use Padmission\Tickets\Services\TicketEscalationLinks;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

/*
 * Delivered the way a queue worker does it: when due, the job
 * runs with nobody signed in and no current panel. The default panel is still
 * what Filament answers with when nothing is current.
 */
beforeEach(function () {
    Gate::policy(Ticket::class, TicketPolicy::class);
    (new TicketStatusSeeder)->run();
    (new TicketPrioritySeeder)->run();

    config([
        'queue.default' => 'database',
        'padmission-tickets.notification-channels' => ['mail', 'database'],
        'padmission-tickets.notification-debounce' => 60,
        'padmission-tickets.default-notification-strategy' => NotificationStrategy::Debounced,
    ]);

    foreach ([
        'jobs' => function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedSmallInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        },
        'notifications' => function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        },
    ] as $name => $columns) {
        if (! Schema::hasTable($name)) {
            Schema::create($name, $columns);
        }
    }

    $this->requester = User::factory()->create(['name' => 'Aisha Brooks']);
    $this->previous = User::factory()->create(['name' => 'Tess Support']);
    $this->colleague = User::factory()->create(['name' => 'Maria Lopez']);
    $this->padmission = User::factory()->create(['name' => 'Kevin McKee']);
    $this->alessa = User::factory()->create(['name' => 'Alessa Support']);

    TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKey([$this->previous->id, $this->colleague->id]));
    TicketPlugin::get('test2')->supportTeamName('Padmission');
    TicketPlugin::get('test2')->allSupportersQuery(fn () => User::query()->whereKey([$this->padmission->id, $this->alessa->id]));
    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
});

function openAssignedTicket(?User $assignee = null, array $attributes = []): Ticket
{
    return Ticket::factory()->open()->create([
        'subject' => 'Rent is wrong',
        'submitter_id' => test()->requester->id,
        'assignee_id' => $assignee?->id,
        ...$attributes,
    ]);
}

function forgetQueuedNotices(): void
{
    DB::table('jobs')->delete();
    DB::table('notifications')->delete();
    app('mailer')->getSymfonyTransport()->flush();
}

/*
 * The debounced job is let fall due, then run with the panel and the session
 * gone, which is where a worker finds the ticket.
 */
function deliverQueuedNotices(bool $waitForDebounce = true): int
{
    $queued = DB::table('jobs')->count();
    $panel = Filament::getCurrentPanel();
    $signedIn = auth()->user();

    Filament::setCurrentPanel(null);
    auth()->logout();
    if ($waitForDebounce) {
        test()->travel((int) config('padmission-tickets.notification-debounce') + 1)->seconds();
    }

    $connection = app('queue')->connection('database');

    for ($i = 0; $i < 20 && ($job = $connection->pop()); $i++) {
        $job->fire();
    }

    if ($panel !== null) {
        Filament::setCurrentPanel($panel);
    }

    if ($signedIn !== null) {
        test()->actingAs($signedIn);
    }

    return $queued;
}

/**
 * @return array{mail: list<string>, bells: list<string>}
 */
function noticesFor(User $user): array
{
    $mail = app('mailer')->getSymfonyTransport()->messages()
        ->map(fn ($sent) => $sent->getOriginalMessage())
        ->filter(fn ($message): bool => collect($message->getTo())->contains(
            fn ($address): bool => $address->getAddress() === $user->email,
        ))
        ->map(fn ($message): string => (string) $message->getSubject())
        ->values()
        ->all();

    $bells = $user->notifications()->get()
        ->map(fn ($notification): string => (string) ($notification->data['title'] ?? ''))
        ->all();

    return ['mail' => $mail, 'bells' => $bells];
}

function expectTold(User $user, string $phrase, int $queued): void
{
    $notices = noticesFor($user);

    expect($queued)->toBeGreaterThan(0)
        ->and($notices['mail'])->not->toBeEmpty()
        ->and(implode("\n", $notices['mail']))->toContain($phrase)
        ->and($notices['bells'])->not->toBeEmpty()
        ->and(implode("\n", $notices['bells']))->toContain($phrase);
}

function expectUntold(User $user): void
{
    $notices = noticesFor($user);

    expect($notices['mail'])->toBe([])
        ->and($notices['bells'])->toBe([]);
}

/**
 * @return list<string>
 */
function mailHtmlFor(User $user): array
{
    return app('mailer')->getSymfonyTransport()->messages()
        ->map(fn ($sent) => $sent->getOriginalMessage())
        ->filter(fn ($message): bool => collect($message->getTo())->contains(
            fn ($address): bool => $address->getAddress() === $user->email,
        ))
        ->map(fn ($message): string => (string) $message->getHtmlBody())
        ->values()
        ->all();
}

function expectAssigned(User $user, Ticket $ticket, int $queued): void
{
    $subject = "Ticket #{$ticket->id} assigned to you – {$ticket->subject}";

    expectTold($user, $subject, $queued);
    expect(implode("\n", mailHtmlFor($user)))
        ->toContain('Ticket Assigned')
        ->toContain('A ticket has been assigned to you for handling.');
    expect($user->notifications()->get()->pluck('data.body')->all())
        ->toBe(['A ticket has been assigned to you for handling.']);
}

function expectOpened(User $user, Ticket $ticket): void
{
    $notices = noticesFor($user);

    expect(implode("\n", $notices['mail']))->toContain("New ticket #{$ticket->id} – {$ticket->subject}")
        ->and(implode("\n", $notices['mail']))->not->toContain('assigned to you')
        ->and(implode("\n", mailHtmlFor($user)))->toContain('New Ticket');
}

function reassignOnPage(Ticket $ticket, User $by, int|string $assigneeId): void
{
    test()->login($by);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->callAction(TestAction::make(ReassignTicketAction::class)->schemaComponent('assignee', schema: 'form'), [
            'assignee_id' => $assigneeId,
        ])
        ->assertHasNoActionErrors();
}

it('emails and bells the person a Reassign action gives the ticket to, and not the requester or the actor', function () {
    $ticket = openAssignedTicket($this->previous);
    forgetQueuedNotices();

    reassignOnPage($ticket, $this->previous, $this->colleague->id);
    expectUntold($this->colleague);
    $queued = deliverQueuedNotices(waitForDebounce: false);

    expectTold($this->colleague, "Ticket #{$ticket->id} assigned to you", $queued);
    expectUntold($this->requester);
    expectUntold($this->previous);
});

it('sends nothing when Reassign gives the ticket to the person doing it', function () {
    $ticket = openAssignedTicket($this->previous);
    forgetQueuedNotices();

    reassignOnPage($ticket, $this->colleague, $this->colleague->id);

    expect($ticket->refresh()->assignee_id)->toBe($this->colleague->id);
    deliverQueuedNotices();

    expectUntold($this->colleague);
    expectUntold($this->requester);
});

it('emails and bells the person the edit form assigns the ticket to', function () {
    $ticket = openAssignedTicket($this->previous);
    forgetQueuedNotices();
    $this->login($this->previous);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->callAction(EditTicketAction::class, [
            'subject' => $ticket->subject,
            'status_id' => $ticket->status_id,
            'priority_id' => $ticket->priority_id,
            'assignee_id' => $this->colleague->id,
        ])
        ->assertHasNoActionErrors();

    $queued = deliverQueuedNotices(waitForDebounce: false);

    expectTold($this->colleague, "Ticket #{$ticket->id} assigned to you", $queued);
    expectUntold($this->requester);
    expectUntold($this->previous);
});

it('sends nothing when the edit form assigns the ticket to the person editing it', function () {
    $ticket = openAssignedTicket($this->previous);
    forgetQueuedNotices();
    $this->login($this->colleague);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->callAction(EditTicketAction::class, [
            'subject' => $ticket->subject,
            'status_id' => $ticket->status_id,
            'priority_id' => $ticket->priority_id,
            'assignee_id' => $this->colleague->id,
        ])
        ->assertHasNoActionErrors();

    expect($ticket->refresh()->assignee_id)->toBe($this->colleague->id);
    deliverQueuedNotices();

    expectUntold($this->colleague);
    expectUntold($this->requester);
});

it('emails and bells the person a bulk Assign gives the ticket to', function () {
    $ticket = openAssignedTicket($this->previous);
    forgetQueuedNotices();
    $this->login($this->previous);

    Livewire::test(ListTickets::class)
        ->callTableBulkAction('assign', [$ticket], ['assignee_id' => $this->colleague->id])
        ->assertNotified();

    $queued = deliverQueuedNotices(waitForDebounce: false);

    expect($ticket->refresh()->assignee_id)->toBe($this->colleague->id);
    expectTold($this->colleague, "Ticket #{$ticket->id} assigned to you", $queued);
    expectUntold($this->requester);
    expectUntold($this->previous);
});

it('sends nothing when a bulk Assign gives the ticket to the person doing it', function () {
    $ticket = openAssignedTicket($this->colleague);
    forgetQueuedNotices();
    $this->login($this->previous);

    Livewire::test(ListTickets::class, ['activeTab' => 'all'])
        ->callTableBulkAction('assign', [$ticket], ['assignee_id' => $this->previous->id]);

    expect($ticket->refresh()->assignee_id)->toBe($this->previous->id);
    deliverQueuedNotices();

    expectUntold($this->previous);
    expectUntold($this->requester);
});

it('emails and bells the colleague an escalation is handed to, on the organization side', function () {
    $escalation = escalationFrom(attributes: [
        'subject' => 'Rent is wrong',
        'submitter_id' => $this->previous->id,
        'assignee_id' => $this->padmission->id,
    ]);
    $original = openAssignedTicket($this->previous, ['linked_ticket_id' => $escalation->id]);
    forgetQueuedNotices();
    $this->login($this->previous);

    Livewire::test(ViewTicket::class, ['record' => $escalation->id])
        ->callAction(TestAction::make(HandOverEscalationAction::class)->schemaComponent('submitter', schema: 'form'), [
            'new_owner' => $this->colleague->id,
        ])
        ->assertHasNoActionErrors();

    $queued = deliverQueuedNotices(waitForDebounce: false);

    expect($escalation->refresh()->submitter_id)->toBe($this->colleague->id);
    expectTold($this->colleague, "Escalation handed to you #{$escalation->id}", $queued);
    expectUntold($this->requester);
    expectUntold($this->previous);
    expect(implode("\n", noticesFor($this->padmission)['mail']))->not->toContain('handed to you');
    expect($original->refresh()->assignee_id)->toBe($this->previous->id);
});

it('sends the person taking over an escalation nothing, since they took it themselves', function () {
    $escalation = escalationFrom(attributes: [
        'subject' => 'Rent is wrong',
        'submitter_id' => $this->previous->id,
        'assignee_id' => $this->padmission->id,
    ]);
    $original = openAssignedTicket($this->previous, ['linked_ticket_id' => $escalation->id]);
    forgetQueuedNotices();
    $this->login($this->colleague);

    Livewire::test(ViewTicket::class, ['record' => $original->id])
        ->callAction(TestAction::make('take-over-escalation')->schemaComponent('escalationActions', schema: 'form'))
        ->assertHasNoActionErrors();

    expect($escalation->refresh()->submitter_id)->toBe($this->colleague->id);
    deliverQueuedNotices();

    expectUntold($this->colleague);
    expectUntold($this->requester);
});

it('emails and bells the supporter the escalation-target team reassigns to', function () {
    $escalation = escalationFrom(attributes: [
        'subject' => 'Rent is wrong',
        'submitter_id' => $this->previous->id,
        'assignee_id' => $this->padmission->id,
    ]);
    forgetQueuedNotices();
    Filament::setCurrentPanel(Filament::getPanel('test2'));
    $this->login($this->padmission);

    reassignOnPage($escalation, $this->padmission, $this->alessa->id);
    $queued = deliverQueuedNotices(waitForDebounce: false);

    expectTold($this->alessa, "Ticket #{$escalation->id} assigned to you", $queued);
    expectUntold($this->previous);
    expectUntold($this->padmission);
    expectUntold($this->requester);
});

it('sends nothing when an escalation-target supporter reassigns the escalation to themselves', function () {
    $escalation = escalationFrom(attributes: [
        'subject' => 'Rent is wrong',
        'submitter_id' => $this->previous->id,
        'assignee_id' => $this->alessa->id,
    ]);
    forgetQueuedNotices();
    Filament::setCurrentPanel(Filament::getPanel('test2'));

    reassignOnPage($escalation, $this->padmission, $this->padmission->id);

    expect($escalation->refresh()->assignee_id)->toBe($this->padmission->id);
    deliverQueuedNotices();

    expectUntold($this->padmission);
    expectUntold($this->previous);
    expectUntold($this->requester);
});

it('emails and bells whoever auto-assignment gives a new ticket to', function () {
    TicketPlugin::get()->assignmentStrategy(new AssignDefaultUser($this->colleague->id));
    forgetQueuedNotices();
    $this->login($this->requester);

    $ticket = openAssignedTicket();
    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'type' => ActivityType::Message,
        'sender' => ActivitySender::System,
        'user_id' => null,
        'content' => 'We received your request.',
    ]);
    $queued = deliverQueuedNotices(waitForDebounce: false);

    expect($ticket->refresh()->assignee_id)->toBe($this->colleague->id);
    expectAssigned($this->colleague, $ticket, $queued);
    deliverQueuedNotices();
    expectOpened($this->requester, $ticket);
});

it('sends nothing when auto-assignment gives a new ticket to the person who opened it', function () {
    TicketPlugin::get()->assignmentStrategy(new AssignDefaultUser($this->previous->id));
    forgetQueuedNotices();
    $this->login($this->previous);

    $ticket = openAssignedTicket();
    deliverQueuedNotices();

    expect($ticket->refresh()->assignee_id)->toBe($this->previous->id);
    expectUntold($this->previous);
});

it('emails and bells whoever auto-assignment gives a ticket opened through the API', function () {
    TicketPlugin::get()->assignmentStrategy(new AssignDefaultUser($this->colleague->id));
    forgetQueuedNotices();
    $this->login($this->requester);

    $id = $this->postJson(route('padmission-tickets::api.store'), ['subject' => 'Rent is wrong'])
        ->assertOk()
        ->json('id');

    $ticket = Ticket::findOrFail($id);
    $queued = deliverQueuedNotices(waitForDebounce: false);

    expect($ticket->assignee_id)->toBe($this->colleague->id);
    expectAssigned($this->colleague, $ticket, $queued);
    deliverQueuedNotices();
    expectOpened($this->requester, $ticket);
});

it('delivers an unassigned requester creation only to the requester', function () {
    forgetQueuedNotices();
    $this->login($this->requester);

    $ticket = openAssignedTicket();
    deliverQueuedNotices();

    expectOpened($this->requester, $ticket);
    expect($this->requester->notifications()->count())->toBe(1);
    expectUntold($this->previous);
    expectUntold($this->colleague);
});

it('delivers a support-created assignment to the colleague in both channels', function () {
    forgetQueuedNotices();
    $this->login($this->previous);

    $ticket = openAssignedTicket($this->colleague);
    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'type' => ActivityType::Message,
        'sender' => ActivitySender::System,
        'user_id' => null,
        'content' => 'Support opened this ticket.',
    ]);
    $queued = deliverQueuedNotices(waitForDebounce: false);

    expectAssigned($this->colleague, $ticket, $queued);
    deliverQueuedNotices();
    expectOpened($this->requester, $ticket);
    expectUntold($this->previous);
});

it('queues and delivers nobody for a support-only creation self-assignment and does not preview assigned wording', function () {
    TicketPlugin::get()->notificationConfiguration(
        NotificationConfiguration::make()->on(TicketCreatedEvent::class, fn () => NotificationRecipient::Supporter)
    );
    forgetQueuedNotices();
    $this->login($this->previous);

    $ticket = openAssignedTicket($this->previous);
    $event = new TicketCreatedEvent($ticket, $this->previous);
    $notification = new TicketNotification($ticket, $event);

    expect($notification->shouldSend($this->previous))->toBeFalse();
    expect($notification->toMail($this->previous)->viewData['headline'])->toBe('New Ticket');
    expect(app(NotificationRecipientService::class)->getNotificationRecipients($event))->toBeEmpty();
    expect(deliverQueuedNotices())->toBe(0);
    expectUntold($this->previous);
    expectUntold($this->requester);
    expectUntold($this->colleague);
});

it('delivers a new escalation assignment to the target team in both channels', function () {
    forgetQueuedNotices();
    $this->login($this->previous);

    $ticket = escalationFrom(attributes: [
        'submitter_id' => $this->previous->id,
        'assignee_id' => $this->padmission->id,
    ]);
    TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'type' => ActivityType::Message,
        'sender' => ActivitySender::System,
        'user_id' => null,
        'content' => 'An escalation was opened.',
    ]);
    $queued = deliverQueuedNotices(waitForDebounce: false);

    expectAssigned($this->padmission, $ticket, $queued);
    expectUntold($this->previous);
    expectUntold($this->requester);
    expectUntold($this->alessa);
});

it('delivers only the requester acknowledgement when the host opts out of creation assignee notices', function () {
    TicketPlugin::get()->notificationConfiguration(
        NotificationConfiguration::make()->notifyAssigneeOnCreation(false)
    );
    TicketPlugin::get()->assignmentStrategy(new AssignDefaultUser($this->colleague->id));
    forgetQueuedNotices();
    $this->login($this->requester);

    $ticket = openAssignedTicket();
    deliverQueuedNotices();

    expectOpened($this->requester, $ticket);
    expectUntold($this->colleague);
    expectUntold($this->previous);
});

it('keeps replies debounced even when an assignment is delivered with their batch pending', function () {
    $ticket = openAssignedTicket();
    forgetQueuedNotices();
    $this->login($this->requester);
    $ticket->ticketActivities()->create([
        'type' => ActivityType::Message,
        'sender' => ActivitySender::User,
        'user_id' => $this->requester->id,
        'content' => 'Please check the pending rent reply.',
    ]);

    expect(DB::table('jobs')->min('available_at'))->toBeGreaterThan(now()->timestamp);
    deliverQueuedNotices(waitForDebounce: false);
    expectUntold($this->colleague);

    reassignOnPage($ticket, $this->previous, $this->colleague->id);
    $queued = deliverQueuedNotices(waitForDebounce: false);
    expectTold($this->colleague, "Ticket #{$ticket->id} assigned to you", $queued);
    expect(implode("\n", mailHtmlFor($this->colleague)))->not->toContain('Please check the pending rent reply.');

    deliverQueuedNotices();
    $notices = noticesFor($this->colleague);
    expect($notices['mail'])->toHaveCount(2)
        ->and($notices['bells'])->toHaveCount(2)
        ->and(collect($notices['mail'])->filter(fn (string $subject): bool => str_contains($subject, 'assigned to you')))->toHaveCount(1)
        ->and(collect($notices['bells'])->filter(fn (string $title): bool => str_contains($title, 'assigned to you')))->toHaveCount(1)
        ->and(implode("\n", mailHtmlFor($this->colleague)))->toContain('Please check the pending rent reply.');
    expectUntold($this->previous);
});

it('does not send another notice when a pending activity batch contains only the assignment', function () {
    $ticket = openAssignedTicket($this->previous);
    forgetQueuedNotices();
    // With no actor, the assignment history also schedules a supporter activity notice.
    auth()->logout();
    $ticket->update(['assignee_id' => $this->colleague->id]);
    $queued = deliverQueuedNotices(waitForDebounce: false);
    expectTold($this->colleague, "Ticket #{$ticket->id} assigned to you", $queued);

    deliverQueuedNotices();
    expect(noticesFor($this->colleague)['mail'])->toHaveCount(1)
        ->and(noticesFor($this->colleague)['bells'])->toHaveCount(1);
});

it('keeps the immediate assignment out of a later close or reopen notification history', function (string $batch) {
    TicketPlugin::get()->notificationConfiguration(
        NotificationConfiguration::make()->on(TicketClosedEvent::class, fn () => NotificationRecipient::Supporter)
    );
    $this->login($this->previous);
    $ticket = openAssignedTicket($this->previous);

    if ($batch === 'reopened') {
        $ticket->close();
    }

    forgetQueuedNotices();
    $ticket->update(['assignee_id' => $this->colleague->id]);
    if ($batch === 'reopened') {
        $ticket->reopen();
    } else {
        $ticket->close();
    }

    $queued = deliverQueuedNotices(waitForDebounce: false);
    expectTold($this->colleague, "Ticket #{$ticket->id} assigned to you", $queued);
    expect(mailHtmlFor($this->colleague))->toHaveCount(1)
        ->and(mailHtmlFor($this->colleague)[0])->toContain('handed this ticket to you');

    deliverQueuedNotices();
    $notices = noticesFor($this->colleague);
    expect($notices['mail'])->toHaveCount(2)
        ->and($notices['bells'])->toHaveCount(2)
        ->and($notices['mail'][1])->toContain("Ticket {$batch}")
        ->and(mailHtmlFor($this->colleague)[1])->not->toContain('handed this ticket to you');
    $laterBells = $this->colleague->notifications()->get()
        ->filter(fn ($notice): bool => str_contains($notice->data['title'], "Ticket {$batch}"));
    expect($laterBells)->toHaveCount(1)
        ->and($laterBells->pluck('data.body')->implode("\n"))->not->toContain('handed');
})->with(['reopened', 'closed']);

it('queues an escalation auto-assignment without delaying either channel', function () {
    $original = openAssignedTicket($this->previous);
    TicketPlugin::get()->assignmentStrategy(new AssignDefaultUser($this->padmission->id));
    forgetQueuedNotices();
    $this->login($this->previous);

    Livewire::test(ViewTicket::class, ['record' => $original->id])
        ->callAction(TestAction::make(CreateLinkedTicketAction::class)->schemaComponent('escalationActions', schema: 'form'), [
            'subject' => 'Escalate this rent question',
            'message' => 'Please review the rent.',
        ])
        ->assertHasNoActionErrors();

    $escalation = $original->refresh()->parentTicket;
    expect($escalation)->not->toBeNull()
        ->and($escalation->assignee_id)->toBe($this->padmission->id);
    expectUntold($this->padmission);
    $queued = deliverQueuedNotices(waitForDebounce: false);
    expectAssigned($this->padmission, $escalation, $queued);
    deliverQueuedNotices();
    expect(collect(noticesFor($this->padmission)['mail'])->filter(fn (string $subject): bool => str_contains($subject, 'assigned to you')))->toHaveCount(1);
});

it('records an immediate handover so a quick later move still tells the former owner', function (bool $deliverFirst) {
    $escalation = escalationFrom(attributes: ['submitter_id' => $this->previous->id, 'assignee_id' => $this->padmission->id]);
    forgetQueuedNotices();
    $this->login($this->previous);
    $links = resolve(TicketEscalationLinks::class);
    expect($links->handOver($escalation, $this->colleague->id, $this->previous->id))->toBeTrue();

    if ($deliverFirst) {
        $queued = deliverQueuedNotices(waitForDebounce: false);
        expectTold($this->colleague, "Escalation handed to you #{$escalation->id}", $queued);
    }

    $this->travel(1)->seconds();
    expect($links->handOver($escalation, $this->previous->id, $this->colleague->id))->toBeTrue();
    deliverQueuedNotices();
    expect(noticesFor($this->colleague)['mail'])->toHaveCount($deliverFirst ? 2 : 0)
        ->and(noticesFor($this->colleague)['bells'])->toHaveCount($deliverFirst ? 2 : 0);
})->with(['delivered before the next move' => true, 'overtaken before the worker ran' => false]);
