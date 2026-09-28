<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\EditTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;
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
