<?php

use Filament\Actions\Action;
use Filament\Actions\Events\ActionCalling;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\Events\GateEvaluated;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\AddToEscalationAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CloseEscalationAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CloseTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CreateLinkedTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\HandOverEscalationAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\ReassignTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\RemoveFromEscalationAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketDisposition;
use Padmission\Tickets\Policies\TicketPolicy;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

// Stands in for read-only impersonation, which classifies an action by the abilities it asks after Filament authorized it once.
beforeEach(function () {
    (new TicketStatusSeeder)->run();
    Gate::policy(Ticket::class, TicketPolicy::class);

    $this->supporter = $this->login(User::factory()->create(['name' => 'Tess Support']));
    $this->colleague = User::factory()->create(['name' => 'Maria Lopez']);
    $this->requester = User::factory()->create(['name' => 'Aisha Brooks']);

    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKey([$this->supporter->id, $this->colleague->id]));
    TicketPlugin::get('test2')->supportTeamName('Platform Support');

    $this->asked = collect();

    Event::listen(ActionCalling::class, function (Action $action): void {
        $abilities = collect();
        $listener = function (GateEvaluated $event) use ($abilities): void {
            $abilities->push($event->ability);
        };

        Event::listen(GateEvaluated::class, $listener);
        $response = $action->getAuthorizationResponse();
        Event::forget(GateEvaluated::class);

        $this->asked->put($action->getName(), ['abilities' => $abilities->all(), 'allowed' => $response->allowed()]);

        $action->halt();
    });
});

function authorizedOriginal(array $attributes = []): Ticket
{
    return Ticket::factory()->open()->create([
        'submitter_id' => test()->requester->id,
        'turn' => Turn::Supporter,
        ...$attributes,
    ]);
}

function authorizedEscalation(): Ticket
{
    $escalation = Ticket::factory()->open()->create([
        'panel' => 'test2',
        'source_panel' => 'test',
        'submitter_id' => test()->supporter->id,
        'turn' => Turn::Supporter,
    ]);

    authorizedOriginal(['linked_ticket_id' => $escalation->id]);

    return $escalation;
}

it('asks the Gate again when an action that needs edit is called, not the page\'s memo', function (string $name, Closure $ticket, Closure $action, Closure $data) {
    $record = $ticket();

    Livewire::test(ViewTicket::class, ['record' => $record->id])
        ->callAction($action(), $data());

    expect($this->asked->get($name))->toBe(['abilities' => ['update'], 'allowed' => true]);
})->with([
    'Close ticket' => ['close-ticket', fn () => authorizedOriginal(), fn () => CloseTicketAction::class, fn () => ['disposition' => TicketDisposition::factory()->create()->id]],
    'Reassign' => ['reassign-ticket', fn () => authorizedOriginal(), fn () => TestAction::make(ReassignTicketAction::class)->schemaComponent('assignee', schema: 'form'), fn () => ['assignee_id' => test()->colleague->id]],
    'Escalate' => ['create-linked-ticket', fn () => authorizedOriginal(), fn () => TestAction::make(CreateLinkedTicketAction::class)->schemaComponent('escalationActions', schema: 'form'), fn () => ['subject' => 'Rent is wrong', 'message' => 'Please check the rent.']],
    'Add to escalation' => ['add-to-escalation', fn () => authorizedOriginal(), fn () => TestAction::make(AddToEscalationAction::class)->schemaComponent('escalationActions', schema: 'form'), fn () => ['escalation' => authorizedEscalation()->id]],
    'Remove from escalation' => ['remove-from-escalation', fn () => authorizedOriginal(['linked_ticket_id' => authorizedEscalation()->id]), fn () => TestAction::make(RemoveFromEscalationAction::class)->schemaComponent('escalationActions', schema: 'form'), fn () => []],
]);

it('authorizes Hand over through the handOver ability when it is called', function () {
    $escalation = authorizedEscalation();

    Livewire::test(ViewTicket::class, ['record' => $escalation->id])
        ->callAction(TestAction::make(HandOverEscalationAction::class)->schemaComponent('submitter', schema: 'form'), ['new_owner' => $this->colleague->id]);

    expect($this->asked->get('hand-over-escalation')['abilities'])->toContain('handOver')
        ->and($this->asked->get('hand-over-escalation')['allowed'])->toBeTrue();
});

it('authorizes Close escalation, not only shows it, for whoever handles the escalation', function () {
    $escalation = authorizedEscalation();

    Livewire::test(ViewTicket::class, ['record' => $escalation->id])
        ->callAction(CloseEscalationAction::class);

    expect($this->asked->get('close-escalation')['allowed'])->toBeTrue();

    $this->login($this->colleague);

    expect(CloseEscalationAction::make()->record($escalation)->getAuthorizationResponse()->allowed())->toBeFalse();
});
