<?php

use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
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
