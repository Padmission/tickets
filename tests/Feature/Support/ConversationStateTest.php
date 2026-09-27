<?php

use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Once;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketUserState;
use Padmission\Tickets\Support\ConversationState;
use Padmission\Tickets\Support\ConversationStateQuery;
use Padmission\Tickets\Support\ConversationViewer;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

function conversationMessage(Ticket $ticket, ActivitySender $sender, ?int $userId): TicketActivity
{
    return TicketActivity::factory()->create([
        'ticket_id' => $ticket->id,
        'type' => ActivityType::Message,
        'sender' => $sender,
        'user_id' => $userId,
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function conversationEscalation(array $attributes = []): Ticket
{
    return Ticket::factory()->open()->create([
        'panel' => 'test2',
        'source_panel' => 'test',
        'turn' => Turn::Supporter,
        ...$attributes,
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function conversationOriginal(array $attributes = []): Ticket
{
    return Ticket::factory()->open()->create([
        'panel' => 'test',
        'turn' => Turn::Supporter,
        ...$attributes,
    ]);
}

beforeEach(function () {
    (new TicketStatusSeeder)->run();
    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    TicketPlugin::get('test2')->supportTeamName('Platform Support');

    $this->me = $this->login(User::factory()->create(['name' => 'Test Admin']));
    $this->colleague = User::factory()->create(['name' => 'Maria Lopez']);
    $this->requester = User::factory()->create(['name' => 'Aisha Brooks']);
});

afterEach(fn () => User::clearBootedModels());

it('works out who owes the next message, the marker, the rank and New from one SQL source', function (Closure $scenario) {
    /** @var array{row: Ticket, expect: array<string, mixed>, panel?: string, also?: array<int, array<string, mixed>>} $case */
    $case = $scenario($this->me, $this->colleague, $this->requester);

    Filament::setCurrentPanel($case['panel'] ?? 'test');

    $rows = Ticket::query()->withConversationState()->get()->keyBy('id');
    $row = $rows[$case['row']->id];

    $codes = fn (Ticket $row): array => [
        'waiting_on' => $row->conversation_waiting_on,
        'marker' => $row->conversation_marker,
        'rank' => (int) $row->conversation_rank,
        'is_new' => (int) $row->conversation_is_new,
    ];

    expect($codes($row))->toMatchArray($case['expect']);

    foreach ($case['also'] ?? [] as $id => $expect) {
        expect($codes($rows[$id]))->toMatchArray($expect);
    }

    [$rank, $bindings] = ConversationStateQuery::rankExpression(ConversationViewer::current());
    $orange = $rows->load(['parentTicket', 'submitter', 'assignee'])
        ->filter(fn (Ticket $ticket): bool => ConversationState::fromRow($ticket)->color() === 'warning'
            || ConversationState::fromRow($ticket)->markerColor() === 'warning');

    expect(Ticket::query()->whereRaw("{$rank} = 0", $bindings)->pluck('id')->sort()->values()->all())
        ->toBe($orange->keys()->sort()->values()->all());
})->with([
    'a message on one original clears only that original\'s relay' => function ($me, $colleague, $requester) {
        $escalation = conversationEscalation(['submitter_id' => $me->id]);
        $answered = conversationOriginal(['submitter_id' => $requester->id, 'assignee_id' => $me->id, 'linked_ticket_id' => $escalation->id]);
        $waiting = conversationOriginal(['submitter_id' => $colleague->id, 'assignee_id' => $me->id, 'linked_ticket_id' => $escalation->id]);
        conversationMessage($escalation, ActivitySender::Supporter, $colleague->id);
        conversationMessage($answered, ActivitySender::Supporter, $me->id);

        return ['row' => $waiting, 'expect' => ['marker' => 'replied', 'rank' => 0], 'also' => [$answered->id => ['marker' => 'escalated', 'waiting_on' => 'you_on_hold', 'rank' => 1]]];
    },
    'system rows and other activity after a team message neither clear nor trigger a relay' => function ($me, $colleague, $requester) {
        $escalation = conversationEscalation(['submitter_id' => $me->id]);
        $original = conversationOriginal(['submitter_id' => $requester->id, 'assignee_id' => $me->id, 'linked_ticket_id' => $escalation->id]);
        conversationMessage($escalation, ActivitySender::Supporter, $colleague->id);
        $original->addTicketActivity(ActivityType::StatusChanged, ActivitySender::Supporter, $me->id);
        $original->addTicketActivity(ActivityType::InternalMessage, ActivitySender::Supporter, $me->id);
        $escalation->addTicketActivity(ActivityType::AssigneeChanged, ActivitySender::System, $me->id);
        $escalation->addTicketActivity(ActivityType::TurnChanged, ActivitySender::User, $me->id);

        $quiet = conversationOriginal(['submitter_id' => $requester->id, 'assignee_id' => $me->id, 'linked_ticket_id' => conversationEscalation(['submitter_id' => $me->id])->id]);
        conversationMessage($quiet, ActivitySender::Supporter, $me->id);
        $quiet->parentTicket->addTicketActivity(ActivityType::StatusChanged, ActivitySender::Supporter, $colleague->id);
        $quiet->parentTicket->addTicketActivity(ActivityType::Message, ActivitySender::System, null);

        return ['row' => $original, 'expect' => ['marker' => 'replied', 'rank' => 0], 'also' => [$quiet->id => ['marker' => 'escalated', 'waiting_on' => 'you_on_hold', 'rank' => 1]]];
    },
    'closed original' => fn ($me) => [
        'row' => Ticket::factory()->closed()->create(['panel' => 'test', 'assignee_id' => $me->id, 'turn' => Turn::Supporter]),
        'expect' => ['waiting_on' => 'closed', 'marker' => null, 'rank' => 3],
    ],
    'own ticket waiting on me' => fn ($me, $colleague) => [
        'row' => conversationOriginal(['submitter_id' => $me->id, 'assignee_id' => $colleague->id, 'turn' => Turn::User]),
        'expect' => ['waiting_on' => 'you_requester', 'rank' => 0],
    ],
    'waiting on the requester' => fn ($me, $colleague, $requester) => [
        'row' => conversationOriginal(['submitter_id' => $requester->id, 'assignee_id' => $me->id, 'turn' => Turn::User]),
        'expect' => ['waiting_on' => 'requester', 'marker' => null, 'rank' => 2],
    ],
    'assigned to me' => fn ($me, $colleague, $requester) => [
        'row' => conversationOriginal(['submitter_id' => $requester->id, 'assignee_id' => $me->id]),
        'expect' => ['waiting_on' => 'you', 'marker' => null, 'rank' => 0],
    ],
    'unassigned' => fn ($me, $colleague, $requester) => [
        'row' => conversationOriginal(['submitter_id' => $requester->id, 'assignee_id' => null]),
        'expect' => ['waiting_on' => 'needs_assignment', 'rank' => 0],
    ],
    'assigned outside the pool' => function ($me, $colleague, $requester) {
        TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKey($me->id));

        return [
            'row' => conversationOriginal(['submitter_id' => $requester->id, 'assignee_id' => $colleague->id]),
            'expect' => ['waiting_on' => 'needs_assignment', 'rank' => 0],
        ];
    },
    'assigned to a colleague' => fn ($me, $colleague, $requester) => [
        'row' => conversationOriginal(['submitter_id' => $requester->id, 'assignee_id' => $colleague->id]),
        'expect' => ['waiting_on' => 'colleague', 'rank' => 1],
    ],
    'on hold' => function ($me, $colleague, $requester) {
        $escalation = conversationEscalation(['submitter_id' => $me->id]);
        $original = conversationOriginal(['submitter_id' => $requester->id, 'assignee_id' => $me->id, 'linked_ticket_id' => $escalation->id]);
        conversationMessage($original, ActivitySender::User, $requester->id);
        conversationMessage($original, ActivitySender::Supporter, $me->id);
        conversationMessage($escalation, ActivitySender::User, $me->id);

        return ['row' => $original, 'expect' => ['waiting_on' => 'you_on_hold', 'marker' => 'escalated', 'rank' => 1]];
    },
    'hold lifted by a new requester message' => function ($me, $colleague, $requester) {
        $escalation = conversationEscalation(['submitter_id' => $me->id]);
        $original = conversationOriginal(['submitter_id' => $requester->id, 'assignee_id' => $me->id, 'linked_ticket_id' => $escalation->id]);
        conversationMessage($original, ActivitySender::Supporter, $me->id);
        conversationMessage($original, ActivitySender::User, $requester->id);

        return ['row' => $original, 'expect' => ['waiting_on' => 'you', 'marker' => 'escalated', 'rank' => 0]];
    },
    'not on hold before anyone from the organization wrote' => function ($me, $colleague, $requester) {
        $escalation = conversationEscalation(['submitter_id' => $me->id]);
        $original = conversationOriginal(['submitter_id' => $requester->id, 'assignee_id' => $me->id, 'linked_ticket_id' => $escalation->id]);
        conversationMessage($original, ActivitySender::User, $requester->id);

        return ['row' => $original, 'expect' => ['waiting_on' => 'you', 'marker' => 'escalated', 'rank' => 0]];
    },
    'a colleague\'s ticket on hold' => function ($me, $colleague, $requester) {
        $escalation = conversationEscalation(['submitter_id' => $colleague->id]);
        $original = conversationOriginal(['submitter_id' => $requester->id, 'assignee_id' => $colleague->id, 'linked_ticket_id' => $escalation->id]);
        conversationMessage($original, ActivitySender::Supporter, $colleague->id);

        return ['row' => $original, 'expect' => ['waiting_on' => 'colleague_on_hold', 'marker' => 'escalated', 'rank' => 1]];
    },
    'relay pending from Send' => function ($me, $colleague, $requester) {
        $escalation = conversationEscalation(['submitter_id' => $me->id, 'turn' => Turn::User]);
        $original = conversationOriginal(['submitter_id' => $requester->id, 'assignee_id' => $me->id, 'linked_ticket_id' => $escalation->id]);
        conversationMessage($original, ActivitySender::Supporter, $me->id);
        conversationMessage($escalation, ActivitySender::User, $me->id);
        conversationMessage($escalation, ActivitySender::Supporter, $colleague->id);

        return ['row' => $original, 'expect' => ['waiting_on' => 'you', 'marker' => 'replied', 'rank' => 0]];
    },
    'relay pending from a locked send, on a colleague\'s original' => function ($me, $colleague, $requester) {
        $escalation = conversationEscalation(['submitter_id' => $me->id, 'turn' => Turn::Supporter]);
        $original = conversationOriginal(['submitter_id' => $requester->id, 'assignee_id' => $colleague->id, 'linked_ticket_id' => $escalation->id]);
        conversationMessage($original, ActivitySender::Supporter, $colleague->id);
        conversationMessage($escalation, ActivitySender::Supporter, $colleague->id);

        return ['row' => $original, 'expect' => ['waiting_on' => 'colleague', 'marker' => 'replied', 'rank' => 0]];
    },
    'relay pending after the escalation closed' => function ($me, $colleague, $requester) {
        $escalation = conversationEscalation(['submitter_id' => $me->id]);
        $original = conversationOriginal(['submitter_id' => $requester->id, 'assignee_id' => $me->id, 'linked_ticket_id' => $escalation->id]);
        conversationMessage($original, ActivitySender::Supporter, $me->id);
        conversationMessage($escalation, ActivitySender::Supporter, $colleague->id);
        $escalation->forceFill(['closed_at' => now()])->save();

        return ['row' => $original, 'expect' => ['waiting_on' => 'you', 'marker' => 'replied', 'rank' => 0]];
    },
    'relay pending for someone else' => function ($me, $colleague, $requester) {
        $escalation = conversationEscalation(['submitter_id' => $colleague->id]);
        $original = conversationOriginal(['submitter_id' => $requester->id, 'assignee_id' => $colleague->id, 'linked_ticket_id' => $escalation->id]);
        conversationMessage($escalation, ActivitySender::Supporter, $me->id);

        return ['row' => $original, 'expect' => ['waiting_on' => 'colleague', 'marker' => 'replied', 'rank' => 1]];
    },
    'relay cleared by an organization message on the original' => function ($me, $colleague, $requester) {
        $escalation = conversationEscalation(['submitter_id' => $me->id]);
        $original = conversationOriginal(['submitter_id' => $requester->id, 'assignee_id' => $me->id, 'linked_ticket_id' => $escalation->id]);
        conversationMessage($escalation, ActivitySender::Supporter, $colleague->id);
        conversationMessage($original, ActivitySender::Supporter, $me->id);

        return ['row' => $original, 'expect' => ['waiting_on' => 'you_on_hold', 'marker' => 'escalated', 'rank' => 1]];
    },
    'relay cleared by the owner\'s message on the escalation' => function ($me, $colleague, $requester) {
        $escalation = conversationEscalation(['submitter_id' => $me->id]);
        $original = conversationOriginal(['submitter_id' => $requester->id, 'assignee_id' => $me->id, 'linked_ticket_id' => $escalation->id]);
        conversationMessage($original, ActivitySender::Supporter, $me->id);
        conversationMessage($escalation, ActivitySender::Supporter, $colleague->id);
        conversationMessage($escalation, ActivitySender::User, $me->id);

        return ['row' => $original, 'expect' => ['waiting_on' => 'you_on_hold', 'marker' => 'escalated', 'rank' => 1]];
    },
    'never pending when the owner asked the original' => function ($me, $colleague) {
        $escalation = conversationEscalation(['submitter_id' => $me->id]);
        $original = conversationOriginal(['submitter_id' => $me->id, 'assignee_id' => $colleague->id, 'linked_ticket_id' => $escalation->id]);
        conversationMessage($escalation, ActivitySender::Supporter, $colleague->id);

        return ['row' => $original, 'expect' => ['waiting_on' => 'colleague', 'marker' => 'escalated', 'rank' => 1]];
    },
    'a guest requester' => function ($me, $colleague) {
        $escalation = conversationEscalation(['submitter_id' => $me->id]);
        $original = Ticket::factory()->open()->withSubmitterData()->create(['panel' => 'test', 'turn' => Turn::Supporter, 'assignee_id' => $colleague->id, 'linked_ticket_id' => $escalation->id]);
        conversationMessage($escalation, ActivitySender::Supporter, $colleague->id);

        return ['row' => $original, 'expect' => ['waiting_on' => 'colleague', 'marker' => 'replied', 'rank' => 0]];
    },
    'escalation closed' => function ($me, $colleague, $requester) {
        $escalation = Ticket::factory()->closed()->create(['panel' => 'test2', 'submitter_id' => $me->id]);
        $original = conversationOriginal(['submitter_id' => $requester->id, 'assignee_id' => $colleague->id, 'linked_ticket_id' => $escalation->id]);
        conversationMessage($original, ActivitySender::Supporter, $colleague->id);

        return ['row' => $original, 'expect' => ['waiting_on' => 'colleague', 'marker' => 'closed', 'rank' => 1]];
    },
    'a deleted escalation' => function ($me, $colleague, $requester) {
        $escalation = conversationEscalation(['submitter_id' => $me->id]);
        $original = conversationOriginal(['submitter_id' => $requester->id, 'assignee_id' => $me->id, 'linked_ticket_id' => $escalation->id]);
        conversationMessage($original, ActivitySender::Supporter, $me->id);
        conversationMessage($escalation, ActivitySender::Supporter, $colleague->id);
        $escalation->delete();

        return ['row' => $original, 'expect' => ['waiting_on' => 'you', 'marker' => null, 'rank' => 0]];
    },
    'escalation waiting on the team' => fn ($me, $colleague, $requester) => [
        'row' => conversationEscalation(['submitter_id' => $me->id])->childTickets()->save(conversationOriginal(['submitter_id' => $requester->id]))->parentTicket,
        'expect' => ['waiting_on' => 'team', 'marker' => null, 'rank' => 2],
    ],
    'escalation waiting on me' => fn ($me, $colleague, $requester) => [
        'row' => conversationEscalation(['submitter_id' => $me->id, 'turn' => Turn::User])->childTickets()->save(conversationOriginal(['submitter_id' => $requester->id]))->parentTicket,
        'expect' => ['waiting_on' => 'you_owner', 'rank' => 0],
    ],
    'escalation waiting on a colleague' => fn ($me, $colleague, $requester) => [
        'row' => conversationEscalation(['submitter_id' => $colleague->id, 'turn' => Turn::User])->childTickets()->save(conversationOriginal(['submitter_id' => $requester->id]))->parentTicket,
        'expect' => ['waiting_on' => 'owner_colleague', 'rank' => 1],
    ],
    'escalation whose originals were all removed' => function ($me) {
        $escalation = conversationEscalation(['submitter_id' => $me->id, 'turn' => Turn::User]);
        $escalation->addTicketActivity(ActivityType::OriginalAdded, sender: ActivitySender::System, userId: $me->id);

        return ['row' => $escalation, 'expect' => ['waiting_on' => 'you_owner', 'rank' => 0]];
    },
    'a widget ticket filed into the other panel' => fn ($me) => [
        'row' => conversationEscalation(['submitter_id' => $me->id, 'turn' => Turn::User]),
        'expect' => ['waiting_on' => null, 'marker' => null, 'rank' => 2],
    ],
    'received escalation waiting on the contact' => fn ($me, $colleague) => [
        'panel' => 'test2',
        'row' => conversationEscalation(['submitter_id' => $colleague->id, 'assignee_id' => $me->id, 'turn' => Turn::User]),
        'expect' => ['waiting_on' => 'contact', 'marker' => null, 'rank' => 2],
    ],
    'received escalation assigned to me' => fn ($me, $colleague) => [
        'panel' => 'test2',
        'row' => conversationEscalation(['submitter_id' => $colleague->id, 'assignee_id' => $me->id]),
        'expect' => ['waiting_on' => 'you', 'rank' => 0],
    ],
    'received escalation unassigned' => fn ($me, $colleague) => [
        'panel' => 'test2',
        'row' => conversationEscalation(['submitter_id' => $colleague->id, 'assignee_id' => null]),
        'expect' => ['waiting_on' => 'needs_assignment', 'rank' => 0],
    ],
    'received escalation assigned to another account of someone in the pool' => function ($me, $colleague) {
        Schema::table('users', fn (Blueprint $table) => $table->dropUnique(['email']));
        $pooled = User::factory()->create(['name' => 'Kevin McKee', 'email' => 'kevin@example.com']);
        $otherAccount = User::factory()->create(['name' => 'Kevin McKee', 'email' => 'kevin@example.com']);

        TicketPlugin::get('test2')
            ->allSupportersQuery(fn () => User::query()->whereKey([$me->id, $pooled->id]))
            ->matchSupportersBy('email')
            ->modifyRelationshipScopes(fn ($relation) => $relation->withoutGlobalScope('acting-tenant'));
        User::addGlobalScope('acting-tenant', fn ($query) => $query->whereKeyNot($otherAccount->id));

        return [
            'panel' => 'test2',
            'row' => conversationEscalation(['submitter_id' => $colleague->id, 'assignee_id' => $otherAccount->id]),
            'expect' => ['waiting_on' => 'colleague', 'rank' => 1],
        ];
    },
    'received escalation assigned to another account, matched by id' => function ($me, $colleague) {
        Schema::table('users', fn (Blueprint $table) => $table->dropUnique(['email']));
        $pooled = User::factory()->create(['email' => 'kevin@example.com']);
        $otherAccount = User::factory()->create(['email' => 'kevin@example.com']);

        TicketPlugin::get('test2')->allSupportersQuery(fn () => User::query()->whereKey([$me->id, $pooled->id]));

        return [
            'panel' => 'test2',
            'row' => conversationEscalation(['submitter_id' => $colleague->id, 'assignee_id' => $otherAccount->id]),
            'expect' => ['waiting_on' => 'needs_assignment', 'rank' => 0],
        ];
    },
    'a viewer who only submits, owing a reply' => function ($me, $colleague) {
        TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKeyNot($me->id));

        return [
            'row' => conversationOriginal(['submitter_id' => $me->id, 'assignee_id' => $colleague->id, 'turn' => Turn::User]),
            'expect' => ['waiting_on' => 'you_requester', 'marker' => null, 'rank' => 0],
        ];
    },
    'a viewer who only submits, waiting on support, even when escalated' => function ($me, $colleague) {
        TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKeyNot($me->id));
        $escalation = conversationEscalation(['submitter_id' => $colleague->id]);
        conversationMessage($escalation, ActivitySender::Supporter, $colleague->id);

        return [
            'row' => conversationOriginal(['submitter_id' => $me->id, 'assignee_id' => $colleague->id, 'linked_ticket_id' => $escalation->id]),
            'expect' => ['waiting_on' => 'support', 'marker' => null, 'rank' => 2],
        ];
    },
    'New for the owner of an escalation' => function ($me, $colleague, $requester) {
        $escalation = conversationEscalation(['submitter_id' => $me->id]);
        $escalation->childTickets()->save(conversationOriginal(['submitter_id' => $requester->id]));
        conversationMessage($escalation, ActivitySender::Supporter, $colleague->id);

        return ['row' => $escalation, 'expect' => ['is_new' => 1]];
    },
    'not New for the owner once seen' => function ($me, $colleague, $requester) {
        $escalation = conversationEscalation(['submitter_id' => $me->id]);
        $escalation->childTickets()->save(conversationOriginal(['submitter_id' => $requester->id]));
        $seen = conversationMessage($escalation, ActivitySender::Supporter, $colleague->id);
        TicketUserState::query()->create(['ticket_id' => $escalation->id, 'user_id' => $me->id, 'last_seen_activity_id' => $seen->id]);

        return ['row' => $escalation, 'expect' => ['is_new' => 0]];
    },
    'not New on a colleague\'s escalation' => function ($me, $colleague, $requester) {
        $escalation = conversationEscalation(['submitter_id' => $colleague->id]);
        $escalation->childTickets()->save(conversationOriginal(['submitter_id' => $requester->id]));
        conversationMessage($escalation, ActivitySender::Supporter, $me->id);

        return ['row' => $escalation, 'expect' => ['is_new' => 0]];
    },
    'New on my original' => function ($me, $colleague, $requester) {
        $original = conversationOriginal(['submitter_id' => $requester->id, 'assignee_id' => $me->id]);
        conversationMessage($original, ActivitySender::User, $requester->id);

        return ['row' => $original, 'expect' => ['is_new' => 1]];
    },
    'not New on a colleague\'s original' => function ($me, $colleague, $requester) {
        $original = conversationOriginal(['submitter_id' => $requester->id, 'assignee_id' => $colleague->id]);
        conversationMessage($original, ActivitySender::User, $requester->id);

        return ['row' => $original, 'expect' => ['is_new' => 0]];
    },
    'not New on my own ticket' => function ($me) {
        $original = conversationOriginal(['submitter_id' => $me->id, 'assignee_id' => $me->id]);
        conversationMessage($original, ActivitySender::User, $me->id);

        return ['row' => $original, 'expect' => ['is_new' => 0]];
    },
    'New on a received escalation assigned to me' => function ($me, $colleague) {
        $escalation = conversationEscalation(['submitter_id' => $colleague->id, 'assignee_id' => $me->id]);
        conversationMessage($escalation, ActivitySender::User, $colleague->id);

        return ['panel' => 'test2', 'row' => $escalation, 'expect' => ['is_new' => 1]];
    },
]);

it('names who owes the next message', function () {
    $escalation = conversationEscalation(['submitter_id' => $this->me->id, 'assignee_id' => $this->colleague->id]);
    $original = conversationOriginal(['submitter_id' => $this->requester->id, 'assignee_id' => $this->me->id, 'linked_ticket_id' => $escalation->id]);
    conversationMessage($original, ActivitySender::Supporter, $this->me->id);
    $colleagues = conversationOriginal(['submitter_id' => $this->requester->id, 'assignee_id' => $this->colleague->id]);

    expect(ConversationState::for($original))
        ->label()->toBe('You')
        ->color()->toBe('gray')
        ->icon()->toBe('heroicon-m-clock')
        ->tooltip()->toBe('Aisha Brooks has your latest reply. You still owe the answer, which depends on the escalation.')
        ->and(ConversationState::for($escalation))
        ->label()->toBe('Platform Support')
        ->icon()->toBe('heroicon-m-building-office')
        ->tooltip()->toBe('Platform Support owes the next reply. Maria Lopez is working on it.')
        ->and(ConversationState::for($colleagues))
        ->label()->toBe('Maria Lopez')
        ->color()->toBe('gray')
        ->tooltip()->toBe('Maria Lopez owes Aisha Brooks the next reply.');

    $escalation->update(['turn' => Turn::User, 'submitter_id' => $this->colleague->id]);
    $escalation->refresh();

    expect(ConversationState::for($escalation))
        ->label()->toBe('Maria Lopez')
        ->tooltip()->toBe('Maria Lopez owes Platform Support the next reply on this escalation.');

    Filament::setCurrentPanel('test2');
    TicketPlugin::get('test2')->describeTicketOriginUsing(fn () => 'Test Organization');

    expect(ConversationState::for($escalation))
        ->label()->toBe('Contact')
        ->tooltip()->toBe('Maria Lopez at Test Organization owes the next reply.');

    $escalation->update(['turn' => Turn::Supporter, 'assignee_id' => null]);
    $escalation->refresh();

    expect(ConversationState::for($escalation))
        ->label()->toBe('Needs assignment')
        ->color()->toBe('warning')
        ->icon()->toBe('heroicon-m-user-plus')
        ->tooltip()->toBe('Nobody who answers tickets here is assigned. Assign someone to answer Maria Lopez.');
});

it('counts a supporter signed in on another account of someone in a pool matched by email', function () {
    Schema::table('users', fn (Blueprint $table) => $table->dropUnique(['email']));
    $pooled = User::factory()->create(['email' => 'kevin@example.com']);
    $otherAccount = User::factory()->create(['email' => 'kevin@example.com']);

    TicketPlugin::get('test2')->allSupportersQuery(fn () => User::query()->whereKey($pooled->id));
    Filament::setCurrentPanel('test2');
    $this->actingAs($otherAccount);

    expect(ConversationViewer::current()->isSupporter)->toBeFalse();

    Once::flush();
    TicketPlugin::get('test2')->matchSupportersBy('email');

    expect(ConversationViewer::current())
        ->isSupporter->toBeTrue()
        ->supporterPool->toBe(['kevin@example.com'])
        ->and(TicketResource::currentUserIsSupporter($otherAccount->id))->toBeTrue()
        ->and(TicketResource::currentUserIsSupporter($pooled->id))->toBeFalse();
});

it('resolves the supporter pool once, not as a subquery on every row', function () {
    TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKey([$this->me->id, $this->colleague->id]));
    conversationOriginal(['submitter_id' => $this->requester->id, 'assignee_id' => $this->colleague->id]);

    $sql = Ticket::query()->withConversationState()->toSql();
    [$rank] = ConversationStateQuery::rankExpression(ConversationViewer::current());

    expect($sql)->not->toContain('from "users"')
        ->and($rank)->not->toContain('from "users"');
});

it('names nobody rather than leaving a gap when the person handling an escalation cannot be found', function () {
    $escalation = conversationEscalation(['submitter_id' => null]);
    $original = conversationOriginal(['submitter_id' => $this->requester->id, 'assignee_id' => $this->colleague->id, 'linked_ticket_id' => $escalation->id]);
    $waiting = conversationOriginal(['submitter_id' => $this->requester->id, 'assignee_id' => $this->colleague->id, 'linked_ticket_id' => conversationEscalation(['submitter_id' => null])->id]);
    conversationMessage($escalation, ActivitySender::Supporter, $this->colleague->id);

    expect(ConversationState::for($original->load('parentTicket')))
        ->markerLabel()->toBe('Platform Support replied')
        ->markerColor()->toBe('gray')
        ->and(ConversationState::for($waiting->load('parentTicket')))
        ->markerTooltip()->toBe('Your team handles the conversation with Platform Support. Platform Support owes the next reply there.');

    $unowned = conversationEscalation(['submitter_id' => null, 'turn' => Turn::User]);
    $unowned->addTicketActivity(ActivityType::OriginalAdded, ActivitySender::System, $this->me->id);

    expect(ConversationState::for($unowned))
        ->waitingOn->toBe('owner_colleague')
        ->label()->toBe('A colleague')
        ->tooltip()->toBe('A colleague owes Platform Support the next reply on this escalation.');
});

it('matches a supporter\'s email whatever its case', function () {
    $pooled = User::factory()->create(['email' => 'kevin@example.com']);
    $viewer = User::factory()->create(['email' => 'Kevin@Example.com']);

    TicketPlugin::get('test2')
        ->allSupportersQuery(fn () => User::query()->whereKey($pooled->id))
        ->matchSupportersBy('email');
    Filament::setCurrentPanel('test2');
    $this->actingAs($viewer);

    expect(ConversationViewer::current()->isSupporter)->toBeTrue();
});
