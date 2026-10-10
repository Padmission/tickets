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
    Schema::create('tenants', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
    config()->set('padmission-tickets.tenancy', ['enabled' => true, 'tenancy_model' => Tenant::class]);
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

    $page = Livewire::test(ListTickets::class, ['activeTab' => 'all'])->removeTableFilter('open');
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

    $page = Livewire::test(ListTickets::class, ['activeTab' => 'all'])->removeTableFilter('open');
    expect($page->instance()->getTable()->getFilter('status')->getOptions())->toBe([$closedA->id => 'Closed']);

    $page->filterTable('status', [$closedA->id])->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b]);
});

/*
 * A host that keeps one shared set of lookups alongside an organization's own
 * still spans organizations, but a count of distinct organizations skips the
 * shared rows' empty one, so the names were offered once per row.
 */
it('offers a status name once when one organization shares the panel with a global set', function () {
    $sharedOpen = TicketStatus::factory()->create(['tenant_id' => null, 'display_name' => 'Open']);
    $sharedClosed = TicketStatus::factory()->create(['tenant_id' => null, 'display_name' => 'Closed']);
    $ownOpen = TicketStatus::factory()->create(['tenant_id' => 1, 'display_name' => 'Open']);
    TicketStatus::factory()->create(['tenant_id' => 1, 'display_name' => 'Closed']);

    $shared = Ticket::factory()->create(['tenant_id' => null, 'status_id' => $sharedOpen->id, 'closed_at' => null]);
    $own = Ticket::factory()->create(['tenant_id' => 1, 'status_id' => $ownOpen->id, 'closed_at' => null]);
    $closed = Ticket::factory()->create(['tenant_id' => null, 'status_id' => $sharedClosed->id, 'closed_at' => now()]);

    $page = Livewire::test(ListTickets::class, ['activeTab' => 'all'])->removeTableFilter('open');
    expect($page->instance()->getTable()->getFilter('status')->getOptions())->toBe(['Closed' => 'Closed', 'Open' => 'Open']);

    $page->filterTable('status', ['Open'])
        ->assertCanSeeTableRecords([$shared, $own])
        ->assertCanNotSeeTableRecords([$closed]);
});

/*
 * A panel serving several organizations has lifted the host's viewer scope
 * whether or not the host turned tenancy on, so the names repeated there too.
 */
it('offers a status name once on a multi-organization panel with tenancy off', function () {
    config()->set('padmission-tickets.tenancy.enabled', false);

    $openA = TicketStatus::factory()->create(['tenant_id' => 1, 'display_name' => 'Open']);
    $openB = TicketStatus::factory()->create(['tenant_id' => 2, 'display_name' => 'Open']);
    $closed = TicketStatus::factory()->create(['tenant_id' => 2, 'display_name' => 'Closed']);

    $a = Ticket::factory()->create(['tenant_id' => 1, 'status_id' => $openA->id, 'closed_at' => null]);
    $b = Ticket::factory()->create(['tenant_id' => 2, 'status_id' => $openB->id, 'closed_at' => null]);
    $c = Ticket::factory()->create(['tenant_id' => 2, 'status_id' => $closed->id, 'closed_at' => now()]);

    $page = Livewire::test(ListTickets::class, ['activeTab' => 'all'])->removeTableFilter('open');
    expect($page->instance()->getTable()->getFilter('status')->getOptions())->toBe(['Closed' => 'Closed', 'Open' => 'Open']);

    $page->filterTable('status', ['Open'])
        ->assertCanSeeTableRecords([$a, $b])
        ->assertCanNotSeeTableRecords([$c]);
});

/*
 * The status relation lifts the current panel scope, so a ticket keeps the
 * status another panel gave it. The filter still belongs to one panel: without
 * its own limit it offered every panel's row, repeating each name.
 */
it('offers only the current panel\'s statuses when one organization keeps a set per panel', function () {
    config()->set('padmission-tickets.tenancy.enabled', false);

    $open = TicketStatus::factory()->create(['panel' => 'test', 'order' => 1, 'display_name' => 'Open']);
    $closed = TicketStatus::factory()->create(['panel' => 'test', 'order' => 2, 'display_name' => 'Closed']);
    TicketStatus::factory()->create(['panel' => 'test2', 'order' => 1, 'display_name' => 'Open']);
    TicketStatus::factory()->create(['panel' => 'test2', 'order' => 2, 'display_name' => 'Closed']);

    $mine = Ticket::factory()->create(['panel' => 'test', 'status_id' => $open->id, 'closed_at' => null]);
    $shut = Ticket::factory()->create(['panel' => 'test', 'status_id' => $closed->id, 'closed_at' => now()]);

    $page = Livewire::test(ListTickets::class, ['activeTab' => 'all'])->removeTableFilter('open');
    expect($page->instance()->getTable()->getFilter('status')->getOptions())
        ->toBe([$open->id => 'Open', $closed->id => 'Closed']);

    $page->filterTable('status', [$open->id])
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$shut]);
});

/*
 * The filter offers only this panel's rows, which is safe because a ticket is
 * never given another panel's: every path that writes one reads it from the
 * ticket's own panel. A ticket the filter could not reach would be invisible
 * to it, so this holds the invariant the restriction above depends on.
 */
it('gives a ticket a status and priority from its own panel on every path that writes one', function () {
    TicketStatus::factory()->create(['panel' => 'test2', 'order' => 0, 'display_name' => 'Open']);
    TicketStatus::factory()->create(['panel' => 'test2', 'order' => 9, 'display_name' => 'Closed']);
    TicketPriority::factory()->create(['panel' => 'test2', 'order' => 0, 'display_name' => 'Normal']);

    $open = TicketStatus::factory()->create(['panel' => 'test', 'order' => 1, 'display_name' => 'Open']);
    TicketStatus::factory()->create(['panel' => 'test', 'order' => 2, 'display_name' => 'Closed']);
    TicketPriority::factory()->create(['panel' => 'test', 'order' => 1, 'display_name' => 'Normal']);

    $ticket = Ticket::factory()->create(['panel' => 'test', 'status_id' => $open->id]);

    $panelOf = fn (Ticket $record): array => [
        $record->status()->withoutGlobalScopes()->value('panel'),
        $record->priority()->withoutGlobalScopes()->value('panel'),
    ];

    $ticket->close();
    expect($panelOf($ticket->refresh()))->toBe(['test', 'test']);

    $ticket->reopen();
    expect($panelOf($ticket->refresh()))->toBe(['test', 'test']);
});
