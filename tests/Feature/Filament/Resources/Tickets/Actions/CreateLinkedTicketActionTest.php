<?php

use Filament\Facades\Filament;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketPrioritySeeder;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CreateLinkedTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    $this->login();
});

it('is visible when linked tickets enabled and ticket has no parent', function () {
    TicketPlugin::get()->allowLinkedTickets(only: ['test']);

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
    TicketPlugin::get()->allowLinkedTickets(only: ['test']);

    $parentTicket = Ticket::factory()->create();
    $childTicket = Ticket::factory()->create(['linked_ticket_id' => $parentTicket->id]);

    Livewire::test(ViewTicket::class, ['record' => $childTicket->id])
        ->assertActionHidden(CreateLinkedTicketAction::class);
});

it('sets default subject', function () {
    TicketPlugin::get()->allowLinkedTickets(only: ['test']);

    $originalTicket = Ticket::factory()->create();

    Livewire::test(ViewTicket::class, ['record' => $originalTicket->id])
        ->mountAction(CreateLinkedTicketAction::class)
        ->assertSchemaComponentStateSet('subject', $originalTicket->subject);
});

it('creates linked ticket successfully', function () {
    (new TicketStatusSeeder)->run();
    (new TicketPrioritySeeder)->run();

    TicketPlugin::get()->allowLinkedTickets(only: ['test']);

    $originalTicket = Ticket::factory()->create(['linked_ticket_id' => null]);
    $currentPanel = Filament::getCurrentOrDefaultPanel()->getId();

    $messageContent = 'This is the initial message for the linked ticket';

    Livewire::test(ViewTicket::class, ['record' => $originalTicket->id])
        ->callAction(CreateLinkedTicketAction::class, [
            'subject' => 'Linked Test Ticket',
            'message' => tiptapDocument($messageContent),
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
        ->status_id->toBe(TicketStatus::getOpenStatuses()->first()->id)
        ->priority_id->toBe(1);

    expect($originalTicket->refresh())
        ->linked_ticket_id->toBe($newTicket->id);

    // Verify the message was persisted as a ticket activity
    $messageActivity = $newTicket->ticketActivities()
        ->where('type', ActivityType::Message)
        ->first();

    expect($messageActivity)
        ->not->toBeNull()
        ->content->toContain($messageContent)
        ->user_id->toBe(auth()->id());
});

it('creates linked ticket for different panel', function () {
    TicketPlugin::get()->allowLinkedTickets(only: ['test']);

    $originalTicket = Ticket::factory()->create(['linked_ticket_id' => null]);

    $messageContent = 'Cross-panel message content';

    Livewire::test(ViewTicket::class, ['record' => $originalTicket->id])
        ->callAction(CreateLinkedTicketAction::class, [
            'subject' => 'Cross-Panel Ticket',
            'message' => tiptapDocument($messageContent),
        ])
        ->assertHasNoFormErrors();

    $newTicket = Ticket::where('subject', 'Cross-Panel Ticket')->first();

    expect($newTicket)
        ->not->toBeNull()
        ->source_panel->toBe(Filament::getCurrentOrDefaultPanel()->getId());

    // Verify message persistence for cross-panel ticket
    expect($newTicket->ticketActivities()->where('type', ActivityType::Message)->first())
        ->not->toBeNull()
        ->content->toContain($messageContent);
});

it('updates livewire data after creation', function () {
    TicketPlugin::get()->allowLinkedTickets(only: ['test']);

    $originalTicket = Ticket::factory()->create(['linked_ticket_id' => null]);

    $component = Livewire::test(ViewTicket::class, ['record' => $originalTicket->id])
        ->callAction(CreateLinkedTicketAction::class, [
            'subject' => 'Data Update Test',
            'message' => tiptapDocument('Test message for data update'),
        ])
        ->assertHasNoFormErrors();

    $newTicket = Ticket::where('subject', 'Data Update Test')->first();
    expect($component->get('data.linkedTicket'))->toBe($newTicket->id);
});

it('sends success notification with action link', function () {
    TicketPlugin::get()->allowLinkedTickets(only: ['test']);

    $originalTicket = Ticket::factory()->create(['linked_ticket_id' => null]);

    Livewire::test(ViewTicket::class, ['record' => $originalTicket->id])
        ->callAction(CreateLinkedTicketAction::class, [
            'subject' => 'Notification Test',
            'message' => tiptapDocument('Notification test message'),
        ])
        ->assertHasNoFormErrors()
        ->assertNotified();
});

it('requires subject field', function () {
    TicketPlugin::get()->allowLinkedTickets(only: ['test']);

    $ticket = Ticket::factory()->create(['linked_ticket_id' => null]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->callAction(CreateLinkedTicketAction::class, [
            'subject' => '',
            'message' => tiptapDocument('Message without subject'),
        ])
        ->assertHasFormErrors(['subject']);
});

it('requires message field', function () {
    TicketPlugin::get()->allowLinkedTickets(only: ['test']);

    $ticket = Ticket::factory()->create(['linked_ticket_id' => null]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->callAction(CreateLinkedTicketAction::class, [
            'subject' => 'Subject without message',
            'message' => tiptapDocument('<p></p>'),
        ])
        ->assertHasFormErrors(['message']);
});
