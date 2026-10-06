<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    $this->login();
    Schema::table('tickets', fn (Blueprint $table) => $table->unsignedBigInteger('tenant_id')->nullable());
    Schema::table('ticket_statuses', fn (Blueprint $table) => $table->unsignedBigInteger('tenant_id')->nullable());
    config()->set('padmission-tickets.tenancy.enabled', true);
});

it('offers a status name once and matches every organization with that name', function () {
    $closedA = TicketStatus::factory()->create(['tenant_id' => 1, 'display_name' => 'Closed']);
    $closedB = TicketStatus::factory()->create(['tenant_id' => 2, 'display_name' => 'Closed']);
    $open = TicketStatus::factory()->create(['tenant_id' => 2, 'display_name' => 'Open']);
    $foreign = TicketStatus::factory()->create(['tenant_id' => 3, 'panel' => 'test2', 'display_name' => 'Foreign']);
    $a = Ticket::factory()->create(['tenant_id' => 1, 'status_id' => $closedA->id, 'closed_at' => now()]);
    $b = Ticket::factory()->create(['tenant_id' => 2, 'status_id' => $closedB->id, 'closed_at' => now()]);
    $c = Ticket::factory()->create(['tenant_id' => 2, 'status_id' => $open->id, 'closed_at' => null]);
    $d = Ticket::factory()->create(['tenant_id' => 3, 'panel' => 'test2', 'status_id' => $foreign->id, 'closed_at' => null]);

    $page = Livewire::test(ListTickets::class)->removeTableFilter('open');
    expect($page->instance()->getTable()->getFilter('status')->getOptions())->toBe(['Closed' => 'Closed', 'Open' => 'Open']);

    $page->filterTable('status', ['Closed'])
        ->assertCanSeeTableRecords([$a, $b])
        ->assertCanNotSeeTableRecords([$c, $d]);

    $page->filterTable('status', ['Closed', 'Open'])->assertCanSeeTableRecords([$a, $b, $c]);
    $page->removeTableFilter('status')->assertCanSeeTableRecords([$a, $b, $c]);
});

it('keeps status ids and host scoping on a single organization panel', function () {
    $closedA = TicketStatus::factory()->create(['tenant_id' => 1, 'display_name' => 'Closed']);
    $closedB = TicketStatus::factory()->create(['tenant_id' => 2, 'display_name' => 'Closed']);
    $a = Ticket::factory()->create(['tenant_id' => 1, 'status_id' => $closedA->id, 'closed_at' => now()]);
    $b = Ticket::factory()->create(['tenant_id' => 2, 'status_id' => $closedB->id, 'closed_at' => now()]);

    TicketPlugin::get()->customizeTicketQuery(fn ($query) => $query->where('tickets.tenant_id', 1))
        ->modifyRelationshipScopes(fn ($relation, $model) => $model === 'status' ? $relation->where('ticket_statuses.tenant_id', 1) : $relation);

    $page = Livewire::test(ListTickets::class)->removeTableFilter('open');
    expect($page->instance()->getTable()->getFilter('status')->queriesRelationships())->toBeTrue()
        ->and($page->instance()->getTable()->getFilter('status')->getRelationshipQuery()->pluck('display_name', 'id')->all())->toBe([$closedA->id => 'Closed']);

    $page->filterTable('status', [$closedA->id])->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b]);
});
