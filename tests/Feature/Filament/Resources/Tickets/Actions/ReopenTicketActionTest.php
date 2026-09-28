<?php

use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\ReopenTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    (new TicketStatusSeeder)->run();
    $this->login();
});

it('leads a closed ticket\'s page with Reopen, which only asks first', function () {
    $ticket = Ticket::factory()->closed()->create();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionVisible(ReopenTicketAction::class)
        ->assertActionHidden('close-ticket')
        ->mountAction(ReopenTicketAction::class)
        ->assertMountedActionModalSee([
            'Reopen this ticket?',
            'It goes back to its first open status, and the requester can reply to it again.',
            'Reopen ticket',
        ])
        ->callMountedAction()
        ->assertNotified('Ticket reopened')
        ->assertDispatched('ticket-chat-changed', ticketId: $ticket->id, canReply: true);

    expect($ticket->refresh()->isClosed)->toBeFalse();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionHidden(ReopenTicketAction::class)
        ->assertActionVisible('close-ticket');
});

it('offers Reopen in a closed row\'s ⋯ menu only', function () {
    $closed = Ticket::factory()->closed()->create();
    $open = Ticket::factory()->open()->create();

    Livewire::test(ListTickets::class)
        ->removeTableFilter('open')
        ->assertActionHidden(TestAction::make(ReopenTicketAction::class)->table($open))
        ->callAction(TestAction::make(ReopenTicketAction::class)->table($closed))
        ->assertNotified('Ticket reopened');

    expect($closed->refresh()->isClosed)->toBeFalse();
});

it('leaves reopening an escalation to the panel it lives in', function () {
    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    $escalation = escalationFrom(attributes: ['submitter_id' => auth()->id()], state: 'closed');

    Livewire::test(ViewTicket::class, ['record' => $escalation->id])
        ->assertActionHidden(ReopenTicketAction::class);

    Filament::setCurrentPanel('test2');

    Livewire::test(ViewTicket::class, ['record' => $escalation->id])
        ->mountAction(ReopenTicketAction::class)
        ->assertMountedActionModalSee('It goes back to its first open status, and both teams can write on it again.');
});

it('draws the page again when a reply in its chat reopened the ticket, so Close leads the header again', function () {
    $ticket = Ticket::factory()->closed()->create();

    $page = Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertSeeHtml("mountAction('reopen-ticket'")
        ->assertDontSeeHtml("mountAction('close-ticket'");

    $ticket->reopen();

    $page->dispatch('message-sent', reopened: true)
        ->assertSeeHtml("mountAction('close-ticket'")
        ->assertDontSeeHtml("mountAction('reopen-ticket'");
});
