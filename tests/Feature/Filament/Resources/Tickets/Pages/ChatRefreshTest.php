<?php

use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketPrioritySeeder;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\AddToEscalationAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CloseTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CreateLinkedTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\EditTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\ReassignTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketDisposition;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Policies\TicketPolicy;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

/*
 * The chat on the ticket page keeps its own state behind wire:ignore, so the
 * page tells it after an action, rather than leave the reply box to its poll.
 */
beforeEach(function () {
    (new TicketStatusSeeder)->run();
    (new TicketPrioritySeeder)->run();
    Gate::policy(Ticket::class, TicketPolicy::class);

    $this->supporter = $this->login(User::factory()->create(['name' => 'Tess Support']));
    $this->colleague = User::factory()->create(['name' => 'Maria Lopez']);
    $this->requester = User::factory()->create(['name' => 'Aisha Brooks']);

    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKey([$this->supporter->id, $this->colleague->id]));
    TicketPlugin::get('test2')->supportTeamName('Platform Support');

    $this->ticket = Ticket::factory()->open()->create([
        'submitter_id' => $this->requester->id,
        'assignee_id' => $this->supporter->id,
        'turn' => Turn::Supporter,
    ]);
});

it('listens for the page on the chat', function () {
    Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
        ->assertSeeHtml("window.addEventListener('ticket-chat-changed'");
});

it('tells the chat after Close that the viewer may no longer reply', function () {
    Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
        ->callAction(CloseTicketAction::class, ['disposition' => TicketDisposition::factory()->create()->id])
        ->assertHasNoActionErrors()
        ->assertDispatched('ticket-chat-changed', canReply: false);

    expect($this->ticket->refresh()->isClosed)->toBeTrue();
});

it('tells the chat after a status change through Edit closes the ticket that the viewer may no longer reply', function () {
    Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
        ->callAction(EditTicketAction::class, [
            'status_id' => TicketStatus::getClosedStatus()->id,
            'priority_id' => TicketPriority::query()->value('id'),
        ])
        ->assertHasNoActionErrors()
        ->assertDispatched('ticket-chat-changed', canReply: false);

    expect($this->ticket->refresh()->isClosed)->toBeTrue();
});

it('tells the chat after an action that changes who it waits on or writes to the requester', function (Closure $action, Closure $data) {
    Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
        ->callAction($action(), $data())
        ->assertHasNoActionErrors()
        ->assertDispatched('ticket-chat-changed', canReply: true);
})->with([
    'Reassign away from the viewer' => [fn () => TestAction::make(ReassignTicketAction::class)->schemaComponent('assignee', schema: 'form'), fn () => ['assignee_id' => test()->colleague->id]],
    'Escalate' => [fn () => TestAction::make(CreateLinkedTicketAction::class)->schemaComponent('escalationActions', schema: 'form'), fn () => ['subject' => 'Rent is wrong', 'message' => '<p>Please check the rent.</p>']],
    'Add to an existing escalation' => [fn () => TestAction::make(AddToEscalationAction::class)->schemaComponent('escalationActions', schema: 'form'), fn () => ['escalation' => escalationFrom(attributes: ['submitter_id' => test()->supporter->id])->id]],
]);
