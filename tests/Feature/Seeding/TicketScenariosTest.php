<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketDispositionSeeder;
use Padmission\Tickets\Database\Seeders\TicketPrioritySeeder;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Events\TicketActivityEvent;
use Padmission\Tickets\Events\TicketAssignedEvent;
use Padmission\Tickets\Events\TicketClosedEvent;
use Padmission\Tickets\Events\TicketCreatedEvent;
use Padmission\Tickets\Events\TicketHandedOverEvent;
use Padmission\Tickets\Events\TicketReopenedEvent;
use Padmission\Tickets\Events\TicketStatusChangedEvent;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketDisposition;
use Padmission\Tickets\Models\TicketUserState;
use Padmission\Tickets\Policies\TicketPolicy;
use Padmission\Tickets\Seeding\TicketScenarios;
use Padmission\Tickets\Services\TicketActivityService;
use Padmission\Tickets\Services\TicketEscalationLinks;
use Padmission\Tickets\Services\TicketReassignment;
use Padmission\Tickets\Services\TicketStarter;
use Padmission\Tickets\Support\ConversationState;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    (new TicketStatusSeeder)->run();
    (new TicketPrioritySeeder)->run();
    (new TicketDispositionSeeder)->run();
    Gate::policy(Ticket::class, TicketPolicy::class);

    $this->requester = User::factory()->create(['name' => 'Aisha Brooks']);
    $this->otherRequester = User::factory()->create(['name' => 'Tom Reyes']);
    $this->supporter = User::factory()->create(['name' => 'Maria Lopez']);
    $this->colleague = User::factory()->create(['name' => 'Dev Patel']);
    $this->platform = User::factory()->create(['name' => 'Kevin McKee']);

    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKey([$this->supporter->id, $this->colleague->id]));
    TicketPlugin::get('test2')->supportTeamName('Platform Support');
    TicketPlugin::get('test2')->allSupportersQuery(fn () => User::query()->whereKey($this->platform->id));

    $this->scenarios = TicketScenarios::make('test', null, [$this->requester, $this->otherRequester], [$this->supporter], [$this->colleague])
        ->escalatesTo('test2', [$this->platform]);
});

/**
 * @return list<array{0: string, 1: string, 2: ?int, 3: list<string>}>
 */
function historyOf(Ticket $ticket): array
{
    return TicketActivity::query()
        ->where('ticket_id', $ticket->id)
        ->orderBy('id')
        ->get()
        ->map(fn (TicketActivity $activity): array => [$activity->type->value, $activity->sender->value, $activity->user_id, array_keys($activity->data ?? [])])
        ->all();
}

/**
 * @return list<string>
 */
function typesOf(Ticket $ticket): array
{
    return array_column(historyOf($ticket), 0);
}

/*
 * The chat loads its history over the API after the page renders, so what
 * it shows is read the way that API reads it.
 */
function chatOf(User $viewer, Ticket $ticket): string
{
    test()->actingAs($viewer);

    return resolve(TicketActivityService::class)->getActivities($ticket->refresh(), user: $viewer)->pluck('content')->implode("\n");
}

function viewAs(User $user, Ticket $ticket, string $panel = 'test'): Testable
{
    Filament::setCurrentPanel($panel);
    test()->actingAs($user);

    return Livewire::test(ViewTicket::class, ['record' => $ticket->id])->assertOk();
}

it('seeds a conversation with both sides, an internal note and the requester\'s last word waiting on support', function () {
    $ticket = $this->scenarios->conversation();

    $messages = TicketActivity::query()->where('ticket_id', $ticket->id)->where('type', ActivityType::Message)->where('sender', '<>', ActivitySender::System)->get();

    expect($ticket->refresh()->turn)->toBe(Turn::Supporter)
        ->and($ticket->assignee_id)->toBe($this->supporter->id)
        ->and($ticket->isOpen)->toBeTrue()
        ->and($messages)->toHaveCount(5)
        ->and($messages->pluck('sender')->unique()->values()->all())->toEqualCanonicalizing([ActivitySender::User, ActivitySender::Supporter])
        ->and($messages->last()->sender)->toBe(ActivitySender::User)
        ->and(TicketActivity::query()->where('ticket_id', $ticket->id)->where('type', ActivityType::InternalMessage)->count())->toBe(1)
        ->and($ticket->created_at->isPast())->toBeTrue()
        ->and(TicketActivity::query()->where('ticket_id', $ticket->id)->max('created_at'))->toBeLessThan(now()->toDateTimeString());

    viewAs($this->supporter, $ticket)->assertSee('Monthly report export stops at 80%');

    expect(chatOf($this->supporter, $ticket))->toContain('Two halves worked')->toContain('export timeout on large reports')
        ->and(chatOf($this->requester, $ticket))->toContain('Two halves worked')->not->toContain('export timeout on large reports');

    $this->actingAs($this->supporter);

    expect(ConversationState::for($ticket)->isNew)->toBeTrue();
});

it('seeds a ticket waiting on the requester', function () {
    $ticket = $this->scenarios->waitingOnRequester()->refresh();

    expect($ticket->turn)->toBe(Turn::User)
        ->and($ticket->latestMessage->sender)->toBe(ActivitySender::Supporter);

    viewAs($this->supporter, $ticket)->assertSee(__('padmission-tickets::tickets.resources.tickets.waiting_on.requester'));
    viewAs($this->otherRequester, $ticket);
});

it('seeds a closed ticket with a disposition', function () {
    $ticket = $this->scenarios->closed()->refresh();

    expect($ticket->isClosed)->toBeTrue()
        ->and($ticket->closed_by)->toBe($this->supporter->id)
        ->and(TicketDisposition::query()->withoutGlobalScopes()->find($ticket->disposition_id)->display_name)->toBe('Resolved')
        ->and(array_slice(typesOf($ticket), -2))->toBe([ActivityType::StatusChanged->value, ActivityType::Closed->value]);

    viewAs($this->supporter, $ticket)->assertSee('Resolved');
});

it('seeds a ticket closed and reopened by the requester\'s reply', function () {
    $ticket = $this->scenarios->reopened()->refresh();
    $types = typesOf($ticket);
    $closedAt = array_search(ActivityType::Closed->value, $types, true);

    expect($ticket->isOpen)->toBeTrue()
        ->and($ticket->disposition_id)->toBeNull()
        ->and($ticket->turn)->toBe(Turn::Supporter)
        ->and($ticket->status->order)->toBe(1)
        ->and(array_slice($types, $closedAt + 1))->toBe([
            ActivityType::StatusChanged->value,
            ActivityType::Reopened->value,
            ActivityType::Message->value,
            ActivityType::TurnChanged->value,
        ]);

    viewAs($this->supporter, $ticket);

    expect(chatOf($this->supporter, $ticket))->toContain(__('padmission-tickets::activities.reopened', ['name' => 'Aisha Brooks']));
});

it('seeds an unassigned ticket', function () {
    $ticket = $this->scenarios->unassigned()->refresh();

    expect($ticket->assignee_id)->toBeNull()
        ->and($ticket->turn)->toBe(Turn::Supporter);

    viewAs($this->supporter, $ticket)->assertSee(__('padmission-tickets::tickets.resources.tickets.waiting_on.unassigned'));
});

it('seeds a ticket assigned outside the supporter pool, which asks for a new assignee', function () {
    $ticket = $this->scenarios->assignedToNonSupporter()->refresh();

    expect($ticket->assignee_id)->toBe($this->requester->id);

    $this->actingAs($this->supporter);

    expect(ConversationState::for($ticket)->waitingOn)->toBe('assignee_cannot_answer');

    viewAs($this->supporter, $ticket)->assertSee('Aisha Brooks can', false);
});

it('seeds a ticket a supporter opened for a requester', function () {
    $ticket = $this->scenarios->openedFor()->refresh();

    expect($ticket->submitter_id)->toBe($this->otherRequester->id)
        ->and($ticket->assignee_id)->toBe($this->supporter->id)
        ->and(historyOf($ticket)[0])->toBe([ActivityType::OpenedFor->value, ActivitySender::System->value, $this->supporter->id, ['requester']])
        ->and(TicketActivity::query()->where('ticket_id', $ticket->id)->where('type', ActivityType::OpenedFor)->value('data'))->toBe(['requester' => $this->otherRequester->id]);

    viewAs($this->otherRequester, $ticket);

    expect(chatOf($this->otherRequester, $ticket))->toContain(__('padmission-tickets::activities.opened_for_you', ['name' => 'Maria Lopez']));
});

it('seeds an escalation that links its originals, some closed', function () {
    $escalation = $this->scenarios->escalation(originals: 3, closedOriginals: 1);
    $originals = $escalation->childTickets;

    expect($escalation->panel)->toBe('test2')
        ->and($escalation->source_panel)->toBe('test')
        ->and($escalation->submitter_id)->toBe($this->supporter->id)
        ->and($escalation->assignee_id)->toBe($this->platform->id)
        ->and($escalation->refresh()->isEscalation())->toBeTrue()
        ->and($escalation->isDirectQuestion())->toBeFalse()
        ->and($escalation->turn)->toBe(Turn::Supporter)
        ->and($originals)->toHaveCount(3)
        ->and($originals->filter->isClosed)->toHaveCount(1)
        ->and($originals->last()->isClosed)->toBeTrue()
        ->and(TicketActivity::query()->where('ticket_id', $escalation->id)->where('type', ActivityType::OriginalAdded)->pluck('data')->pluck('original')->all())->toBe($originals->modelKeys())
        ->and(in_array(ActivityType::Escalated->value, typesOf($originals[0]), true))->toBeTrue()
        ->and(in_array(ActivityType::AddedToEscalation->value, typesOf($originals[1]), true))->toBeTrue()
        ->and(in_array(ActivityType::AddedToEscalation->value, typesOf($originals[2]), true))->toBeTrue()
        ->and(resolve(TicketEscalationLinks::class)->hasOpenEscalation($originals[0]))->toBeTrue();

    viewAs($this->supporter, $escalation)->assertSee('Imports stuck in processing for several customers');
    viewAs($this->supporter, $originals[0])->assertSee('Escalated to Platform Support');
    viewAs($this->platform, $escalation, 'test2')->assertActionVisible('show-linked');

    expect(chatOf($this->platform, $escalation))->toContain('The earliest one we know of started');
});

it('leaves an answered escalation waiting on the team that escalated', function () {
    $escalation = $this->scenarios->escalation(answered: true)->refresh();

    expect($escalation->turn)->toBe(Turn::User)
        ->and($escalation->latestMessage->user_id)->toBe($this->platform->id);
});

it('refuses an escalation without originals or with more closed than it has', function (int $originals, int $closed) {
    $this->scenarios->escalation(originals: $originals, closedOriginals: $closed);
})->with([
    'no originals' => [0, 0],
    'more closed than linked' => [1, 2],
])->throws(InvalidArgumentException::class);

it('seeds a direct question, whose page offers no escalation', function () {
    $question = $this->scenarios->directQuestion()->refresh();

    expect($question->isDirectQuestion())->toBeTrue()
        ->and($question->panel)->toBe('test2')
        ->and($question->childTickets()->withoutGlobalScopes()->count())->toBe(0);

    viewAs($this->supporter, $question)
        ->assertSee('Can we raise our file upload limit?')
        ->assertActionHidden('show-linked')
        ->assertDontSeeHtml('pad-ti-originals');

    viewAs($this->platform, $question, 'test2')
        ->assertActionHidden('show-linked')
        ->assertDontSeeHtml('pad-ti-originals');
});

it('seeds an escalation handed over to a colleague', function () {
    $escalation = $this->scenarios->handedOver()->refresh();
    $handOver = TicketActivity::query()->where('ticket_id', $escalation->id)->where('type', ActivityType::HandedOver)->sole();

    expect($escalation->submitter_id)->toBe($this->colleague->id)
        ->and($handOver->user_id)->toBe($this->supporter->id)
        ->and($handOver->data)->toBe(['from' => $this->supporter->id, 'to' => $this->colleague->id])
        ->and($escalation->latestMessage->user_id)->toBe($this->colleague->id)
        ->and($escalation->turn)->toBe(Turn::Supporter);

    viewAs($this->colleague, $escalation);

    expect(chatOf($this->platform, $escalation))->toContain(__('padmission-tickets::activities.handed_over', ['from' => 'Maria Lopez', 'to' => 'Dev Patel']));
});

it('writes the history rows the live flows write', function (Closure $seeded, Closure $live) {
    expect(Closure::bind($seeded, test())())->toBe(Closure::bind($live, test())());
})->with([
    'a question asked directly' => [
        fn () => historyOf($this->scenarios->directQuestion()),
        function () {
            Filament::setCurrentPanel('test');
            $this->actingAs($this->supporter);

            return historyOf(resolve(TicketStarter::class)->ask('test2', 'Question', '<p>Hi</p>'));
        },
    ],
    'a ticket opened for someone' => [
        fn () => array_slice(historyOf($this->scenarios->openedFor()), 0, 2),
        function () {
            Filament::setCurrentPanel('test');
            $this->actingAs($this->supporter);

            return historyOf(resolve(TicketStarter::class)->openFor($this->otherRequester, 'Opened', '<p>Hi</p>', $this->supporter->id));
        },
    ],
    'a chat ticket answered by support' => [
        fn () => historyOf($this->scenarios->waitingOnRequester()),
        function () {
            $this->actingAs($this->otherRequester);
            $id = $this->postJson(route('padmission-tickets::api.store'), ['subject' => 'Chat'])->assertSuccessful()->json('id');
            $this->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $id]), ['content' => '<p>Hello</p>'])->assertOk();

            $this->actingAs($this->supporter);
            $this->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $id]), ['content' => '<p>Hi</p>'])->assertOk();

            return historyOf(Ticket::find($id));
        },
    ],
    'closing' => [
        fn () => array_slice(historyOf($this->scenarios->closed()), -2),
        function () {
            $this->actingAs($this->supporter);
            $ticket = Ticket::factory()->open()->create(['submitter_id' => $this->requester->id]);
            $ticket->close(TicketDisposition::query()->orderBy('order')->value('id'), $this->supporter->id);

            return historyOf($ticket);
        },
    ],
    'reopening by reply' => [
        fn () => array_slice(historyOf($this->scenarios->reopened()), -4),
        function () {
            $ticket = Ticket::factory()->closed()->create(['submitter_id' => $this->requester->id, 'turn' => Turn::User]);
            $ticket->addTicketActivity(ActivityType::Message, ActivitySender::Supporter, $this->supporter->id, content: '<p>Done</p>');

            $this->actingAs($this->requester);
            $this->postJson(route('padmission-tickets::api.messages.store', ['ticket' => $ticket]), ['content' => '<p>Again</p>', 'reopen' => true])->assertOk();

            return array_slice(historyOf($ticket), -4);
        },
    ],
    'escalating and adding an original' => [
        function () {
            $escalation = $this->scenarios->escalation(originals: 2);

            return [
                array_values(array_filter(historyOf($escalation), fn (array $row): bool => $row[0] === ActivityType::OriginalAdded->value)),
                array_values(array_filter(historyOf($escalation->childTickets[0]), fn (array $row): bool => $row[0] === ActivityType::Escalated->value)),
                array_values(array_filter(historyOf($escalation->childTickets[1]), fn (array $row): bool => $row[0] === ActivityType::AddedToEscalation->value)),
            ];
        },
        function () {
            $this->actingAs($this->supporter);
            $escalation = Ticket::factory()->open()->create(['panel' => 'test2', 'source_panel' => 'test', 'submitter_id' => $this->supporter->id]);
            [$first, $second] = Ticket::factory()->open()->count(2)->create(['submitter_id' => $this->requester->id]);

            $links = resolve(TicketEscalationLinks::class);
            $links->linkNewEscalation($first, $escalation);
            $links->syncOriginals($escalation, [$first->id, $second->id]);

            return [historyOf($escalation), historyOf($first), historyOf($second)];
        },
    ],
    'handing over' => [
        fn () => array_values(array_filter(historyOf($this->scenarios->handedOver()), fn (array $row): bool => $row[0] === ActivityType::HandedOver->value)),
        function () {
            $this->actingAs($this->supporter);
            $escalation = escalationFrom(attributes: ['submitter_id' => $this->supporter->id]);
            TicketActivity::query()->where('ticket_id', $escalation->id)->delete();

            resolve(TicketEscalationLinks::class)->handOver($escalation, $this->colleague->id, $this->supporter->id);

            return historyOf($escalation);
        },
    ],
]);

it('reassigns as the live Reassign does', function (string $by, string $note) {
    $scenarios = new class('test', null, [$this->requester], [$this->supporter], [$this->colleague]) extends TicketScenarios
    {
        public function reassigned(User $by, User $to): Ticket
        {
            return $this->seedOnce('reassigned', function () use ($by, $to): Ticket {
                $this->startAt(days: 1);
                $ticket = $this->openFromChat($this->requester(0), 'Reassigned', '<p>Hi</p>', $this->supporter(0));
                $this->later(hours: 1);
                $this->reassign($ticket, $to, $by);
                $this->reassign($ticket, $to, $by);

                return $ticket;
            });
        }
    };

    $assignees = fn (Ticket $ticket): array => TicketActivity::query()->where('ticket_id', $ticket->id)->where('type', ActivityType::AssigneeChanged)->get()
        ->map(fn (TicketActivity $activity): array => [$activity->sender->value, $activity->user_id, $activity->data, $activity->assigneeNote(null)])->all();

    $seeded = $scenarios->reassigned($this->{$by}, $this->colleague)->refresh();

    $this->actingAs($this->{$by});
    $live = Ticket::factory()->open()->create(['submitter_id' => $this->requester->id, 'assignee_id' => $this->supporter->id]);
    expect(resolve(TicketReassignment::class)->assign($live, $this->colleague->id))->toBeTrue();

    expect($assignees($seeded))->toBe($assignees($live))
        ->and($assignees($seeded))->toHaveCount(1)
        ->and($assignees($seeded)[0][3])->toContain($note)
        ->and($seeded->assignee_id)->toBe($this->colleague->id)
        ->and($seeded->updated_at->isPast())->toBeTrue();

    viewAs($this->colleague, $seeded);
})->with([
    'handed by the assignee' => ['supporter', 'Maria Lopez'],
    'taken by the new assignee' => ['colleague', 'Dev Patel'],
]);

it('marks each writer as having read up to their own last word', function () {
    $ticket = $this->scenarios->waitingOnRequester();
    $lastSeen = fn (User $user): ?int => TicketUserState::query()->where('ticket_id', $ticket->id)->where('user_id', $user->id)->value('last_seen_activity_id');

    expect($lastSeen($this->supporter))->toBe($ticket->latestMessage->id)
        ->and($ticket->hasUnreadMessagesFor($this->otherRequester))->toBeTrue()
        ->and($ticket->hasUnreadMessagesFor($this->supporter))->toBeFalse();
});

it('notifies nobody', function () {
    Event::fake([
        TicketActivityEvent::class,
        TicketAssignedEvent::class,
        TicketClosedEvent::class,
        TicketCreatedEvent::class,
        TicketHandedOverEvent::class,
        TicketReopenedEvent::class,
        TicketStatusChangedEvent::class,
    ]);
    Queue::fake();

    expect($this->scenarios->all())->toHaveKeys(['conversation', 'escalation', 'directQuestion', 'handedOver']);

    Event::assertNothingDispatched();
    Queue::assertNothingPushed();
});

it('returns the first run\'s tickets when seeded again', function () {
    $first = collect($this->scenarios->all())->map->getKey();
    $count = Ticket::query()->withoutGlobalScopes()->count();

    $second = collect(TicketScenarios::make('test', null, [$this->requester], [$this->supporter], [$this->colleague])->escalatesTo('test2', [$this->platform])->all())->map->getKey();

    expect($second->all())->toBe($first->all())
        ->and(Ticket::query()->withoutGlobalScopes()->count())->toBe($count)
        ->and($this->scenarios->escalation(originals: 3, closedOriginals: 1)->childTickets)->toHaveCount(3);
});

it('keeps scenarios apart by their key', function () {
    expect($this->scenarios->conversation()->id)->not->toBe($this->scenarios->conversation(key: 'conversation-2')->id)
        ->and($this->scenarios->escalation(originals: 1)->id)->not->toBe($this->scenarios->escalation(originals: 2)->id);
});

it('skips the escalation scenarios until told where to escalate', function () {
    $tickets = TicketScenarios::make('test', null, [$this->requester], [$this->supporter])->all();

    expect($tickets)->not->toHaveKeys(['escalation', 'directQuestion', 'handedOver'])
        ->and(fn () => TicketScenarios::make('test', null, [$this->requester], [$this->supporter])->directQuestion())
        ->toThrow(InvalidArgumentException::class);
});

it('refuses a panel without the plugin, or no one to act', function (Closure $make) {
    Closure::bind($make, test())();
})->with([
    'unknown panel' => [fn () => TicketScenarios::make('nope', null, [$this->requester], [$this->supporter])],
    'no requesters' => [fn () => TicketScenarios::make('test', null, [], [$this->supporter])],
    'no supporters' => [fn () => TicketScenarios::make('test', null, [$this->requester], [])],
])->throws(InvalidArgumentException::class);

describe('tickets:seed --only=scenarios', function () {
    it('seeds the scenarios once for each panel that starts tickets, from its own supporters and requesters', function () {
        $this->artisan('tickets:seed', ['--only' => 'scenarios'])->assertSuccessful();

        $seeded = fn (string $panel) => Ticket::query()->withoutGlobalScopes()->where('source_panel', $panel)->whereNotNull('data->'.TicketScenarios::MARKER);
        $count = Ticket::query()->withoutGlobalScopes()->count();

        expect($seeded('test')->where('data->'.TicketScenarios::MARKER, 'handedOver')->exists())->toBeTrue()
            ->and($seeded('test')->where('panel', 'test2')->exists())->toBeTrue()
            ->and($seeded('test2')->exists())->toBeFalse()
            ->and($seeded('test')->pluck('submitter_id')->intersect([$this->platform->id]))->toBeEmpty();

        $this->artisan('tickets:seed', ['--only' => 'scenarios'])->assertSuccessful();

        expect(Ticket::query()->withoutGlobalScopes()->count())->toBe($count);
    });

    it('leaves out a panel others escalate to, even one that starts tickets', function () {
        TicketPlugin::get('test2')->startsTickets();

        $this->artisan('tickets:seed', ['--only' => 'scenarios'])->assertSuccessful();

        expect(Ticket::query()->withoutGlobalScopes()->where('source_panel', 'test2')->exists())->toBeFalse()
            ->and(Ticket::query()->withoutGlobalScopes()->where('source_panel', 'test')->where('panel', 'test2')->exists())->toBeTrue()
            ->and(Ticket::query()->withoutGlobalScopes()->where('source_panel', 'test3')->exists())->toBeTrue();
    });

    it('seeds only the panel asked for', function () {
        $this->artisan('tickets:seed', ['--only' => 'scenarios', '--panel' => 'test3'])->assertSuccessful();

        expect(Ticket::query()->withoutGlobalScopes()->whereNot('source_panel', 'test3')->exists())->toBeFalse()
            ->and(Ticket::query()->withoutGlobalScopes()->where('source_panel', 'test3')->exists())->toBeTrue();
    });

    it('is left out when no types are named', function () {
        $this->artisan('tickets:seed', ['--only' => 'statuses'])->assertSuccessful();
        $this->artisan('tickets:seed')->assertSuccessful();

        expect(Ticket::query()->withoutGlobalScopes()->whereNotNull('data->'.TicketScenarios::MARKER)->exists())->toBeFalse();
    });
});
