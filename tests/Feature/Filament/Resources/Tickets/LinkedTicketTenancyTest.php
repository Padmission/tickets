<?php

use Filament\Facades\Filament;
use Filament\Forms\Components\TableSelect\Livewire\TableSelectLivewireComponent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Filament\Tables\ChildTicketsTable;
use Padmission\Tickets\Filament\Tables\OpenEscalationsTable;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Services\TicketEscalationLinks;
use Padmission\Tickets\Tests\Fixtures\Models\CustomTicket;
use Padmission\Tickets\Tests\Fixtures\Models\HostTicket;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

/*
 * 3.x's parent picker became Add to escalation, which picks through
 * OpenEscalationsTable and saves through TicketEscalationLinks.
 */
function linkedTicketPicker(Ticket $ticket, string $relationship): Testable
{
    return Livewire::test(TableSelectLivewireComponent::class, [
        'model' => $ticket::class,
        'record' => $ticket,
        'relationshipName' => $relationship,
        'tableConfiguration' => base64_encode($relationship === 'childTickets' ? ChildTicketsTable::class : OpenEscalationsTable::class),
        'state' => $relationship === 'childTickets' ? [] : null,
    ]);
}

beforeEach(function () {
    $this->login();

    TicketPlugin::get()->allowLinkedTicketsTo(['test2']);
    (new TicketStatusSeeder)->run();

    Schema::table('tickets', fn (Blueprint $table) => $table->unsignedBigInteger('tenant_id')->nullable());
});

describe('with tenancy enabled', function () {
    beforeEach(fn () => config()->set('padmission-tickets.tenancy.enabled', true));

    it('lists only escalations from the ticket\'s own tenant in the Add to escalation picker', function () {
        $ticket = Ticket::factory()->open()->create(['tenant_id' => 1]);
        $sameTenant = escalationFrom('test', ['tenant_id' => 1]);
        $otherTenant = escalationFrom('test', ['tenant_id' => 2]);

        linkedTicketPicker($ticket, 'parentTicket')
            ->assertCanSeeTableRecords([$sameTenant])
            ->assertCanNotSeeTableRecords([$otherTenant]);
    });

    it('lists only originals from the ticket\'s own tenant in the child picker', function () {
        $escalated = Ticket::factory()->open()->create(['panel' => 'test2', 'tenant_id' => 1]);
        $sameTenant = Ticket::factory()->open()->create(['tenant_id' => 1]);
        $otherTenant = Ticket::factory()->open()->create(['tenant_id' => 2]);

        linkedTicketPicker($escalated, 'childTickets')
            ->assertCanSeeTableRecords([$sameTenant])
            ->assertCanNotSeeTableRecords([$otherTenant]);
    });

    it('adds a ticket to an escalation from the same tenant', function () {
        $ticket = Ticket::factory()->open()->create(['tenant_id' => 1]);
        $escalation = escalationFrom('test', ['tenant_id' => 1]);

        expect(resolve(TicketEscalationLinks::class)->addToEscalation($ticket, $escalation->id))->toBeNull()
            ->and($ticket->refresh()->linked_ticket_id)->toBe($escalation->id);
    });

    it('refuses a submitted escalation from another tenant', function () {
        $ticket = Ticket::factory()->open()->create(['tenant_id' => 1]);
        $otherTenant = escalationFrom('test', ['tenant_id' => 2]);

        expect(resolve(TicketEscalationLinks::class)->addToEscalation($ticket, $otherTenant->id))->toBe(TicketEscalationLinks::NOT_LINKABLE)
            ->and($ticket->refresh()->linked_ticket_id)->toBeNull();
    });

    it('refuses a submitted original from another tenant', function () {
        TicketPlugin::get('test2')->allowLinkedTicketsTo(['test']);

        $escalated = Ticket::factory()->open()->create(['tenant_id' => 1]);
        $otherTenant = Ticket::factory()->open()->create(['panel' => 'test2', 'tenant_id' => 2]);

        Livewire::test(ViewTicket::class, ['record' => $escalated->id])
            ->callAction('linkOriginals', ['originals' => [$otherTenant->id]])
            ->assertNotified(__('padmission-tickets::tickets.resources.tickets.link_refused.title'));

        expect($otherTenant->refresh()->linked_ticket_id)->toBeNull();
    });

    it('renders a ticket linked to another tenant\'s escalation', function () {
        config()->set('padmission-tickets.models', [
            Authenticatable::class => User::class,
            Ticket::class => HostTicket::class,
        ]);

        Schema::table('ticket_statuses', fn (Blueprint $table) => $table->unsignedBigInteger('tenant_id')->nullable());
        TicketStatus::addGlobalScope('tenant', fn ($query) => $query->where('ticket_statuses.tenant_id', 1));

        $ownStatus = TicketStatus::factory()->create(['tenant_id' => 1, 'order' => 1]);
        TicketStatus::factory()->create(['tenant_id' => 1, 'order' => 99]);
        $escalationStatus = TicketStatus::factory()->create(['panel' => 'test2', 'tenant_id' => 2, 'display_name' => 'Escalation Status', 'order' => 1]);
        TicketStatus::factory()->create(['panel' => 'test2', 'tenant_id' => 2, 'order' => 99]);

        $escalation = HostTicket::factory()->create(['panel' => 'test2', 'tenant_id' => 2, 'status_id' => $escalationStatus->id, 'closed_at' => null]);
        $ticket = HostTicket::factory()->create(['tenant_id' => 1, 'status_id' => $ownStatus->id, 'closed_at' => null, 'linked_ticket_id' => $escalation->id]);

        try {
            Livewire::test(ViewTicket::class, ['record' => $ticket->id, 'linkedTicketId' => $escalation->id])
                ->assertOk()
                ->assertSee('Escalation Status');
        } finally {
            TicketStatus::clearBootedModels();
        }
    });

    describe('in a panel that lifts the host\'s tenant scope', function () {
        beforeEach(function () {
            config()->set('padmission-tickets.models', [
                Authenticatable::class => User::class,
                Ticket::class => CustomTicket::class,
            ]);

            // Stands in for a host tenant scope pinned to the viewer's own tenant (1).
            CustomTicket::addGlobalScope('tenant', fn ($query) => $query->where('tickets.tenant_id', 1));

            Filament::setCurrentPanel('test2');
            TicketStatus::factory()->create(['panel' => 'test2']);

            TicketPlugin::get('test2')
                ->customizeTicketQuery(fn ($query) => $query->withoutGlobalScope('tenant'))
                ->modifyRelationshipScopes(fn ($relation) => $relation->withoutGlobalScope('tenant'));
        });

        afterEach(fn () => CustomTicket::clearBootedModels());

        it('lists originals from the escalated ticket\'s tenant, not the viewer\'s', function () {
            $escalated = CustomTicket::factory()->open()->create(['panel' => 'test2', 'tenant_id' => 4]);
            $ticketTenant = CustomTicket::factory()->open()->create(['tenant_id' => 4]);
            $viewerTenant = CustomTicket::factory()->open()->create(['tenant_id' => 1]);
            $otherTenant = CustomTicket::factory()->open()->create(['tenant_id' => 2]);

            linkedTicketPicker($escalated, 'childTickets')
                ->assertCanSeeTableRecords([$ticketTenant])
                ->assertCanNotSeeTableRecords([$viewerTenant, $otherTenant]);
        });

        it('lists only tenant-less originals for an escalated ticket without a tenant', function () {
            $escalated = CustomTicket::factory()->open()->create(['panel' => 'test2', 'tenant_id' => null]);
            $tenantless = CustomTicket::factory()->open()->create(['tenant_id' => null]);
            $viewerTenant = CustomTicket::factory()->open()->create(['tenant_id' => 1]);
            $otherTenant = CustomTicket::factory()->open()->create(['tenant_id' => 2]);

            linkedTicketPicker($escalated, 'childTickets')
                ->assertCanSeeTableRecords([$tenantless])
                ->assertCanNotSeeTableRecords([$viewerTenant, $otherTenant]);
        });
    });
});

describe('with a scope the ticket\'s panel lifts from its relationships', function () {
    beforeEach(function () {
        config()->set('padmission-tickets.models', [
            Authenticatable::class => User::class,
            Ticket::class => CustomTicket::class,
        ]);

        CustomTicket::addGlobalScope('hidden', fn ($query) => $query->where('tickets.subject', '!=', 'Hidden'));

        TicketPlugin::get('test2')->modifyRelationshipScopes(fn ($relation) => $relation->withoutGlobalScope('hidden'));
    });

    afterEach(fn () => CustomTicket::clearBootedModels());

    it('keeps the scope in the picker when the current panel does not lift it', function () {
        $escalated = CustomTicket::factory()->open()->create(['panel' => 'test2']);
        $hidden = CustomTicket::factory()->open()->create(['subject' => 'Hidden']);

        linkedTicketPicker($escalated, 'childTickets')->assertCanNotSeeTableRecords([$hidden]);
    });

    it('lifts the scope in the picker when the current panel lifts it too', function () {
        Filament::setCurrentPanel('test2');
        TicketStatus::factory()->create(['panel' => 'test2']);

        $escalated = CustomTicket::factory()->open()->create(['panel' => 'test2']);
        $hidden = CustomTicket::factory()->open()->create(['subject' => 'Hidden']);

        linkedTicketPicker($escalated, 'childTickets')->assertCanSeeTableRecords([$hidden]);
    });
});

it('does not filter the Add to escalation picker by tenant when tenancy is disabled', function () {
    $ticket = Ticket::factory()->open()->create(['tenant_id' => 1]);
    $otherTenant = escalationFrom('test', ['tenant_id' => 2]);

    linkedTicketPicker($ticket, 'parentTicket')->assertCanSeeTableRecords([$otherTenant]);
});

it('refuses to replace an existing open escalation link', function () {
    $escalation = escalationFrom();
    $another = escalationFrom();
    $ticket = Ticket::factory()->open()->create(['linked_ticket_id' => $escalation->id]);

    expect(resolve(TicketEscalationLinks::class)->addToEscalation($ticket, $another->id))->toBe(TicketEscalationLinks::ALREADY_ESCALATED)
        ->and($ticket->refresh()->linked_ticket_id)->toBe($escalation->id);
});

it('refuses to replace an escalation linked since the ticket was loaded', function () {
    $linkedMeanwhile = escalationFrom();
    $another = escalationFrom();
    $ticket = Ticket::factory()->open()->create(['linked_ticket_id' => null]);

    Ticket::query()->whereKey($ticket->id)->update(['linked_ticket_id' => $linkedMeanwhile->id]);

    expect(resolve(TicketEscalationLinks::class)->addToEscalation($ticket, $another->id))->toBe(TicketEscalationLinks::ALREADY_ESCALATED)
        ->and($ticket->linked_ticket_id)->toBe($linkedMeanwhile->id)
        ->and($ticket->refresh()->linked_ticket_id)->toBe($linkedMeanwhile->id);
});

it('links original tickets submitted more than once', function () {
    TicketPlugin::get('test2')->allowLinkedTicketsTo(['test']);

    $escalated = Ticket::factory()->open()->create();
    $original = Ticket::factory()->open()->create(['panel' => 'test2']);

    Livewire::test(ViewTicket::class, ['record' => $escalated->id])
        ->callAction('linkOriginals', ['originals' => [$original->id, $original->id]])
        ->assertNotNotified(__('padmission-tickets::tickets.resources.tickets.link_refused.title'));

    expect($original->refresh()->linked_ticket_id)->toBe($escalated->id);
});

it('refuses an original another request links elsewhere before this one saves', function () {
    TicketPlugin::get('test2')->allowLinkedTicketsTo(['test']);

    $escalated = Ticket::factory()->open()->create();
    $otherEscalation = Ticket::factory()->open()->create();
    $original = Ticket::factory()->open()->create(['panel' => 'test2']);

    $page = Livewire::test(ViewTicket::class, ['record' => $escalated->id]);

    Event::listen(TransactionBeginning::class, function () use ($original, $otherEscalation) {
        DB::table('tickets')->where('id', $original->id)->update(['linked_ticket_id' => $otherEscalation->id]);
    });

    $page
        ->callAction('linkOriginals', ['originals' => [$original->id]])
        ->assertNotified(__('padmission-tickets::tickets.resources.tickets.link_refused.title'));

    expect($original->refresh()->linked_ticket_id)->toBe($otherEscalation->id);
});

it('saves linked and unlinked originals through the model', function () {
    TicketPlugin::get('test2')->allowLinkedTicketsTo(['test']);

    $escalated = Ticket::factory()->open()->create();
    $toLink = Ticket::factory()->open()->create(['panel' => 'test2']);
    $toUnlink = Ticket::factory()->open()->create(['panel' => 'test2', 'linked_ticket_id' => $escalated->id]);

    $updated = [];
    Ticket::updated(function (Ticket $ticket) use (&$updated) {
        if ($ticket->wasChanged('linked_ticket_id')) {
            $updated[$ticket->id] = $ticket->linked_ticket_id;
        }
    });

    resolve(TicketEscalationLinks::class)->syncOriginals($escalated, [$toLink->id]);

    expect($updated)->toBe([$toUnlink->id => null, $toLink->id => $escalated->id]);
});

it('still removes a ticket from its escalation', function () {
    $escalation = escalationFrom();
    $ticket = Ticket::factory()->open()->create(['linked_ticket_id' => $escalation->id]);

    expect(resolve(TicketEscalationLinks::class)->removeFromEscalation($ticket))->toBeTrue()
        ->and($ticket->refresh()->linked_ticket_id)->toBeNull();
});

it('refuses an original that is already linked to another escalation', function () {
    TicketPlugin::get('test2')->allowLinkedTicketsTo(['test']);

    $escalated = Ticket::factory()->open()->create();
    $otherEscalation = Ticket::factory()->open()->create();
    $original = Ticket::factory()->open()->create(['panel' => 'test2', 'linked_ticket_id' => $otherEscalation->id]);

    linkedTicketPicker($escalated, 'childTickets')->assertCanNotSeeTableRecords([$original]);

    Livewire::test(ViewTicket::class, ['record' => $escalated->id])
        ->callAction('linkOriginals', ['originals' => [$original->id]])
        ->assertNotified(__('padmission-tickets::tickets.resources.tickets.link_refused.title'));

    expect($original->refresh()->linked_ticket_id)->toBe($otherEscalation->id);
});
