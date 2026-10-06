<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    $this->login();
    Schema::table('tickets', fn (Blueprint $table) => $table->unsignedBigInteger('tenant_id')->nullable());
    Schema::table('ticket_statuses', fn (Blueprint $table) => $table->unsignedBigInteger('tenant_id')->nullable());
    Schema::table('ticket_priorities', fn (Blueprint $table) => $table->unsignedBigInteger('tenant_id')->nullable());
    config()->set('padmission-tickets.tenancy.enabled', true);
});

it('offers a priority name once and matches every organization with that name', function () {
    $status = TicketStatus::factory()->create();
    $highA = TicketPriority::factory()->create(['tenant_id' => 1, 'display_name' => 'High']);
    $highB = TicketPriority::factory()->create(['tenant_id' => 2, 'display_name' => 'High']);
    $low = TicketPriority::factory()->create(['tenant_id' => 2, 'display_name' => 'Low']);
    $foreign = TicketPriority::factory()->create(['tenant_id' => 3, 'panel' => 'test2', 'display_name' => 'Foreign']);
    $a = Ticket::factory()->create(['tenant_id' => 1, 'status_id' => $status->id, 'priority_id' => $highA->id]);
    $b = Ticket::factory()->create(['tenant_id' => 2, 'status_id' => $status->id, 'priority_id' => $highB->id]);
    $c = Ticket::factory()->create(['tenant_id' => 2, 'status_id' => $status->id, 'priority_id' => $low->id]);
    $d = Ticket::factory()->create(['tenant_id' => 3, 'panel' => 'test2', 'status_id' => $status->id, 'priority_id' => $foreign->id]);

    $page = Livewire::test(ListTickets::class)->removeTableFilter('open');
    expect($page->instance()->getTable()->getFilter('priority')->getOptions())->toBe(['High' => 'High', 'Low' => 'Low']);

    $page->filterTable('priority', ['High'])
        ->assertCanSeeTableRecords([$a, $b])
        ->assertCanNotSeeTableRecords([$c, $d]);

    $page->filterTable('priority', ['High', 'Low'])->assertCanSeeTableRecords([$a, $b, $c]);
    $page->removeTableFilter('priority')->assertCanSeeTableRecords([$a, $b, $c]);
});

it('keeps priority ids and host scoping on a single organization panel', function () {
    $status = TicketStatus::factory()->create();
    $highA = TicketPriority::factory()->create(['tenant_id' => 1, 'display_name' => 'High']);
    $highB = TicketPriority::factory()->create(['tenant_id' => 2, 'display_name' => 'High']);
    $a = Ticket::factory()->create(['tenant_id' => 1, 'status_id' => $status->id, 'priority_id' => $highA->id]);
    $b = Ticket::factory()->create(['tenant_id' => 2, 'status_id' => $status->id, 'priority_id' => $highB->id]);

    TicketPlugin::get()->customizeTicketQuery(fn ($query) => $query->where('tickets.tenant_id', 1))
        ->modifyRelationshipScopes(fn ($relation, $model) => $model === 'priority' ? $relation->where('ticket_priorities.tenant_id', 1) : $relation);

    $page = Livewire::test(ListTickets::class)->removeTableFilter('open');
    expect($page->instance()->getTable()->getFilter('priority')->queriesRelationships())->toBeTrue()
        ->and($page->instance()->getTable()->getFilter('priority')->getRelationshipQuery()->pluck('display_name', 'id')->all())->toBe([$highA->id => 'High']);

    $page->filterTable('priority', [$highA->id])->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b]);
});
