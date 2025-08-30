<?php

use Filament\Facades\Filament;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketPrioritySeeder;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CreateLinkedTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    $this->login();
});

it('is visible when linked tickets enabled and ticket has no parent', function () {
    TicketPlugin::get()->allowLinkedTickets();

    $ticket = Ticket::factory()->create(['linked_ticket_id' => null]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionVisible(CreateLinkedTicketAction::class);
});

it('is hidden when linked tickets disabled', function () {
    TicketPlugin::get()->allowLinkedTickets(false);

    $ticket = Ticket::factory()->create(['linked_ticket_id' => null]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionHidden(CreateLinkedTicketAction::class);
});

it('is hidden when ticket already has parent', function () {
    TicketPlugin::get()->allowLinkedTickets();

    $parentTicket = Ticket::factory()->create();
    $childTicket = Ticket::factory()->create(['linked_ticket_id' => $parentTicket->id]);

    Livewire::test(ViewTicket::class, ['record' => $childTicket->id])
        ->assertActionHidden(CreateLinkedTicketAction::class);
});

it('creates linked ticket successfully', function () {
    (new TicketStatusSeeder)->run();
    (new TicketPrioritySeeder)->run();

    TicketPlugin::get()->allowLinkedTickets();

    $originalTicket = Ticket::factory()->create(['linked_ticket_id' => null]);
    $currentPanel = Filament::getCurrentOrDefaultPanel()->getId();

    Livewire::test(ViewTicket::class, ['record' => $originalTicket->id])
        ->callAction(CreateLinkedTicketAction::class, [
            'subject' => 'Linked Test Ticket',
            'message' => ['Message'],
        ])
        ->assertHasNoFormErrors();

    expect(Ticket::count())->toBe(2);

    $newTicket = Ticket::where('subject', 'Linked Test Ticket')->first();

    expect($newTicket)
        ->panel->toBe($currentPanel)
        ->source_panel->toBe($currentPanel)
        ->subject->toBe('Linked Test Ticket')
        ->submitter_id->toBe(auth()->id())
        ->turn->toBe(Turn::Supporter)
        ->status_id->toBe(1)
        ->priority_id->toBe(1);

    expect($originalTicket->refresh())
        ->linked_ticket_id->toBe($newTicket->id);
});

it('creates linked ticket for different panel', function () {
    TicketPlugin::get()->allowLinkedTickets();

    $originalTicket = Ticket::factory()->create(['linked_ticket_id' => null]);

    Livewire::test(ViewTicket::class, ['record' => $originalTicket->id])
        ->callAction(CreateLinkedTicketAction::class, [
            'subject' => 'Cross-Panel Ticket',
            'message' => ['Message'],
        ])
        ->assertHasNoFormErrors();

    $newTicket = Ticket::where('subject', 'Cross-Panel Ticket')->first();

    expect($newTicket)
        ->not->toBeNull()
        ->source_panel->toBe(Filament::getCurrentOrDefaultPanel()->getId());
});

it('updates livewire data after creation', function () {
    TicketPlugin::get()->allowLinkedTickets();

    $originalTicket = Ticket::factory()->create(['linked_ticket_id' => null]);

    $component = Livewire::test(ViewTicket::class, ['record' => $originalTicket->id])
        ->callAction(CreateLinkedTicketAction::class, [
            'subject' => 'Data Update Test',
            'message' => ['Message'],
        ])
        ->assertHasNoFormErrors();

    $newTicket = Ticket::where('subject', 'Data Update Test')->first();
    expect($component->get('data.linkedTicket'))->toBe($newTicket->id);
});

it('sends success notification with action link', function () {
    TicketPlugin::get()->allowLinkedTickets();

    $originalTicket = Ticket::factory()->create(['linked_ticket_id' => null]);

    Livewire::test(ViewTicket::class, ['record' => $originalTicket->id])
        ->callAction(CreateLinkedTicketAction::class, [
            'subject' => 'Notification Test',
            'message' => ['Message'],
        ])
        ->assertHasNoFormErrors()
        ->assertNotified();
});

it('requires subject field', function () {
    TicketPlugin::get()->allowLinkedTickets();

    $ticket = Ticket::factory()->create(['linked_ticket_id' => null]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->callAction(CreateLinkedTicketAction::class, [
            'subject' => '',
            'message' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Message without subject']]]]],
        ])
        ->assertHasFormErrors(['subject']);
});

it('requires message field', function () {
    TicketPlugin::get()->allowLinkedTickets();

    $ticket = Ticket::factory()->create(['linked_ticket_id' => null]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->callAction(CreateLinkedTicketAction::class, [
            'subject' => 'Subject without message',
            'message' => null,
        ])
        ->assertHasFormErrors(['message']);
});
