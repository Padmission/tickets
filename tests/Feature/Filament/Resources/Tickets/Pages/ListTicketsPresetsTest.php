<?php

use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    (new TicketStatusSeeder)->run();
    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    $this->travelTo(now()->setDate(2026, 10, 5)->setTime(14, 0));
});

// The test panel is the organization app; test2 receives its escalations as admin does.
it('defaults supporters to my tickets in every panel and omits the redundant presets', function (string $panel) {
    Filament::setCurrentPanel($panel);
    $me = $this->login();
    $mine = Ticket::factory()->open()->create(['panel' => $panel, 'assignee_id' => $me->id]);
    $other = Ticket::factory()->open()->create(['panel' => $panel, 'assignee_id' => User::factory()->create()->id]);

    $page = Livewire::test(ListTickets::class)
        ->assertSet('activeTab', 'my')
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$other]);

    expect($page->instance()->getDefaultActiveTab())->toBe('my')
        ->and($page->instance()->getWidgetData()['activeTab'])->toBe('my')
        ->and($page->instance()->getCachedTabs())->not->toHaveKey('my_open')->not->toHaveKey('open_linked');
})->with(['app panel' => 'test', 'admin panel' => 'test2']);

it('keeps the requester default on their own submissions', function () {
    $requester = $this->login();
    $supporter = User::factory()->create();
    TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKey($supporter->id));
    $own = Ticket::factory()->open()->create(['submitter_id' => $requester->id, 'assignee_id' => $supporter->id]);
    $other = Ticket::factory()->open()->create(['submitter_id' => $supporter->id]);

    Livewire::test(ListTickets::class)
        ->assertSet('activeTab', 'all')
        ->assertCanSeeTableRecords([$own])
        ->assertCanNotSeeTableRecords([$other]);
});

it('honors explicit tab URLs and deep links over the supporter default', function (string $parameter, string $tab) {
    $this->login();

    Livewire::withQueryParams([$parameter => $tab])->test(ListTickets::class)
        ->assertSet('activeTab', $tab);
})->with(['tab', 'activeTab'])->with(['all', 'needs_reply', 'linked']);

it('falls back from stale tab URLs to the viewer default', function (string $panel, string $parameter, string $tab, bool $isSupporter) {
    Filament::setCurrentPanel($panel);
    $me = $this->login();
    $colleague = User::factory()->create();
    TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKey($isSupporter ? [$me->id, $colleague->id] : [$colleague->id]));
    $visible = Ticket::factory()->open()->create([
        'panel' => $panel,
        'submitter_id' => $me->id,
        'assignee_id' => $isSupporter ? $me->id : $colleague->id,
    ]);
    $hidden = Ticket::factory()->open()->create(['panel' => $panel, 'submitter_id' => $colleague->id, 'assignee_id' => $colleague->id]);

    Livewire::withQueryParams([$parameter => $tab])->test(ListTickets::class)
        ->assertSet('activeTab', $isSupporter ? 'my' : 'all')
        ->assertCanSeeTableRecords([$visible])
        ->assertCanNotSeeTableRecords([$hidden]);
})->with(['app panel' => 'test', 'admin panel' => 'test2'])
    ->with(['tab', 'activeTab'])
    ->with(['my_open', 'open_linked'])
    ->with(['supporter' => true, 'requester' => false]);

it('falls back from invalid Livewire tab updates to the viewer default', function (string $panel, string $tab, bool $isSupporter) {
    Filament::setCurrentPanel($panel);
    $me = $this->login();
    $colleague = User::factory()->create();
    TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKey($isSupporter ? [$me->id, $colleague->id] : [$colleague->id]));
    $visible = Ticket::factory()->open()->create([
        'panel' => $panel,
        'submitter_id' => $me->id,
        'assignee_id' => $isSupporter ? $me->id : $colleague->id,
    ]);
    $hidden = Ticket::factory()->open()->create(['panel' => $panel, 'submitter_id' => $colleague->id, 'assignee_id' => $colleague->id]);
    $foreign = Ticket::factory()->open()->create(['panel' => $panel === 'test' ? 'test2' : 'test', 'submitter_id' => $me->id, 'assignee_id' => $me->id]);

    $page = Livewire::test(ListTickets::class, ['activeTab' => $isSupporter ? 'all' : 'my'])
        ->set('activeTab', $tab)
        ->assertSet('activeTab', $isSupporter ? 'my' : 'all')
        ->assertDispatched('refresh-page')
        ->assertCanSeeTableRecords([$visible])
        ->assertCanNotSeeTableRecords([$hidden, $foreign]);
    expect($page->instance()->getWidgetData()['activeTab'])->toBe($isSupporter ? 'my' : 'all');
})->with(['app panel' => 'test', 'admin panel' => 'test2'])
    ->with(['my_open', 'open_linked', 'unknown_tab'])
    ->with(['supporter' => true, 'requester' => false]);

it('lists each preset with matching badges on both sides and keeps closed history in the original tabs', function (string $panel) {
    Filament::setCurrentPanel($panel);
    $me = $this->login();
    $colleague = User::factory()->create();
    $make = fn (array $attributes): Ticket => Ticket::factory()->open()->create(['panel' => $panel, ...$attributes]);
    $mineReply = $make(['assignee_id' => $me->id, 'turn' => Turn::Supporter]);
    $mineWaiting = $make(['assignee_id' => $me->id, 'turn' => Turn::User]);
    $otherReply = $make(['assignee_id' => $colleague->id, 'turn' => Turn::Supporter]);
    $unassignedReply = $make(['assignee_id' => null, 'turn' => Turn::Supporter]);
    $unassignedWaiting = $make(['assignee_id' => null, 'turn' => Turn::User]);
    $closed = Ticket::factory()->closed()->create(['panel' => $panel, 'assignee_id' => $me->id, 'turn' => Turn::Supporter]);
    $foreign = Ticket::factory()->open()->create(['panel' => $panel === 'test' ? 'test2' : 'test', 'turn' => Turn::Supporter]);
    foreach ([$mineReply, $closed, $foreign] as $ticket) {
        TicketActivity::factory()->create(['ticket_id' => $ticket->id, 'type' => ActivityType::Message, 'sender' => ActivitySender::User, 'created_at' => '2026-10-02 13:59:59']);
    }

    $expected = [
        'needs_reply' => [$mineReply, $otherReply, $unassignedReply],
        'overdue' => [$mineReply],
        'unassigned' => [$unassignedReply, $unassignedWaiting],
        'waiting_on_requester' => [$mineWaiting, $unassignedWaiting],
    ];
    $all = collect([$mineReply, $mineWaiting, $otherReply, $unassignedReply, $unassignedWaiting, $closed, $foreign]);
    foreach ($expected as $tab => $tickets) {
        $page = Livewire::test(ListTickets::class)->set('activeTab', $tab)->removeTableFilter('open');
        $page->assertCanSeeTableRecords($tickets)
            ->assertCanNotSeeTableRecords($all->whereNotIn('id', collect($tickets)->pluck('id')))
            ->assertCountTableRecords(count($tickets));
        $tabDefinition = $page->instance()->getCachedTabs()[$tab];
        expect($tabDefinition->getBadge())->toBe((string) count($tickets))
            ->and($tabDefinition->getLabel())->not->toStartWith('padmission-tickets::')
            ->and($page->instance()->getSubheading())->not->toBeNull()
            ->and($page->instance()->getTable()->getEmptyStateHeading())->not->toBeNull();
    }

    Livewire::test(ListTickets::class)->set('activeTab', 'all')->removeTableFilter('open')->assertCanSeeTableRecords([$closed]);
    Livewire::test(ListTickets::class)->set('activeTab', 'my')->removeTableFilter('open')->assertCanSeeTableRecords([$closed]);
})->with(['organization' => 'test', 'receiving team' => 'test2']);

it('extends sent escalation tabs with an overdue subset and preserves ownership and history', function () {
    $me = $this->login();
    $colleague = User::factory()->create();
    $open = escalationFrom(attributes: ['submitter_id' => $me->id, 'turn' => Turn::Supporter]);
    $waiting = escalationFrom(attributes: ['submitter_id' => $colleague->id, 'turn' => Turn::User]);
    $closed = escalationFrom(attributes: ['submitter_id' => $me->id], state: 'closed');
    $foreign = escalationFrom('test3');
    foreach ([$open, $closed, $foreign] as $ticket) {
        TicketActivity::factory()->create(['ticket_id' => $ticket->id, 'type' => ActivityType::Message, 'sender' => ActivitySender::User, 'created_at' => '2026-10-02 13:59:59']);
    }

    $page = Livewire::test(ListTickets::class)->set('activeTab', 'linked');
    $page->assertCanSeeTableRecords([$open, $waiting])->assertCanNotSeeTableRecords([$closed, $foreign]);
    expect($page->instance()->getCachedTabs()['linked']->getBadge())->toBe('2');
    $page->set('activeTab', 'overdue_linked')->assertCanSeeTableRecords([$open])->assertCanNotSeeTableRecords([$waiting, $closed, $foreign]);
    expect($page->instance()->getCachedTabs()['overdue_linked']->getBadge())->toBe('1');
    $page->set('activeTab', 'linked')->removeTableFilter('open')->assertCanSeeTableRecords([$open, $waiting, $closed]);
    $page->set('activeTab', 'my_linked')->assertCanSeeTableRecords([$open, $closed])->assertCanNotSeeTableRecords([$waiting]);
});

it('shows only received open escalations in the receiving panel preset', function () {
    $this->login();
    $open = escalationFrom();
    $closed = escalationFrom(state: 'closed');
    $direct = escalationFrom('test', state: 'open');
    $direct->ticketActivities()->where('type', ActivityType::OriginalAdded)->update(['type' => ActivityType::AskedDirectly]);
    $ordinary = Ticket::factory()->open()->create(['panel' => 'test2']);
    Filament::setCurrentPanel('test2');

    $page = Livewire::test(ListTickets::class)->set('activeTab', 'open_escalations')->removeTableFilter('open');
    $page->assertCanSeeTableRecords([$open, $direct])->assertCanNotSeeTableRecords([$closed, $ordinary]);
    expect($page->instance()->getCachedTabs()['open_escalations']->getBadge())->toBe('2');
});

it('applies host ticket query scopes to presets and their badge counts', function () {
    $me = $this->login();
    $visible = Ticket::factory()->open()->create(['assignee_id' => $me->id, 'turn' => Turn::Supporter]);
    $hidden = Ticket::factory()->open()->create(['assignee_id' => $me->id, 'turn' => Turn::Supporter]);
    TicketPlugin::get()->customizeTicketQuery(fn ($query) => $query->whereKey($visible->id));

    $page = Livewire::test(ListTickets::class)->set('activeTab', 'needs_reply');
    $page->assertCanSeeTableRecords([$visible])->assertCanNotSeeTableRecords([$hidden]);
    expect($page->instance()->getCachedTabs()['needs_reply']->getBadge())->toBe('1');
});

it('does not offer support presets or sent escalation presets to requesters', function () {
    $requester = $this->login();
    $supporter = User::factory()->create();
    TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKey($supporter->id));
    $own = Ticket::factory()->open()->create(['submitter_id' => $requester->id]);
    $other = Ticket::factory()->open()->create(['submitter_id' => $supporter->id]);
    $page = Livewire::test(ListTickets::class);
    expect(array_keys($page->instance()->getCachedTabs()))->toBe(['all', 'my']);
    $page->set('activeTab', 'needs_reply')->assertCanSeeTableRecords([$own])->assertCanNotSeeTableRecords([$other]);
});

it('updates the reply presets using the existing turn state and its history', function () {
    $this->login();
    $ticket = Ticket::factory()->open()->create(['turn' => Turn::Supporter]);
    Livewire::test(ListTickets::class)->set('activeTab', 'needs_reply')->assertCanSeeTableRecords([$ticket]);

    $ticket->update(['turn' => Turn::User]);
    $ticket->addTicketActivity(ActivityType::TurnChanged, ActivitySender::System, data: ['from' => Turn::Supporter->value, 'to' => Turn::User->value]);
    Livewire::test(ListTickets::class)->set('activeTab', 'needs_reply')->assertCanNotSeeTableRecords([$ticket]);
    Livewire::test(ListTickets::class)->set('activeTab', 'waiting_on_requester')->assertCanSeeTableRecords([$ticket]);
});

it('draws all four local preset badges with one SQL aggregate', function () {
    $this->login();
    Ticket::factory()->open()->count(3)->create();
    $page = app('livewire')->new(ListTickets::class);
    $tabs = $page->getCachedTabs();
    DB::enableQueryLog();
    DB::flushQueryLog();
    foreach (['needs_reply', 'overdue', 'unassigned', 'waiting_on_requester'] as $tab) {
        $tabs[$tab]->getBadge();
    }
    $queries = collect(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries->filter(fn (array $query): bool => str_contains($query['query'], 'as needs_reply')))->toHaveCount(1)
        ->and($queries->filter(fn (array $query): bool => str_contains($query['query'], 'select "tickets".*')))->toHaveCount(0);
});
