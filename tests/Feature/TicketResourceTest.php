<?php

use Livewire\Livewire;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Models\Ticket;

it('lists tickets', function () {
    $ticket = Ticket::factory()->create();

    Livewire::test(ListTickets::class)
        ->assertSee(__('padmission-tickets::tickets.resources.tickets.plural_model_label'))
        ->assertSeeInOrder([
            $ticket->status->display_name,
            $ticket->priority->display_name,
            $ticket->subject,
            $ticket->assignee->name,
        ]);
});

