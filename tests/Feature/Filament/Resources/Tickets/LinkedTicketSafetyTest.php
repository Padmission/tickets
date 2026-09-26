<?php

use Filament\Actions\Testing\TestAction;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Padmission\Tickets\Filament\Forms\Components\LinkedTicketModalSelect;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\AddToEscalationAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\CreateLinkedTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Filament\Tables\LinkedTicketCandidates;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\TicketEscalationLinks;
use Padmission\Tickets\Tests\Fixtures\Models\CustomTicket;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    $this->login();
    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
});

it('loads linked tickets as the host ticket model', function () {
    config()->set('padmission-tickets.models', [
        Authenticatable::class => User::class,
        Ticket::class => CustomTicket::class,
    ]);

    $escalated = CustomTicket::factory()->create(['panel' => 'test2']);
    $original = CustomTicket::factory()->create(['linked_ticket_id' => $escalated->id]);

    // The base model skips the host's own scoping of the linked ticket, such
    // as a status relation that lifts its tenant scope.
    expect($original->parentTicket)->toBeInstanceOf(CustomTicket::class)
        ->and($escalated->childTickets->first())->toBeInstanceOf(CustomTicket::class);
});

it('shows a linked ticket whose status the viewer cannot load', function () {
    $ticket = Ticket::factory()->create(['panel' => 'test2']);
    $ticket->setRelation('status', null);

    $html = LinkedTicketModalSelect::make('parentTicket')
        ->configure()
        ->getOptionLabelFromRecord($ticket);

    expect($html->toHtml())->toContain("#{$ticket->id}");
});

describe('with tenants', function () {
    beforeEach(function () {
        config()->set('padmission-tickets.tenancy.enabled', true);
        Schema::table('tickets', fn (Blueprint $table) => $table->unsignedBigInteger('tenant_id')->nullable());
    });

    it('only offers escalated tickets from the ticket\'s own tenant', function () {
        $ticket = Ticket::factory()->create(['tenant_id' => 1]);
        $sameTenant = Ticket::factory()->create(['panel' => 'test2', 'tenant_id' => 1]);
        $otherTenant = Ticket::factory()->create(['panel' => 'test2', 'tenant_id' => 2]);

        $offered = LinkedTicketCandidates::parents(Ticket::query(), $ticket)->pluck('id');

        expect($offered)->toContain($sameTenant->id)->not->toContain($otherTenant->id);
    });

    it('refuses to link another tenant\'s ticket', function () {
        $ticket = Ticket::factory()->create(['tenant_id' => 1, 'linked_ticket_id' => null]);
        $otherTenant = Ticket::factory()->create(['panel' => 'test2', 'tenant_id' => 2]);

        Livewire::test(ViewTicket::class, ['record' => $ticket->id])
            ->callAction(TestAction::make(AddToEscalationAction::class)->schemaComponent('escalationActions', schema: 'form'), ['escalation' => $otherTenant->id])
            ->assertNotified(__('padmission-tickets::tickets.resources.tickets.link_refused.title'));

        expect($ticket->refresh()->linked_ticket_id)->toBeNull();
    });

    it('only offers originals from the escalated ticket\'s own tenant', function () {
        TicketPlugin::get('test2')->allowLinkedTicketsTo(['test']);

        $escalated = Ticket::factory()->create(['tenant_id' => 1]);
        $sameTenant = Ticket::factory()->create(['panel' => 'test2', 'tenant_id' => 1]);
        $otherTenant = Ticket::factory()->create(['panel' => 'test2', 'tenant_id' => 2]);

        $offered = LinkedTicketCandidates::children(Ticket::query(), $escalated)->pluck('id');

        expect($offered)->toContain($sameTenant->id)->not->toContain($otherTenant->id);
    });
});

it('offers escalations that already have originals, but not originals linked elsewhere', function () {
    TicketPlugin::get('test2')->allowLinkedTicketsTo(['test']);

    $ticket = Ticket::factory()->create();
    $sharedEscalation = Ticket::factory()->create(['panel' => 'test2']);
    Ticket::factory()->create(['linked_ticket_id' => $sharedEscalation->id]);

    expect(LinkedTicketCandidates::parents(Ticket::query(), $ticket)->pluck('id'))
        ->toContain($sharedEscalation->id);

    $escalated = Ticket::factory()->create();
    $originalLinkedElsewhere = Ticket::factory()->create(['panel' => 'test2', 'linked_ticket_id' => Ticket::factory()->create()->id]);

    expect(LinkedTicketCandidates::children(Ticket::query(), $escalated)->pluck('id'))
        ->not->toContain($originalLinkedElsewhere->id);
});

it('keeps an existing escalation instead of replacing it', function () {
    $escalation = Ticket::factory()->create(['panel' => 'test2']);
    $another = Ticket::factory()->create(['panel' => 'test2']);
    $ticket = Ticket::factory()->create(['linked_ticket_id' => $escalation->id]);

    expect(CreateLinkedTicketAction::isAvailableFor($ticket))->toBeFalse()
        ->and(resolve(TicketEscalationLinks::class)->addToEscalation($ticket, $another->id))->toBe(TicketEscalationLinks::ALREADY_ESCALATED)
        ->and($ticket->refresh()->linked_ticket_id)->toBe($escalation->id);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertDontSee(__('padmission-tickets::tickets.actions.add_to_escalation.label'));
});

describe('in a cross-tenant panel', function () {
    beforeEach(function () {
        config()->set('padmission-tickets.tenancy.enabled', true);
        config()->set('padmission-tickets.models', [
            Authenticatable::class => User::class,
            Ticket::class => CustomTicket::class,
        ]);
        Schema::table('tickets', fn (Blueprint $table) => $table->unsignedBigInteger('tenant_id')->nullable());

        // Stands in for a host tenant scope pinned to the viewer's tenant (1),
        // which the admin-like panel lifts from its queries and relationships.
        CustomTicket::addGlobalScope('viewer-tenant', fn ($query) => $query->where('tickets.tenant_id', 1));

        TicketPlugin::get()
            ->allowLinkedTicketsTo([])
            ->customizeTicketQuery(fn ($query) => $query->withoutGlobalScope('viewer-tenant'))
            ->modifyRelationshipScopes(fn ($relation) => $relation->withoutGlobalScope('viewer-tenant'));
        TicketPlugin::get('test2')->allowLinkedTicketsTo(['test']);
    });

    afterEach(fn () => CustomTicket::clearBootedModels());

    it('offers the escalated ticket\'s own tenant\'s originals, not the viewer\'s', function () {
        $escalated = CustomTicket::factory()->create(['tenant_id' => 4]);
        $ownTenant = CustomTicket::factory()->create(['panel' => 'test2', 'tenant_id' => 4]);
        $viewerTenant = CustomTicket::factory()->create(['panel' => 'test2', 'tenant_id' => 1]);
        $otherTenant = CustomTicket::factory()->create(['panel' => 'test2', 'tenant_id' => 2]);

        $offered = LinkedTicketCandidates::children(CustomTicket::query(), $escalated)->pluck('id');

        expect($offered->all())->toBe([$ownTenant->id])
            ->and($offered)->not->toContain($viewerTenant->id)
            ->not->toContain($otherTenant->id);
    });

    it('links the escalated ticket\'s own tenant\'s original and refuses the viewer\'s', function () {
        $escalated = CustomTicket::factory()->create(['tenant_id' => 4]);
        $ownTenant = CustomTicket::factory()->create(['panel' => 'test2', 'tenant_id' => 4, 'linked_ticket_id' => null]);
        $viewerTenant = CustomTicket::factory()->create(['panel' => 'test2', 'tenant_id' => 1, 'linked_ticket_id' => null]);

        Livewire::test(ViewTicket::class, ['record' => $escalated->id])
            ->fillForm(['childTickets' => [$ownTenant->id]]);

        expect(CustomTicket::withoutGlobalScopes()->find($ownTenant->id)->linked_ticket_id)->toBe($escalated->id);

        Livewire::test(ViewTicket::class, ['record' => $escalated->id])
            ->fillForm(['childTickets' => [$ownTenant->id, $viewerTenant->id]])
            ->assertNotified(__('padmission-tickets::tickets.resources.tickets.link_refused.title'));

        expect(CustomTicket::withoutGlobalScopes()->find($viewerTenant->id)->linked_ticket_id)->toBeNull();
    });
});

it('does not filter by tenant when tenancy is off', function () {
    config()->set('padmission-tickets.tenancy.enabled', false);
    TicketPlugin::get('test2')->allowLinkedTicketsTo(['test']);

    $escalated = Ticket::factory()->create();
    $original = Ticket::factory()->create(['panel' => 'test2']);

    expect(LinkedTicketCandidates::children(Ticket::query(), $escalated)->pluck('id'))->toContain($original->id);
});
