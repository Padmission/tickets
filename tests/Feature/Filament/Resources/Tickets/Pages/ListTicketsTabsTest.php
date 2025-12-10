<?php

use Filament\Facades\Filament;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

it('shows all and my tickets tab', function () {
    $this->login();

    $component = Livewire::test(ListTickets::class);
    $tabs = $component->instance()->getTabs();

    expect($tabs)->toHaveKeys(['all', 'my']);
    expect($tabs['all']->getLabel())->toBe(__('padmission-tickets::tickets.resources.tickets.tabs.all'));
    expect($tabs['my']->getLabel())->toBe(__('padmission-tickets::tickets.resources.tickets.tabs.my'));
});

it('shows all tickets in "all" tab', function () {
    (new TicketStatusSeeder)->run();

    $user = $this->login();
    $otherUser = User::factory()->create();

    $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);

    $tickets = $ticketModel::factory()
        ->sequence(
            ['assignee_id' => $user->id],
            ['assignee_id' => $otherUser->id],
            ['assignee_id' => null],
        )
        ->count(3)
        ->open()
        ->create([
            'status_id' => TicketStatus::getOpenStatuses()->first()->id,
        ]);

    Livewire::test(ListTickets::class, ['activeTab' => 'all'])
        ->assertTableColumnHidden('panel')
        ->assertCountTableRecords(3)
        ->assertCanSeeTableRecords([$tickets->first()->id]);
});

it('shows my tickets tab filtered by assignee', function () {
    (new TicketStatusSeeder)->run();

    $user = $this->login();
    $otherUser = User::factory()->create();

    $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);

    $tickets = $ticketModel::factory()
        ->sequence(
            ['assignee_id' => $user->id],
            ['assignee_id' => $otherUser->id],
            ['assignee_id' => null],
        )
        ->count(3)
        ->open()
        ->create([
            'status_id' => TicketStatus::getOpenStatuses()->first()->id,
        ]);

    Livewire::test(ListTickets::class, ['activeTab' => 'my'])
        ->assertTableColumnHidden('panel')
        ->assertCountTableRecords(1)
        ->assertCanSeeTableRecords([$tickets->first()->id]);
});

it('hides linked tickets tabs when feature disabled', function () {
    $this->login();

    $component = Livewire::test(ListTickets::class);
    $tabs = $component->instance()->getTabs();

    expect($tabs)->toHaveKeys(['all', 'my']);
    expect($tabs)->not->toHaveKeys(['linked', 'my_linked']);
});

it('shows linked tickets tabs when feature enabled', function () {
    TicketPlugin::get()->allowLinkedTicketsTo(['test']);

    $this->login();

    $component = Livewire::test(ListTickets::class);
    $tabs = $component->instance()->getTabs();

    expect($tabs)->toHaveKeys(['all', 'my', 'linked', 'my_linked']);

    expect($tabs['linked']->getLabel())->toBe(__('padmission-tickets::tickets.resources.tickets.tabs.linked'));
    expect($tabs['my_linked']->getLabel())->toBe(__('padmission-tickets::tickets.resources.tickets.tabs.my_linked'));
});

it('linked tickets only shows tickets that have a child ticket from the current panel', function () {
    TicketPlugin::get()->allowLinkedTicketsTo(['test']);

    (new TicketStatusSeeder)->run();

    $this->login();

    $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);
    $currentPanel = Filament::getCurrentPanel();

    $linkedTicket = $ticketModel::factory()
        ->has(Ticket::factory(['panel' => $currentPanel->getId()]), 'childTickets')
        ->create(['panel' => 'test2']);

    $ticketModel::factory()
        ->has(Ticket::factory(['panel' => 'other-panel']), 'childTickets')
        ->create(['panel' => 'test2']);

    $ticketModel::factory()->create();

    Livewire::test(ListTickets::class, ['activeTab' => 'linked'])
        ->assertTableColumnVisible('panel')
        ->assertTableColumnHidden('source_panel')
        ->assertCountTableRecords(1)
        ->assertCanSeeTableRecords([$linkedTicket->id]);
});

it('filters my linked tickets tab by linked ticket id and submitter', function () {
    TicketPlugin::get()->allowLinkedTicketsTo(['test']);
    (new TicketStatusSeeder)->run();

    $user = $this->login();
    $otherUser = User::factory()->create();

    $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);
    $currentPanel = Filament::getCurrentPanel();

    $linkedTickets = $ticketModel::factory()
        ->has(Ticket::factory(['panel' => $currentPanel->getId()]), 'childTickets')
        ->sequence(
            ['submitter_id' => $user->id],
            ['submitter_id' => $otherUser->id],
        )
        ->create(['panel' => 'test2']);

    Livewire::test(ListTickets::class, ['activeTab' => 'my_linked'])
        ->assertTableColumnVisible('panel')
        ->assertTableColumnHidden('source_panel')
        ->assertCountTableRecords(1)
        ->assertCanSeeTableRecords([$linkedTickets->first()->id]);
});
