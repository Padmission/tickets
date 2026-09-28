<?php

use Filament\Actions\Testing\TestAction;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketPrioritySeeder;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Events\TicketAssignedEvent;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\EditTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\ReassignTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

describe('In a panel that lifts the tenant scope', function () {
    beforeEach(function () {
        foreach (['tickets', 'ticket_statuses', 'ticket_priorities'] as $table) {
            Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unsignedBigInteger('tenant_id')->nullable());
        }

        config()->set('padmission-tickets.tenancy.enabled', true);

        foreach ([1 => 'Own', 2 => 'Foreign'] as $tenant => $name) {
            $this->statuses[$tenant] = [
                'open' => TicketStatus::factory()->create(['tenant_id' => $tenant, 'panel' => 'test', 'order' => 1, 'display_name' => "{$name} Open"]),
                'closed' => TicketStatus::factory()->create(['tenant_id' => $tenant, 'panel' => 'test', 'order' => 2, 'display_name' => "{$name} Closed"]),
            ];
            $this->priorities[$tenant] = TicketPriority::factory()->create(['tenant_id' => $tenant, 'panel' => 'test', 'display_name' => "{$name} Urgent"]);
        }

        TicketStatus::addGlobalScope('viewer-tenant', fn ($query) => $query->where('ticket_statuses.tenant_id', 2));
        TicketPriority::addGlobalScope('viewer-tenant', fn ($query) => $query->where('ticket_priorities.tenant_id', 2));
        TicketPlugin::get()->modifyRelationshipScopes(fn ($relation) => $relation->withoutGlobalScope('viewer-tenant'));

        $this->login();

        $this->ticket = Ticket::factory()->create([
            'tenant_id' => 1,
            'status_id' => $this->statuses[1]['open']->id,
            'priority_id' => $this->priorities[1]->id,
        ]);
    });

    afterEach(function () {
        TicketStatus::clearBootedModels();
    });

    it('offers only the ticket tenant\'s statuses and priorities', function () {
        Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
            ->mountAction(EditTicketAction::class)
            ->assertMountedActionModalSee(['Own Open', 'Own Closed', 'Own Urgent'])
            ->assertMountedActionModalDontSee(['Foreign Open', 'Foreign Closed', 'Foreign Urgent']);
    });

    it('refuses another tenant\'s status or priority', function () {
        Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
            ->callAction(EditTicketAction::class, [
                'status_id' => $this->statuses[2]['closed']->id,
                'priority_id' => $this->priorities[2]->id,
            ])
            ->assertHasActionErrors(['status_id', 'priority_id']);

        expect($this->ticket->refresh())
            ->status_id->toBe($this->statuses[1]['open']->id)
            ->priority_id->toBe($this->priorities[1]->id);
    });

    it('closes the ticket with its own tenant\'s closed status', function () {
        Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
            ->callAction(EditTicketAction::class, [
                'status_id' => $this->statuses[1]['closed']->id,
                'priority_id' => $this->priorities[1]->id,
            ])
            ->assertHasNoActionErrors();

        expect($this->ticket->refresh())
            ->isClosed->toBeTrue()
            ->status_id->toBe($this->statuses[1]['closed']->id);
    });
});

/**
 * @param  array<string, mixed>  $data
 * @return array<string, mixed>
 */
function editing(Ticket $ticket, array $data): array
{
    return ['status_id' => $ticket->status_id, 'priority_id' => $ticket->priority_id, 'subject' => $ticket->subject, 'assignee_id' => $ticket->assignee_id, ...$data];
}

describe('Subject and Assigned to', function () {
    beforeEach(function () {
        (new TicketStatusSeeder)->run();
        (new TicketPrioritySeeder)->run();

        $this->supporter = $this->login(User::factory()->create(['name' => 'Tess Support']));
        $this->colleague = User::factory()->create(['name' => 'Maria Lopez']);
        $this->outsider = User::factory()->create(['name' => 'Aisha Brooks']);
        TicketPlugin::get()->allSupportersQuery(fn () => User::query()->whereKey([$this->supporter->id, $this->colleague->id]));

        $this->ticket = Ticket::factory()->open()->create(['subject' => 'Rent is wrong', 'assignee_id' => $this->supporter->id]);
    });

    it('changes the subject and says so in the history', function () {
        Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
            ->callAction(EditTicketAction::class, editing($this->ticket, ['subject' => 'Rent < 200 on the recert']))
            ->assertHasNoActionErrors();

        $note = $this->ticket->ticketActivities()->where('type', ActivityType::SubjectChanged)->sole();

        expect($this->ticket->refresh()->subject)->toBe('Rent < 200 on the recert')
            ->and($note->content)->toBe('Subject changed from "Rent is wrong" to "Rent &lt; 200 on the recert"');
    });

    it('keeps the subject plain text', function () {
        Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
            ->callAction(EditTicketAction::class, editing($this->ticket, ['subject' => '<b>Rent</b>']))
            ->assertHasActionErrors(['subject']);

        expect($this->ticket->refresh()->subject)->toBe('Rent is wrong');
    });

    it('writes no history when nothing it shows changed', function () {
        Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
            ->callAction(EditTicketAction::class, editing($this->ticket, []))
            ->assertHasNoActionErrors();

        expect($this->ticket->ticketActivities()->whereIn('type', [ActivityType::SubjectChanged, ActivityType::AssigneeChanged])->exists())->toBeFalse();
    });

    it('reassigns as Reassign does: the same history note and the same assignment email', function () {
        Event::fake([TicketAssignedEvent::class]);

        Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
            ->callAction(EditTicketAction::class, editing($this->ticket, ['assignee_id' => $this->colleague->id]))
            ->assertHasNoActionErrors();

        $viaEdit = $this->ticket->ticketActivities()->where('type', ActivityType::AssigneeChanged)->sole();
        $other = Ticket::factory()->open()->create(['assignee_id' => $this->supporter->id]);

        Livewire::test(ViewTicket::class, ['record' => $other->id])
            ->callAction(TestAction::make(ReassignTicketAction::class)->schemaComponent('assignee', schema: 'form'), ['assignee_id' => $this->colleague->id]);

        $viaReassign = $other->ticketActivities()->where('type', ActivityType::AssigneeChanged)->sole();

        expect((string) $this->ticket->refresh()->assignee_id)->toBe((string) $this->colleague->id)
            ->and($viaEdit->data)->toEqual(['from' => $this->supporter->id, 'to' => $this->colleague->id])
            ->and($viaEdit->assigneeNote($this->supporter->id))->toBe($viaReassign->assigneeNote($this->supporter->id));

        Event::assertDispatchedTimes(TicketAssignedEvent::class, 2);
    });

    it('offers only the people Reassign offers, and refuses anyone else', function () {
        Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
            ->mountAction(EditTicketAction::class)
            ->assertMountedActionModalSee(['Tess Support', 'Maria Lopez'])
            ->assertMountedActionModalDontSee('Aisha Brooks')
            ->callMountedAction()
            ->assertHasNoActionErrors();

        Livewire::test(ViewTicket::class, ['record' => $this->ticket->id])
            ->callAction(EditTicketAction::class, editing($this->ticket, ['assignee_id' => $this->outsider->id, 'subject' => 'Changed']))
            ->assertHasActionErrors(['assignee_id']);

        expect($this->ticket->refresh())
            ->subject->toBe('Rent is wrong')
            ->and((string) $this->ticket->assignee_id)->toBe((string) $this->supporter->id);
    });
});
