<?php

use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\ReassignTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    (new TicketStatusSeeder)->run();
});

it('reassigns the ticket from the ticket page', function () {
    $this->login();
    $teammate = User::factory()->create();
    $ticket = Ticket::factory()->open()->create();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionVisible(ReassignTicketAction::class)
        ->callAction(ReassignTicketAction::class, ['assignee_id' => $teammate->id])
        ->assertHasNoActionErrors()
        ->assertNotified(__('padmission-tickets::tickets.actions.reassign.success'));

    expect($ticket->refresh()->assignee_id)->toEqual($teammate->id);
});

it('reassigns the ticket from the ticket list', function () {
    $this->login();
    $teammate = User::factory()->create();
    $ticket = Ticket::factory()->open()->create();

    Livewire::test(ListTickets::class)
        ->callAction(TestAction::make(ReassignTicketAction::class)->table($ticket), ['assignee_id' => $teammate->id])
        ->assertHasNoActionErrors();

    expect($ticket->refresh()->assignee_id)->toEqual($teammate->id);
});

it('offers to assign the ticket to the current user', function () {
    $user = $this->login();
    $ticket = Ticket::factory()->open()->create(['assignee_id' => User::factory()->create()->id]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->mountAction(ReassignTicketAction::class)
        ->callAction(TestAction::make('assign-to-me')->schemaComponent('assignee_id'))
        ->assertActionDataSet(['assignee_id' => $user->id]);
});

it('refuses an assignee who is not a supporter', function () {
    $this->login();
    $outsider = User::factory()->create();
    $ticket = Ticket::factory()->open()->create();
    $originalAssignee = $ticket->assignee_id;

    TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKeyNot($outsider->id));

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->callAction(ReassignTicketAction::class, ['assignee_id' => $outsider->id]);

    expect($ticket->refresh()->assignee_id)->toEqual($originalAssignee);
});

it('is hidden on a closed ticket', function () {
    $this->login();
    $ticket = Ticket::factory()->closed()->create();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionHidden(ReassignTicketAction::class);
});

it('is labelled assign when nobody is assigned yet', function () {
    $this->login();
    $ticket = Ticket::factory()->open()->create(['assignee_id' => null]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionHasLabel(ReassignTicketAction::class, __('padmission-tickets::tickets.actions.reassign.label_unassigned'));
});
