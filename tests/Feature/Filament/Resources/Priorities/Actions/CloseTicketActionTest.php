<?php

use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\StatusSeeder;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CloseTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Models\Status;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\User;

it('closes ticket', function () {
    (new StatusSeeder)->run(panel: 'test');

    $ticket = Ticket::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user);
    $this->freezeSecond();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionVisible(CloseTicketAction::class)
        ->callAction(CloseTicketAction::class)
        ->assertHasNoActionErrors();

    expect($ticket->refresh())
        ->isClosed->toBeTrue()
        ->status->toEqual(Status::getClosedStatus())
        ->closed_at->toEqual(now())
        ->closed_by->toEqual($user->id);
});

it('hides action when ticket is closed', function () {
    (new StatusSeeder)->run(panel: 'test');
    $ticket = Ticket::factory()->closed()->create();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionHidden(CloseTicketAction::class);
});
