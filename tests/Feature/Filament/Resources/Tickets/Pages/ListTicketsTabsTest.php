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
    TicketPlugin::get()->allowLinkedTickets();

    $this->login();

    $component = Livewire::test(ListTickets::class);
    $tabs = $component->instance()->getTabs();

    expect($tabs)->toHaveKeys(['all', 'my', 'linked', 'my_linked']);

    expect($tabs['linked']->getLabel())->toBe(__('padmission-tickets::tickets.resources.tickets.tabs.linked'));
    expect($tabs['my_linked']->getLabel())->toBe(__('padmission-tickets::tickets.resources.tickets.tabs.my_linked'));
});

it('filters linked tickets tab by linked ticket id', function () {
    TicketPlugin::get()->allowLinkedTickets();
    (new TicketStatusSeeder)->run();

    $this->login();

    $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);
    $currentPanel = Filament::getCurrentPanel();

    $parentTicket = $ticketModel::factory()->create();

    $linkedTickets = $ticketModel::factory()
        ->sequence(
            ['source_panel' => $currentPanel->getId(), 'linked_ticket_id' => $parentTicket->id],
            ['source_panel' => $currentPanel->getId(), 'linked_ticket_id' => null],
            ['source_panel' => 'other-panel', 'linked_ticket_id' => $parentTicket->id],
        )
        ->open()
        ->count(3)
        ->create();

    Livewire::test(ListTickets::class, ['activeTab' => 'linked'])
        ->assertCountTableRecords(1)
        ->assertCanSeeTableRecords([$linkedTickets->first()->id]);
});

it('filters my linked tickets tab by linked ticket id and submitter', function () {
    TicketPlugin::get()->allowLinkedTickets();
    (new TicketStatusSeeder)->run();

    $user = $this->login();
    $otherUser = User::factory()->create();

    $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);
    $currentPanel = Filament::getCurrentPanel();

    $parentTicket = $ticketModel::factory()->create();

    $linkedTickets = $ticketModel::factory()
        ->sequence(
            ['source_panel' => $currentPanel->getId(), 'linked_ticket_id' => $parentTicket->id, 'submitter_id' => $user->id],
            ['source_panel' => $currentPanel->getId(), 'linked_ticket_id' => $parentTicket->id, 'submitter_id' => $otherUser->id],
        )
        ->open()
        ->count(2)
        ->create();

    Livewire::test(ListTickets::class, ['activeTab' => 'my_linked'])
        ->assertCountTableRecords(1)
        ->assertCanSeeTableRecords([$linkedTickets->first()->id]);
});
