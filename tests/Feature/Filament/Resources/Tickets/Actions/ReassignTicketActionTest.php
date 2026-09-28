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

it('explains who can be assigned instead of offering an empty list', function () {
    $this->login();
    $ticket = Ticket::factory()->open()->create();

    TicketPlugin::get()
        ->allSupportersQuery(fn () => User::query()->whereRaw('1 = 0'))
        ->assignableUsersDescription('Give a teammate the Helpdesk role to assign them tickets.');

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->mountAction(inlineReassign())
        ->assertMountedActionModalSee([
            __('padmission-tickets::tickets.actions.reassign.nobody_to_assign'),
            'Give a teammate the Helpdesk role to assign them tickets.',
        ])
        ->assertSchemaComponentHidden('assignee_id', 'mountedActionSchema0');
});

it('does not offer the person who already has the ticket', function () {
    $assignee = $this->login();
    $ticket = Ticket::factory()->open()->create(['assignee_id' => $assignee->id]);

    TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKey($assignee->id));

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->mountAction(inlineReassign())
        ->assertMountedActionModalSee(__('padmission-tickets::tickets.actions.reassign.nobody_to_assign'));
});

it('points to the named escalation team only where the ticket can be escalated', function () {
    $this->login();
    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    TicketPlugin::get('test2')->supportTeamName('Platform Support');

    $sentence = __('padmission-tickets::tickets.actions.reassign.modal_description_escalation_to', ['team' => 'Platform Support']);

    $ticket = Ticket::factory()->open()->create(['linked_ticket_id' => null]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->mountAction(inlineReassign())
        ->assertMountedActionModalSee($sentence);

    $escalated = Ticket::factory()->open()->create([
        'linked_ticket_id' => Ticket::factory()->create(['panel' => 'test2'])->id,
    ]);

    Livewire::test(ViewTicket::class, ['record' => $escalated->id])
        ->mountAction(inlineReassign())
        ->assertMountedActionModalDontSee($sentence);

    TicketPlugin::get()->allowLinkedTicketsTo([]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->mountAction(inlineReassign())
        ->assertMountedActionModalDontSee($sentence);
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
    'a colleague handles it' => [false, 'Its escalation to Platform Support stays with Maria Lopez, who gets Platform Support\'s replies. Use Hand over on the escalation to change that.'],
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
