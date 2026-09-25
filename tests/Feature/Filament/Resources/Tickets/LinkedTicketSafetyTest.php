<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Padmission\Tickets\Filament\Forms\Components\LinkedTicketModalSelect;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Filament\Tables\LinkedTicketCandidates;
use Padmission\Tickets\Models\Ticket;
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
            ->fillForm(['parentTicket' => $otherTenant->id])
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

it('does not offer tickets already linked elsewhere', function () {
    TicketPlugin::get('test2')->allowLinkedTicketsTo(['test']);

    $ticket = Ticket::factory()->create();
    $takenEscalation = Ticket::factory()->create(['panel' => 'test2']);
    Ticket::factory()->create(['linked_ticket_id' => $takenEscalation->id]);
    $freeEscalation = Ticket::factory()->create(['panel' => 'test2']);

    expect(LinkedTicketCandidates::parents(Ticket::query(), $ticket)->pluck('id'))
        ->toContain($freeEscalation->id)
        ->not->toContain($takenEscalation->id);

    $escalated = Ticket::factory()->create();
    $originalLinkedElsewhere = Ticket::factory()->create(['panel' => 'test2', 'linked_ticket_id' => Ticket::factory()->create()->id]);

    expect(LinkedTicketCandidates::children(Ticket::query(), $escalated)->pluck('id'))
        ->not->toContain($originalLinkedElsewhere->id);
});

it('keeps an existing escalation instead of replacing it', function () {
    $escalation = Ticket::factory()->create(['panel' => 'test2']);
    $another = Ticket::factory()->create(['panel' => 'test2']);
    $ticket = Ticket::factory()->create(['linked_ticket_id' => $escalation->id]);

    Livewire::test(ViewTicket::class, ['record' => $ticket->id])
        ->assertFormFieldDisabled('parentTicket')
        ->fillForm(['parentTicket' => $another->id]);

    expect($ticket->refresh()->linked_ticket_id)->toBe($escalation->id);
});
