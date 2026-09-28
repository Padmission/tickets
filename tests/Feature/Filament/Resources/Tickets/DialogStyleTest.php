<?php

use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\HandOverEscalationAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\ReassignTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\RemoveFromEscalationAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\ReopenTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Policies\TicketPolicy;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

// A host may slide every action over by default, as Journey does.
beforeEach(function () {
    (new TicketStatusSeeder)->run();
    $this->login();

    Action::configureUsing(fn (Action $action) => $action->slideOver());
});

it('asks Reopen as a centred confirm, on the page and in a row\'s ⋯ menu', function () {
    $ticket = Ticket::factory()->closed()->create();
    $centredConfirm = fn (ReopenTicketAction $action): bool => ! $action->isModalSlideOver() && $action->isConfirmationRequired();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionExists(ReopenTicketAction::class, $centredConfirm);

    Livewire::test(ListTickets::class)
        ->removeTableFilter('open')
        ->assertActionExists(TestAction::make(ReopenTicketAction::class)->table($ticket), $centredConfirm);
});

it('asks Reassign in a centred dialog, beside Assigned to and in a row\'s ⋯ menu', function () {
    $ticket = Ticket::factory()->open()->create();
    $centred = fn (ReassignTicketAction $action): bool => ! $action->isModalSlideOver();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionExists(TestAction::make(ReassignTicketAction::class)->schemaComponent('assignee', schema: 'form'), $centred);

    Livewire::test(ListTickets::class)
        ->assertActionExists(TestAction::make(ReassignTicketAction::class)->table($ticket), $centred);
});

it('asks the bulk Assign and Close in centred dialogs', function () {
    Ticket::factory()->open()->create();

    Livewire::test(ListTickets::class)
        ->assertActionExists(TestAction::make('assign')->table()->bulk(), fn (Action $action): bool => ! $action->isModalSlideOver())
        ->assertActionExists(TestAction::make('close-tickets')->table()->bulk(), fn (Action $action): bool => ! $action->isModalSlideOver() && $action->isConfirmationRequired());
});

it('asks Remove from escalation as a centred confirm', function () {
    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    $escalation = Ticket::factory()->open()->create(['panel' => 'test2']);
    $ticket = Ticket::factory()->open()->create(['linked_ticket_id' => $escalation->id]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionExists(
            TestAction::make(RemoveFromEscalationAction::class)->schemaComponent('escalationActions', schema: 'form'),
            fn (RemoveFromEscalationAction $action): bool => ! $action->isModalSlideOver() && $action->isConfirmationRequired(),
        );
});

it('asks Hand over in a centred dialog, as Reassign', function () {
    Gate::policy(Ticket::class, TicketPolicy::class);
    $owner = $this->login();
    $colleague = User::factory()->create();
    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKey([$owner->id, $colleague->id]));
    $escalation = escalationFrom(attributes: ['submitter_id' => $owner->id]);

    Livewire::test(ViewTicket::class, ['record' => $escalation->id])
        ->assertActionExists(
            TestAction::make(HandOverEscalationAction::class)->schemaComponent('submitter', schema: 'form'),
            fn (HandOverEscalationAction $action): bool => ! $action->isModalSlideOver() && ! $action->isConfirmationRequired(),
        );
});
