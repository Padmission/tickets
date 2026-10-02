<?php

use Filament\Resources\Events\RecordSaved;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Policies\TicketPolicy;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    (new TicketStatusSeeder)->run();

    Gate::policy(Ticket::class, TicketPolicy::class);

    $this->supporter = User::factory()->create();
    $this->requester = User::factory()->create();
    TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKey($this->supporter->id));

    $this->ticket = Ticket::factory()->open()->create(['submitter_id' => $this->requester->id]);

    Event::fake([RecordSaved::class]);
});

it('refuses the page\'s save to someone who may only view the ticket', function () {
    $this->actingAs($this->requester);

    Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
        ->call('save')
        ->assertForbidden();

    Event::assertNotDispatched(RecordSaved::class);
});

it('still saves for someone who may edit it', function () {
    $this->actingAs($this->supporter);

    Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
        ->call('save')
        ->assertSuccessful();

    Event::assertDispatched(RecordSaved::class);
});
