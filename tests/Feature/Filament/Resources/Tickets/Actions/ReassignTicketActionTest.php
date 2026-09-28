<?php

use Filament\Actions\Testing\TestAction;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\ReassignTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    (new TicketStatusSeeder)->run();
});

it('reassigns the ticket from the ticket page', function () {
    $this->login();
    $teammate = User::factory()->create();
    $ticket = Ticket::factory()->open()->create();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionVisible(inlineReassign())
        ->callAction(inlineReassign(), ['assignee_id' => $teammate->id])
        ->assertHasNoActionErrors()
        ->assertNotified(__('padmission-tickets::tickets.actions.reassign.success'));

    expect($ticket->refresh()->assignee_id)->toEqual($teammate->id);
});

it('reassigns the ticket from the ticket list', function () {
    $this->login();
    $teammate = User::factory()->create();
    $ticket = Ticket::factory()->open()->create();

    Livewire::test(ListTickets::class)
        ->callAction(TestAction::make(ReassignTicketAction::class)->table($ticket), ['assignee_id' => $teammate->id])
        ->assertHasNoActionErrors();

    expect($ticket->refresh()->assignee_id)->toEqual($teammate->id);
});

it('offers to assign the ticket to the current user', function () {
    $user = $this->login();
    $ticket = Ticket::factory()->open()->create(['assignee_id' => User::factory()->create()->id]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->mountAction(inlineReassign())
        ->callAction(TestAction::make('assign-to-me')->schemaComponent('assignee_id'))
        ->assertActionDataSet(['assignee_id' => $user->id]);
});

it('refuses an assignee who is not a supporter', function () {
    $this->login();
    $outsider = User::factory()->create();
    $ticket = Ticket::factory()->open()->create();
    $originalAssignee = $ticket->assignee_id;

    TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKeyNot($outsider->id));

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->callAction(inlineReassign(), ['assignee_id' => $outsider->id]);

    expect($ticket->refresh()->assignee_id)->toEqual($originalAssignee);
});

it('is hidden on a closed ticket', function () {
    $this->login();
    $ticket = Ticket::factory()->closed()->create();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertDontSee(__('padmission-tickets::tickets.actions.reassign.inline_label'));
});

it('is labelled assign when nobody is assigned yet', function () {
    $this->login();
    $ticket = Ticket::factory()->open()->create(['assignee_id' => null]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionHasLabel(inlineReassign(), __('padmission-tickets::tickets.actions.reassign.label_unassigned'));
});

it('looks up supporters for the ticket being reassigned', function () {
    $this->login();
    $teammate = User::factory()->create();
    $ticket = Ticket::factory()->open()->create();

    // Stands in for a host that pins supporters to the ticket's tenant and
    // finds nobody without the ticket, as Journey's tenant panel does for
    // staff who do not belong to that tenant.
    TicketPlugin::get()->allSupportersQuery(fn (?Ticket $ticket = null) => User::query()
        ->when($ticket === null, fn ($query) => $query->whereRaw('1 = 0')));

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->callAction(inlineReassign(), ['assignee_id' => $teammate->id])
        ->assertHasNoActionErrors();

    expect($ticket->refresh()->assignee_id)->toEqual($teammate->id);
});

it('says no one can be assigned yet, and why, with only a way to close', function () {
    $this->login();
    $ticket = Ticket::factory()->open()->create(['assignee_id' => null]);

    TicketPlugin::get()
        ->allSupportersQuery(fn () => User::query()->whereRaw('1 = 0'))
        ->assignableUsersDescription('Give a teammate the Helpdesk role, then assign them here.');

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->mountAction(inlineReassign())
        ->assertMountedActionModalSee(['No one can be assigned yet', 'Give a teammate the Helpdesk role, then assign them here.', 'Close'])
        ->assertMountedActionModalDontSee(['Assign to me', 'Reassign'])
        ->assertSchemaComponentHidden('assignee_id', 'mountedActionSchema0');
});

it('says no one can be assigned when only the person who has it could be', function () {
    $assignee = $this->login();
    $ticket = Ticket::factory()->open()->create(['assignee_id' => $assignee->id]);

    TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKey($assignee->id));

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->mountAction(inlineReassign())
        ->assertMountedActionModalSee('No one can be assigned yet');
});

it('asks who answers the requester of a ticket nobody has yet', function () {
    $this->login();
    $ticket = Ticket::factory()->open()->create(['assignee_id' => null, 'submitter_id' => User::factory()->create(['name' => 'Nina Patel'])->id]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->mountAction(inlineReassign())
        ->assertMountedActionModalSee(['Assign ticket', 'Choose who answers Nina Patel.', 'Assign to'])
        ->assertMountedActionModalDontSee('Reassign');
});

it('hands a ticket from its assignee to a teammate', function () {
    $this->login();
    $ticket = Ticket::factory()->open()->create(['assignee_id' => User::factory()->create(['name' => 'Maria Lopez'])->id]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->mountAction(inlineReassign())
        ->assertMountedActionModalSee(['Reassign ticket', 'Hand this ticket from Maria Lopez to a teammate.', 'Reassign']);
});

it('hands the viewer\'s own ticket from them', function () {
    $viewer = $this->login();
    $ticket = Ticket::factory()->open()->create(['assignee_id' => $viewer->id]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->mountAction(inlineReassign())
        ->assertMountedActionModalSee('Hand this ticket from you to a teammate.');
});

it('offers only Assign to me when the viewer is the one person who can be assigned', function () {
    $viewer = $this->login();
    $ticket = Ticket::factory()->open()->create(['assignee_id' => null]);

    TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKey($viewer->id));

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->mountAction(inlineReassign())
        ->assertMountedActionModalSee(['Assign ticket', 'Assign to me'])
        ->assertSchemaComponentHidden('assignee_id', 'mountedActionSchema0')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect((string) $ticket->refresh()->assignee_id)->toBe((string) $viewer->id);
});

it('says the escalation stays with whoever handles it', function (bool $viewerIsOwner, string $sentence) {
    $viewer = $this->login();
    TicketPlugin::get('test2')->supportTeamName('Platform Support');

    $owner = $viewerIsOwner ? $viewer : User::factory()->create(['name' => 'Maria Lopez']);
    $escalation = Ticket::factory()->open()->create(['panel' => 'test2', 'submitter_id' => $owner->id]);
    $original = Ticket::factory()->open()->create(['linked_ticket_id' => $escalation->id]);

    Livewire::test(ViewTicket::class, ['record' => $original->id])
        ->mountAction(inlineReassign())
        ->assertMountedActionModalSee($sentence);

    $escalation->close(closedById: $viewer->id);

    Livewire::test(ViewTicket::class, ['record' => $original->id])
        ->mountAction(inlineReassign())
        ->assertMountedActionModalDontSee('Its escalation to Platform Support');
})->with([
    'a colleague handles it' => [false, 'Its escalation to Platform Support stays with Maria Lopez.'],
    'the viewer handles it' => [true, 'Its escalation to Platform Support stays with you.'],
]);

it('reassigns from the Assigned to entry, not the page header', function () {
    $this->login();
    $ticket = Ticket::factory()->open()->create();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionHasLabel(inlineReassign(), __('padmission-tickets::tickets.actions.reassign.inline_label'))
        ->assertActionDoesNotExist(ReassignTicketAction::class);
});

it('is not offered to someone who cannot edit the ticket', function () {
    $this->login();
    Gate::policy(Ticket::class, ReadOnlyReassignPolicy::class);
    $ticket = Ticket::factory()->open()->create();

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertDontSee(__('padmission-tickets::tickets.actions.reassign.inline_label'));
});

function inlineReassign(): TestAction
{
    return TestAction::make(ReassignTicketAction::class)->schemaComponent('assignee', schema: 'form');
}

class ReadOnlyReassignPolicy
{
    public function view(): bool
    {
        return true;
    }

    public function update(): bool
    {
        return false;
    }
}

it('is a pencil right after the Assigned to label, named by its tooltip', function (?string $assignee, string $name) {
    $this->login();
    $ticket = Ticket::factory()->open()->create(['assignee_id' => $assignee === null ? null : User::factory()->create()->id]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertActionExists(inlineReassign(), fn (ReassignTicketAction $action): bool => $action->isIconButton()
            && $action->getIcon() === Heroicon::OutlinedPencilSquare
            && $action->getTooltip() === $name)
        ->assertSeeHtml('pad-ti-edit-entry');
})->with([
    'assigned' => ['someone', 'Change'],
    'unassigned' => [null, 'Assign'],
]);
