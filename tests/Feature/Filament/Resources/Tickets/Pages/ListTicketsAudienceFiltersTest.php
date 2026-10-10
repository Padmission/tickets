<?php

use Filament\Facades\Filament;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivitySender;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    (new TicketStatusSeeder)->run();
    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
});

it('shows escalation controls only to the appropriate supporters and tabs', function (string $panel, bool $supporter, string $tab) {
    Filament::setCurrentPanel($panel);
    $this->login();
    if (! $supporter) {
        TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereRaw('1 = 0'));
    }
    $page = Livewire::test(ListTickets::class, ['activeTab' => $tab === 'direct' ? 'all' : $tab]);

    if ($tab === 'direct') {
        $page->set('tableFilters.direct_questions.isActive', true);
    }

    $sending = $supporter && $panel === 'test' && $tab !== 'direct';
    $receiving = $supporter && $panel === 'test2';

    expect($page->instance()->getTable()->getColumn('escalation')->isVisible())->toBe($sending)
        ->and($page->instance()->getTable()->getFilter('escalated', withHidden: true)->isVisible())->toBe($sending)
        ->and($page->instance()->getTable()->getFilter('direct_questions', withHidden: true)->isVisible())->toBe($supporter && $panel === 'test')
        ->and($page->instance()->getTable()->getFilter('kind', withHidden: true)->isVisible())->toBe($receiving)
        ->and($page->instance()->getTable()->getFilter('escalated', withHidden: true)->getLabel())->toBe('Escalated')
        ->and($page->instance()->getTable()->getFilter('kind', withHidden: true)->getLabel())->toBe('Type')
        ->and($page->instance()->getTable()->getFilter('kind', withHidden: true)->getOptions())->toBe(['escalation' => 'Escalation', 'direct' => 'Direct question'])
        ->and($page->instance()->tableFilters['open']['isActive'])->toBeTrue();
})->with(['test', 'test2'])->with([true, false])->with(['all', 'my', 'direct']);

it('hides escalation controls in a panel that does not escalate or receive escalations', function () {
    TicketPlugin::get()->allowLinkedTicketsTo([]);
    $this->login();
    Livewire::test(ListTickets::class)
        ->assertTableColumnHidden('escalation')
        ->assertTableFilterHidden('escalated')
        ->assertTableFilterHidden('direct_questions')
        ->assertTableFilterHidden('kind');
});

it('filters originals with either open or closed escalations alongside open only', function (string $tab) {
    $me = $this->login();
    $original = fn (Ticket $parent, bool $closed = false): Ticket => ($closed ? Ticket::factory()->closed() : Ticket::factory()->open())
        ->create(['assignee_id' => $me->id, 'linked_ticket_id' => $parent->id]);
    $withOpen = $original(Ticket::factory()->open()->create(['panel' => 'test2']));
    $withClosed = $original(Ticket::factory()->closed()->create(['panel' => 'test2']));
    $closedOriginal = $original(Ticket::factory()->closed()->create(['panel' => 'test2']), true);
    $plain = Ticket::factory()->open()->create(['assignee_id' => $me->id]);

    Livewire::test(ListTickets::class, ['activeTab' => $tab])->filterTable('escalated')
        ->assertCanSeeTableRecords([$withOpen, $withClosed])
        ->assertCanNotSeeTableRecords([$plain, $closedOriginal])
        ->removeTableFilter('open')->assertCanSeeTableRecords([$closedOriginal])
        ->removeTableFilter('escalated')->assertCanSeeTableRecords([$plain]);
})->with(['all', 'my']);

it('distinguishes direct questions from escalations by originals and their history', function (string $tab) {
    Filament::setCurrentPanel('test2');
    $me = $this->login();
    $ticket = fn (bool $closed = false): Ticket => ($closed ? Ticket::factory()->closed() : Ticket::factory()->open())
        ->create(['panel' => 'test2', 'source_panel' => 'test', 'assignee_id' => $me->id]);
    $direct = $ticket();
    $direct->addTicketActivity(ActivityType::AskedDirectly, ActivitySender::System);
    $closedDirect = $ticket(true);
    $closedDirect->addTicketActivity(ActivityType::AskedDirectly, ActivitySender::System);
    $linked = $ticket();
    Ticket::factory()->open()->create(['panel' => 'test', 'linked_ticket_id' => $linked->id]);
    $removed = $ticket();
    $removed->addTicketActivity(ActivityType::OriginalAdded, ActivitySender::System);
    $previouslyDirect = $ticket();
    $previouslyDirect->addTicketActivity(ActivityType::AskedDirectly, ActivitySender::System);
    $previouslyDirect->addTicketActivity(ActivityType::OriginalAdded, ActivitySender::System);
    $plain = $ticket();

    Livewire::test(ListTickets::class, ['activeTab' => $tab])->filterTable('kind', 'direct')
        ->assertCanSeeTableRecords([$direct, $plain])
        ->assertCanNotSeeTableRecords([$linked, $removed, $previouslyDirect, $closedDirect])
        ->removeTableFilter('open')->assertCanSeeTableRecords([$closedDirect])
        ->filterTable('kind', 'escalation')
        ->assertCanSeeTableRecords([$linked, $removed, $previouslyDirect])
        ->assertCanNotSeeTableRecords([$direct, $closedDirect, $plain])
        ->removeTableFilter('kind')->assertCanSeeTableRecords([$plain, $direct]);
})->with(['all', 'my']);

it('partitions every receiving ticket between Type options without overlap', function (string $tab, bool $openOnly) {
    Filament::setCurrentPanel('test2');
    $me = $this->login();
    $ticket = fn (array $attributes = []): Ticket => Ticket::factory()->open()->create([
        'panel' => 'test2', 'assignee_id' => $me->id, ...$attributes,
    ]);
    $plain = $ticket();
    $widget = $ticket(['source_panel' => 'test']);
    $asked = $ticket(['source_panel' => 'test']);
    $asked->addTicketActivity(ActivityType::AskedDirectly, ActivitySender::System);
    $deletedOriginal = Ticket::factory()->create(['linked_ticket_id' => $asked->id]);
    $deletedOriginal->delete();
    $linked = $ticket();
    Ticket::factory()->create(['linked_ticket_id' => $linked->id]);
    $history = $ticket();
    $history->addTicketActivity(ActivityType::OriginalAdded, ActivitySender::System);
    $closed = Ticket::factory()->closed()->create(['panel' => 'test2', 'assignee_id' => $me->id]);
    $foreign = Ticket::factory()->create(['assignee_id' => $me->id]);

    $page = Livewire::test(ListTickets::class, ['activeTab' => $tab]);
    if (! $openOnly) {
        $page->removeTableFilter('open');
    }
    $ids = fn (): array => $page->instance()->getTableRecords()->pluck('id')->sort()->values()->all();
    $all = $ids();
    $page->filterTable('kind', 'escalation')->assertCanSeeTableRecords([$linked, $history])
        ->assertCanNotSeeTableRecords([$plain, $widget, $asked, $closed, $foreign]);
    $escalations = $ids();
    $page->filterTable('kind', 'direct')->assertCanSeeTableRecords([$plain, $widget, $asked])
        ->assertCanNotSeeTableRecords([$linked, $history, $foreign]);
    $direct = $ids();

    expect(array_intersect($escalations, $direct))->toBe([])
        ->and(collect([...$escalations, ...$direct])->sort()->values()->all())->toBe($all)
        ->and(in_array($closed->id, $direct))->toBe(! $openOnly);
})->with(['all', 'my'])->with([true, false]);

it('ignores a deleted escalation in both the sending badge and Escalated filter', function (string $tab) {
    $me = $this->login();
    $escalation = Ticket::factory()->open()->create(['panel' => 'test2']);
    $original = Ticket::factory()->open()->create(['assignee_id' => $me->id, 'linked_ticket_id' => $escalation->id]);
    $escalation->delete();

    $page = Livewire::test(ListTickets::class, ['activeTab' => $tab])->assertCanSeeTableRecords([$original]);
    $row = $page->instance()->getTableRecords()->first();
    expect($page->instance()->getTable()->getColumn('escalation')->record($row)->getState())->toBeNull();
    $page->filterTable('escalated')->assertCanNotSeeTableRecords([$original]);

    $escalation->restore();
    $page->refresh()->assertCanSeeTableRecords([$original]);
    $row = $page->instance()->getTableRecords()->first();
    expect($page->instance()->getTable()->getColumn('escalation')->record($row)->getState())->not->toBeNull();
})->with(['all', 'my']);

it('leads from an Escalated row to its conversation with the other team', function () {
    $me = $this->login();
    $escalation = Ticket::factory()->open()->create(['panel' => 'test2']);
    $original = Ticket::factory()->open()->create(['assignee_id' => $me->id, 'linked_ticket_id' => $escalation->id]);

    Livewire::test(ListTickets::class, ['activeTab' => 'my'])->filterTable('escalated')
        ->assertCanSeeTableRecords([$original]);

    Livewire::test(ViewTicket::class, ['record' => $original->id])->callAction('show-linked')
        ->assertSeeHtml('href="'.e(ViewTicket::getUrl(['record' => $escalation, 'linked' => $original->id])).'"');
});
