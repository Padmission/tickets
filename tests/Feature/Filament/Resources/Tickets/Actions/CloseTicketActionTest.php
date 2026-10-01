<?php

use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CloseTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketDisposition;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

it('closes ticket', function () {

    (new TicketStatusSeeder)->run();

    $ticketModel = TicketPlugin::resolveModelClass(Ticket::class);
    $ticket = $ticketModel::factory()->create(['status_id' => TicketStatus::getOpenStatuses()->first()->id]);

    $dispositionModel = TicketPlugin::resolveModelClass(TicketDisposition::class);
    $disposition = $dispositionModel::factory()->create();

    $user = $this->login();
    $this->freezeSecond();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionVisible(CloseTicketAction::class)
        ->callAction(CloseTicketAction::class, [
            'disposition' => $disposition->id,
        ])
        ->assertHasNoActionErrors();

    expect($ticket->refresh())
        ->isClosed->toBeTrue()
        ->status->toEqual(TicketStatus::getClosedStatus())
        ->closed_at->toEqual(now())
        ->closed_by->toEqual($user->id)
        ->disposition_id->toEqual($disposition->getKey());
});

it('hides action when ticket is closed', function () {
    (new TicketStatusSeeder)->run();
    $this->login();

    $ticket = Ticket::factory()->closed()->create();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionHidden(CloseTicketAction::class);
});

it('says what closing does before it closes', function () {
    (new TicketStatusSeeder)->run();
    $this->login();

    $ticket = Ticket::factory()->open()->create();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionExists(CloseTicketAction::class, fn (CloseTicketAction $action): bool => ! $action->isModalSlideOver())
        ->mountAction(CloseTicketAction::class)
        ->assertMountedActionModalSee([
            'Close this ticket?',
            'The requester is told it was closed. Anyone who replies to it later is asked whether to reopen it.',
            'Close ticket',
        ]);

    expect($ticket->refresh()->isClosed)->toBeFalse();
});

it('asks for a disposition only when the ticket\'s own panel has one', function () {
    (new TicketStatusSeeder)->run();
    $this->login();

    TicketDisposition::factory()->create(['panel' => 'test2']);
    $ticket = Ticket::factory()->open()->create(['disposition_id' => null]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->mountAction(CloseTicketAction::class)
        ->assertMountedActionModalDontSee(__('padmission-tickets::tickets.actions.close.disposition.label'))
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($ticket->refresh())
        ->isClosed->toBeTrue()
        ->disposition_id->toBeNull();

    TicketDisposition::factory()->create(['panel' => 'test']);
    $second = Ticket::factory()->open()->create();

    Livewire::test(ViewTicket::class, ['record' => $second->id])
        ->callAction(CloseTicketAction::class)
        ->assertHasActionErrors(['disposition' => 'required']);

    expect($second->refresh()->isClosed)->toBeFalse();
});

it('says an open escalation stays open when its original closes', function (bool $viewerIsOwner, string $sentence) {
    (new TicketStatusSeeder)->run();
    $viewer = $this->login();
    TicketPlugin::get('test2')->supportTeamName('Platform Support');

    $owner = $viewerIsOwner ? $viewer : User::factory()->create(['name' => 'Maria Lopez']);
    $escalation = Ticket::factory()->open()->create(['panel' => 'test2', 'submitter_id' => $owner->id]);
    $original = Ticket::factory()->open()->create(['linked_ticket_id' => $escalation->id]);

    Livewire::test(ViewTicket::class, ['record' => $original->id])
        ->mountAction(CloseTicketAction::class)
        ->assertMountedActionModalSee([
            'The requester is told it was closed. Anyone who replies to it later is asked whether to reopen it.',
            $sentence,
        ]);

    $escalation->close(closedById: $viewer->id);

    Livewire::test(ViewTicket::class, ['record' => $original->id])
        ->mountAction(CloseTicketAction::class)
        ->assertMountedActionModalDontSee('Its escalation to Platform Support');
})->with([
    'a colleague handles it' => [false, 'Its escalation to Platform Support stays open. Maria Lopez can close it from the escalation when Platform Support\'s part is done.'],
    'the viewer handles it' => [true, 'Its escalation to Platform Support stays open. You can close it from the escalation when Platform Support\'s part is done.'],
]);

it('tells the team receiving an escalation who is told and what stays open', function (?string $organization, string $contact) {
    (new TicketStatusSeeder)->run();
    $this->login();
    TicketPlugin::get('test2')->allowLinkedTicketsTo(['test']);
    TicketPlugin::get()->describeTicketOriginUsing(fn (): ?string => $organization);

    $escalation = Ticket::factory()->open()->create([
        'source_panel' => 'test2',
        'submitter_id' => User::factory()->create(['name' => 'Test Admin'])->id,
    ]);
    Ticket::factory()->open()->create(['panel' => 'test2', 'linked_ticket_id' => $escalation->id]);

    $page = Livewire::test(ViewTicket::class, ['record' => $escalation->id])
        ->mountAction(CloseTicketAction::class)
        ->assertMountedActionModalSee(['Close escalation?', "{$contact} is told it was closed. The original ticket stays open; Test Admin updates the requester. Anyone who replies to it later is asked whether to reopen it."])
        ->assertMountedActionModalDontSee(['The requester is told it was closed.', 'Close this ticket?']);

    expect($page->instance()->getMountedAction()->getModalSubmitAction()->getLabel())->toBe('Close escalation');

    Ticket::factory()->open()->create(['panel' => 'test2', 'linked_ticket_id' => $escalation->id]);

    Livewire::test(ViewTicket::class, ['record' => $escalation->id])
        ->mountAction(CloseTicketAction::class)
        ->assertMountedActionModalSee("{$contact} is told it was closed. The 2 original tickets stay open; Test Admin updates the requesters.");
})->with([
    'with an organization' => ['Test Organization', 'Test Admin at Test Organization'],
    'without one' => [null, 'Test Admin'],
]);

it('never offers or requires a deleted disposition', function () {
    (new TicketStatusSeeder)->run();
    $this->login();

    $retired = tap(TicketDisposition::factory()->create(['panel' => 'test', 'display_name' => 'Retired outcome']))->delete();
    $ticket = Ticket::factory()->open()->create(['disposition_id' => null]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->mountAction(CloseTicketAction::class)
        ->assertMountedActionModalDontSee(__('padmission-tickets::tickets.actions.close.disposition.label'))
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($ticket->refresh()->isClosed)->toBeTrue();

    $offered = TicketDisposition::factory()->create(['panel' => 'test', 'display_name' => 'Resolved outcome']);
    $second = Ticket::factory()->open()->create(['disposition_id' => null]);

    Livewire::test(ViewTicket::class, ['record' => $second->id])
        ->mountAction(CloseTicketAction::class)
        ->assertFormFieldExists('disposition', fn ($field): bool => array_key_exists($offered->id, $field->getOptions())
            && ! array_key_exists($retired->id, $field->getOptions()));
});
