<?php

use Filament\Facades\Filament;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    (new TicketStatusSeeder)->run();
    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    $this->travelTo(now()->setDate(2026, 10, 5)->setTime(14, 0));
});

// The test panel is the organization app; test2 receives its escalations as admin does.
it('defaults supporters to my tickets with only the original tabs in every panel', function (string $panel) {
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
        ->and(array_keys($page->instance()->getCachedTabs()))->toBe($panel === 'test' ? ['all', 'my', 'linked', 'my_linked'] : ['all', 'my']);
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
})->with(['tab', 'activeTab'])->with(['all', 'my', 'linked', 'my_linked']);

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
    ->with(['my_open', 'open_linked', 'needs_reply', 'overdue', 'unassigned', 'waiting_on_requester', 'open_escalations', 'overdue_linked'])
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
    ->with(['my_open', 'open_linked', 'unknown_tab', 'needs_reply', 'overdue', 'unassigned', 'waiting_on_requester', 'open_escalations', 'overdue_linked'])
    ->with(['supporter' => true, 'requester' => false]);
