<?php

use Filament\Facades\Filament;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Filament\Tables\LinkedTicketCandidates;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Support\ConversationStateQuery;
use Padmission\Tickets\Support\ConversationViewer;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    (new TicketStatusSeeder)->run();
    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    $this->me = $this->login(User::factory()->create());
});

it('sorts the list by the selected rank instead of a second copy of it', function () {
    $query = TicketResource::orderByRank(Ticket::query()->withConversationState(), 'desc');
    [$rank] = ConversationStateQuery::rankExpression(ConversationViewer::current());

    expect($query->toSql())->toEndWith('order by "conversation_rank" desc')
        ->and(substr_count($query->toSql(), $rank))->toBe(1)
        ->and(TicketResource::orderByRank(Ticket::query(), 'asc')->toSql())->toEndWith("order by {$rank} asc");
});

it('checks relay and hold once per row, not once per branch', function () {
    $viewer = new ConversationViewer(1, [1], true, 'test', false, ['test2'], 'id', [1, 2]);
    [$rank] = ConversationStateQuery::rankExpression($viewer);
    $selects = strtolower(ConversationStateQuery::apply(Ticket::query(), $viewer)->toSql());

    expect(substr_count(strtolower($rank), '(select'))->toBeLessThanOrEqual(9)
        ->and(substr_count($selects, '(select'))->toBeLessThanOrEqual(35);
});

it('lists an escalation from this panel whose original moved to another panel', function () {
    $escalation = Ticket::factory()->open()->create(['panel' => 'test2', 'source_panel' => 'test', 'submitter_id' => $this->me->id, 'turn' => Turn::User]);
    Ticket::factory()->open()->create(['panel' => 'test3', 'linked_ticket_id' => $escalation->id]);
    $original = Ticket::factory()->open()->create(['panel' => 'test']);

    Livewire::test(ListTickets::class, ['activeTab' => 'linked'])
        ->assertCanSeeTableRecords([$escalation->id]);

    expect(LinkedTicketCandidates::openEscalations(Ticket::query(), $original)->pluck('id')->all())->toContain($escalation->id)
        ->and(Ticket::query()->withConversationState()->find($escalation->id)->conversation_waiting_on)->toBe('you_owner');
});

it('matches an assignee to an email pool whatever the case', function () {
    $pooled = User::factory()->create(['email' => 'Kevin@Example.com']);

    TicketPlugin::get('test2')
        ->allSupportersQuery(fn () => User::query()->whereKey([$this->me->id, $pooled->id]))
        ->matchSupportersBy('email');
    Filament::setCurrentPanel('test2');

    $viewer = new ConversationViewer($this->me->id, [$this->me->id], true, 'test2', true, [], 'email', ['KEVIN@example.COM']);
    $ticket = Ticket::factory()->open()->create(['panel' => 'test2', 'assignee_id' => $pooled->id, 'turn' => Turn::Supporter]);

    expect(Ticket::query()->withConversationState($viewer)->find($ticket->id)->conversation_waiting_on)->toBe('colleague');
});
