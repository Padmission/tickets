<?php

use Livewire\Livewire;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CreateLinkedTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    $this->login();
});

it('shows CreateLinkedTicketAction in header when enabled', function () {
    TicketPlugin::get()->allowLinkedTickets();

    $ticket = Ticket::factory()->create();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionVisible(CreateLinkedTicketAction::class);
});

it('hides CreateLinkedTicketAction when linked tickets disabled', function () {
    TicketPlugin::get()->allowLinkedTickets(false);

    $ticket = Ticket::factory()->create();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionHidden(CreateLinkedTicketAction::class);
});

it('shows linked tickets section when feature enabled', function () {
    TicketPlugin::get()->allowLinkedTickets();

    $ticket = Ticket::factory()->create();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])->assertSee(__('padmission-tickets::tickets.resources.tickets.linked_tickets'));
});

it('hides linked tickets section when feature disabled', function () {
    TicketPlugin::get()->allowLinkedTickets(false);

    $ticket = Ticket::factory()->create();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertDontSee(__('padmission-tickets::tickets.resources.tickets.linked_tickets'));
});

it('updates linked ticket relationship via form', function () {
    TicketPlugin::get()->allowLinkedTickets();

    $parentTicket = Ticket::factory()->create();
    $ticket = Ticket::factory()->create(['linked_ticket_id' => null]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->fillForm(['linkedTicket' => $parentTicket->id]);

    expect($ticket->refresh()->linked_ticket_id)->toBe($parentTicket->id);
});

it('updates multiple linked tickets relationship via form', function () {
    TicketPlugin::get()->allowLinkedTickets();

    $parentTicket = Ticket::factory()->create();
    $childTicket1 = Ticket::factory()->create(['linked_ticket_id' => null]);
    $childTicket2 = Ticket::factory()->create(['linked_ticket_id' => null]);

    Livewire::test(ViewTicket::class, ['record' => $parentTicket->id])
        ->fillForm(['linkedTickets' => [$childTicket1->id, $childTicket2->id]]);

    expect($childTicket1->refresh()->linked_ticket_id)->toBe($parentTicket->id);
    expect($childTicket2->refresh()->linked_ticket_id)->toBe($parentTicket->id);
});

it('removes tickets from linked relationship when deselected', function () {
    TicketPlugin::get()->allowLinkedTickets();

    $parentTicket = Ticket::factory()->create();
    $childTicket1 = Ticket::factory()->create(['linked_ticket_id' => $parentTicket->id]);
    $childTicket2 = Ticket::factory()->create(['linked_ticket_id' => $parentTicket->id]);

    // @TODO: Report bug to Filament. `InteractsWithSchema::fillFormDataForTesting()`
    // does not have correct state in afterStateUpdated() hook
    Livewire::test(ViewTicket::class, ['record' => $parentTicket->id])
        ->fillForm(['linkedTickets' => [$childTicket1->id]]);

    expect($childTicket1->refresh()->linked_ticket_id)->toBe($parentTicket->id);
    expect($childTicket2->refresh()->linked_ticket_id)->toBeNull();
})->skip('Filament Testing Bug');

it('clears all linked tickets when form field is emptied', function () {
    TicketPlugin::get()->allowLinkedTickets();

    $parentTicket = Ticket::factory()->create();
    $childTicket1 = Ticket::factory()->create(['linked_ticket_id' => $parentTicket->id]);
    $childTicket2 = Ticket::factory()->create(['linked_ticket_id' => $parentTicket->id]);

    Livewire::test(ViewTicket::class, ['record' => $parentTicket->id])
        ->fillForm(['linkedTickets' => []]);

    expect($childTicket1->refresh()->linked_ticket_id)->toBeNull();
    expect($childTicket2->refresh()->linked_ticket_id)->toBeNull();
})->skip('Filament Testing Bug: Property [$data.linkedTickets] not found on component');

it('displays ticket subject as page heading', function () {
    $ticket = Ticket::factory()->create(['subject' => 'Test Ticket Subject']);

    $component = Livewire::test(ViewTicket::class, ['record' => $ticket->id]);

    $heading = $component->instance()->getHeading();

    expect($heading)->toBeInstanceOf(\Illuminate\Support\HtmlString::class);
    expect((string) $heading)->toBe('Test Ticket Subject');
});

it('has chat section in main content area', function () {
    $ticket = Ticket::factory()->create();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertSee('pad-ti-chat-section');
});

it('shows ticket status and priority', function () {
    $ticket = Ticket::factory()->create();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertSee($ticket->status->display_name)
        ->assertSee($ticket->priority->display_name);
});
