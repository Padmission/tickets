<?php

use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Models\Ticket;

it('lists tickets', function () {
    (new TicketStatusSeeder)->run();
    $this->login();

    $ticket = Ticket::factory()->open()->create();

    Livewire::test(ListTickets::class, ['activeTab' => 'all'])
        ->assertSee(__('padmission-tickets::tickets.resources.tickets.plural_model_label'))
        // Priority is hidden by default; its random name would be found only in the filter's options.
        ->assertSeeInOrder([
            $ticket->status->display_name,
            $ticket->subject,
            $ticket->assignee->name,
        ]);
});

it('sorts by turn then by updated_at', function () {
    $this->login();

    [$ticketA, $ticketB, $ticketC] = Ticket::factory()
        ->count(3)
        ->sequence(
            ['turn' => Turn::User, 'updated_at' => now()],
            ['turn' => Turn::User, 'updated_at' => now()->subDay()],
            ['turn' => Turn::Supporter, 'updated_at' => now()],
        )
        ->create();

    Livewire::test(ListTickets::class)
        ->assertSee(__('padmission-tickets::tickets.resources.tickets.plural_model_label'))
        ->assertSeeInOrder([
            $ticketC->getKey(),
            $ticketA->getKey(),
            $ticketB->getKey(),
        ]);
});
