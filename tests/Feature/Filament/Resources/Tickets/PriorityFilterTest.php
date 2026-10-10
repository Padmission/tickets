<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Tests\Fixtures\Models\Tenant;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    $this->login();
    Schema::table('tickets', fn (Blueprint $table) => $table->unsignedBigInteger('tenant_id')->nullable());
    Schema::table('ticket_statuses', fn (Blueprint $table) => $table->unsignedBigInteger('tenant_id')->nullable());
    Schema::table('ticket_priorities', fn (Blueprint $table) => $table->unsignedBigInteger('tenant_id')->nullable());
    Schema::create('tenants', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
    config()->set('padmission-tickets.tenancy', ['enabled' => true, 'tenancy_model' => Tenant::class]);
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

    $page = Livewire::test(ListTickets::class, ['activeTab' => 'all'])->removeTableFilter('open');
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

    $page = Livewire::test(ListTickets::class, ['activeTab' => 'all'])->removeTableFilter('open');
    expect($page->instance()->getTable()->getFilter('priority')->getOptions())->toBe([$highA->id => 'High']);

    $page->filterTable('priority', [$highA->id])->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b]);
});

/*
 * A host that keeps one shared set of lookups alongside an organization's own
 * still spans organizations, but a count of distinct organizations skips the
 * shared rows' empty one, so the names were offered once per row.
 */
it('offers a priority name once when one organization shares the panel with a global set', function () {
    $status = TicketStatus::factory()->create(['tenant_id' => null]);
    $sharedHigh = TicketPriority::factory()->create(['tenant_id' => null, 'display_name' => 'High']);
    $sharedLow = TicketPriority::factory()->create(['tenant_id' => null, 'display_name' => 'Low']);
    $ownHigh = TicketPriority::factory()->create(['tenant_id' => 1, 'display_name' => 'High']);
    TicketPriority::factory()->create(['tenant_id' => 1, 'display_name' => 'Low']);

    $shared = Ticket::factory()->create(['tenant_id' => null, 'status_id' => $status->id, 'priority_id' => $sharedHigh->id]);
    $own = Ticket::factory()->create(['tenant_id' => 1, 'status_id' => $status->id, 'priority_id' => $ownHigh->id]);
    $low = Ticket::factory()->create(['tenant_id' => null, 'status_id' => $status->id, 'priority_id' => $sharedLow->id]);

    $page = Livewire::test(ListTickets::class, ['activeTab' => 'all'])->removeTableFilter('open');
    expect($page->instance()->getTable()->getFilter('priority')->getOptions())->toBe(['High' => 'High', 'Low' => 'Low']);

    $page->filterTable('priority', ['High'])
        ->assertCanSeeTableRecords([$shared, $own])
        ->assertCanNotSeeTableRecords([$low]);
});

/*
 * A panel serving several organizations has lifted the host's viewer scope
 * whether or not the host turned tenancy on, so the names repeated there too.
 */
it('offers a priority name once on a multi-organization panel with tenancy off', function () {
    config()->set('padmission-tickets.tenancy.enabled', false);

    $status = TicketStatus::factory()->create(['tenant_id' => 1]);
    $highA = TicketPriority::factory()->create(['tenant_id' => 1, 'display_name' => 'High']);
    $highB = TicketPriority::factory()->create(['tenant_id' => 2, 'display_name' => 'High']);
    $low = TicketPriority::factory()->create(['tenant_id' => 2, 'display_name' => 'Low']);

    $a = Ticket::factory()->create(['tenant_id' => 1, 'status_id' => $status->id, 'priority_id' => $highA->id]);
    $b = Ticket::factory()->create(['tenant_id' => 2, 'status_id' => $status->id, 'priority_id' => $highB->id]);
    $c = Ticket::factory()->create(['tenant_id' => 2, 'status_id' => $status->id, 'priority_id' => $low->id]);

    $page = Livewire::test(ListTickets::class, ['activeTab' => 'all'])->removeTableFilter('open');
    expect($page->instance()->getTable()->getFilter('priority')->getOptions())->toBe(['High' => 'High', 'Low' => 'Low']);

    $page->filterTable('priority', ['High'])
        ->assertCanSeeTableRecords([$a, $b])
        ->assertCanNotSeeTableRecords([$c]);
});

/*
 * The priority relation lifts the current panel scope, so a ticket keeps the
 * priority another panel gave it. The filter still belongs to one panel: without
 * its own limit it offered every panel's row, repeating each name.
 */
it('offers only the current panel\'s priorities when one organization keeps a set per panel', function () {
    config()->set('padmission-tickets.tenancy.enabled', false);

    $status = TicketStatus::factory()->create(['panel' => 'test']);
    $low = TicketPriority::factory()->create(['panel' => 'test', 'order' => 1, 'display_name' => 'Low']);
    $high = TicketPriority::factory()->create(['panel' => 'test', 'order' => 2, 'display_name' => 'High']);
    TicketPriority::factory()->create(['panel' => 'test2', 'order' => 1, 'display_name' => 'Low']);
    TicketPriority::factory()->create(['panel' => 'test2', 'order' => 2, 'display_name' => 'High']);

    $urgent = Ticket::factory()->create(['panel' => 'test', 'status_id' => $status->id, 'priority_id' => $high->id]);
    $calm = Ticket::factory()->create(['panel' => 'test', 'status_id' => $status->id, 'priority_id' => $low->id]);

    $page = Livewire::test(ListTickets::class, ['activeTab' => 'all'])->removeTableFilter('open');
    expect($page->instance()->getTable()->getFilter('priority')->getOptions())
        ->toBe([$low->id => 'Low', $high->id => 'High']);

    $page->filterTable('priority', [$high->id])
        ->assertCanSeeTableRecords([$urgent])
        ->assertCanNotSeeTableRecords([$calm]);
});
