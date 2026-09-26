<?php

use Filament\Tables\Columns\TextColumn;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Filament\Widgets\OpenTicketsWidget;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

it('lists open tickets whatever status they have, and hides closed ones', function () {
    (new TicketStatusSeeder)->run();
    $this->login();

    $foreignStatus = TicketStatus::factory()->create(['panel' => 'test2']);

    $openWithForeignStatus = Ticket::factory()->create(['status_id' => $foreignStatus->id, 'closed_at' => null]);
    $closed = Ticket::factory()->closed()->create();

    Livewire::test(ListTickets::class)
        ->assertCanSeeTableRecords([$openWithForeignStatus])
        ->assertCanNotSeeTableRecords([$closed])
        ->removeTableFilter('open')
        ->assertCanSeeTableRecords([$openWithForeignStatus, $closed]);
});

it('only offers escalated tabs in a panel that escalates', function () {
    $this->login();

    TicketPlugin::get('test2')->allowLinkedTicketsTo(['test']);

    $tabs = Livewire::test(ListTickets::class)->instance()->getTabs();

    expect(TicketPlugin::get()->hasLinkedTickets())->toBeTrue()
        ->and($tabs)->not->toHaveKeys(['linked', 'my_linked']);
});

it('explains the active tab', function (string $tab, string $key) {
    $this->login();

    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    TicketPlugin::get('test2')->supportTeamName('Platform Support');

    Livewire::test(ListTickets::class, ['activeTab' => $tab])
        ->assertSee(__("padmission-tickets::tickets.resources.tickets.tab_descriptions.{$key}", ['team' => 'Platform Support']));
})->with([
    'all' => ['all', 'all'],
    'my' => ['my', 'my'],
    'escalated' => ['linked', 'linked_to'],
]);

it('tells someone who only submits tickets that the list is theirs, without team-wide counts', function () {
    $this->login();

    TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereRaw('1 = 0'));

    Livewire::test(ListTickets::class)
        ->assertSee(__('padmission-tickets::tickets.resources.tickets.tab_descriptions.all_submitter'))
        ->assertDontSeeLivewire(OpenTicketsWidget::class);
});

it('shows the team-wide counts to supporters', function () {
    $this->login();

    Livewire::test(ListTickets::class)
        ->assertSeeLivewire(OpenTicketsWidget::class);
});

describe('Tab badges', function () {
    beforeEach(function () {
        (new TicketStatusSeeder)->run();
    });

    it('counts my open tickets the same as the navigation badge', function () {
        $user = $this->login();

        Ticket::factory()->open()->count(2)->create(['assignee_id' => $user->id]);
        Ticket::factory()->closed()->create(['assignee_id' => $user->id]);
        Ticket::factory()->open()->create(['assignee_id' => User::factory()->create()->id]);

        $tabs = Livewire::test(ListTickets::class)->instance()->getTabs();

        expect($tabs['my']->getBadge())->toBe('2')
            ->and(TicketResource::getNavigationBadge())->toBe('2')
            ->and(TicketResource::getNavigationBadgeTooltip())->toBe(__('padmission-tickets::tickets.resources.tickets.badges.my'))
            ->and($tabs['all']->getBadge())->toBeNull();
    });

    it('hides the badges when there is nothing open', function () {
        $this->login();
        TicketPlugin::get()->allowLinkedTicketsTo(['test2']);

        $tabs = Livewire::test(ListTickets::class)->instance()->getTabs();

        expect($tabs['my']->getBadge())->toBeNull()
            ->and($tabs['linked']->getBadge())->toBeNull()
            ->and($tabs['my_linked']->getBadge())->toBeNull()
            ->and(TicketResource::getNavigationBadge())->toBeNull();
    });

    it('counts the open escalated tickets on each escalated tab', function () {
        $user = $this->login();
        TicketPlugin::get()->allowLinkedTicketsTo(['test2']);

        $escalate = fn (array $attributes, bool $open = true) => ($open ? Ticket::factory()->open() : Ticket::factory()->closed())
            ->has(Ticket::factory(['panel' => 'test']), 'childTickets')
            ->create(['panel' => 'test2', ...$attributes]);

        $escalate(['submitter_id' => $user->id]);
        $escalate(['submitter_id' => User::factory()->create()->id]);
        $escalate(['submitter_id' => $user->id], open: false);

        $tabs = Livewire::test(ListTickets::class)->instance()->getTabs();

        expect($tabs['linked']->getBadge())->toBe('2')
            ->and($tabs['my_linked']->getBadge())->toBe('1');
    });
});

it('finds a ticket by the number quoted from an email', function (string $prefix) {
    (new TicketStatusSeeder)->run();
    $this->login();

    $wanted = Ticket::factory()->open()->create(['subject' => 'Rent question']);
    $other = Ticket::factory()->open()->create(['subject' => 'Something else']);

    Livewire::test(ListTickets::class)
        ->searchTable($prefix.$wanted->id)
        ->assertCanSeeTableRecords([$wanted])
        ->assertCanNotSeeTableRecords([$other])
        ->searchTable('rent')
        ->assertCanSeeTableRecords([$wanted])
        ->assertCanNotSeeTableRecords([$other]);
})->with(['plain' => '', 'with hash' => '#']);

it('offers the ticket number as a sortable column that is off until chosen', function () {
    (new TicketStatusSeeder)->run();
    $this->login();

    $first = Ticket::factory()->open()->create();
    $second = Ticket::factory()->open()->create();

    Livewire::test(ListTickets::class)
        ->assertTableColumnExists('id', fn (TextColumn $column): bool => $column->isToggleable()
            && $column->isToggledHiddenByDefault()
            && $column->isSortable()
            && $column->getLabel() === 'Ticket #')
        ->assertCanNotRenderTableColumn('id')
        ->toggleAllTableColumns()
        ->assertCanRenderTableColumn('id')
        ->sortTable('id', 'desc')
        ->assertCanSeeTableRecords([$second, $first], inOrder: true)
        ->searchTable('#'.$first->id)
        ->assertCanSeeTableRecords([$first])
        ->assertCanNotSeeTableRecords([$second]);
});
