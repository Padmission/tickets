<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Padmission\Tickets\Filament\Resources\Dispositions\Pages\ListDispositions;
use Padmission\Tickets\Filament\Resources\Priorities\Pages\ListPriorities;
use Padmission\Tickets\Filament\Resources\Statuses\Pages\ListStatuses;
use Padmission\Tickets\Models\TicketDisposition;
use Padmission\Tickets\Models\TicketPriority;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Tests\Fixtures\Models\Tenant;

beforeEach(function () {
    $this->login();

    Schema::create('tenants', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });

    foreach (['ticket_statuses', 'ticket_priorities', 'ticket_dispositions'] as $table) {
        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->unsignedBigInteger('tenant_id')->nullable());
    }

    Tenant::create(['id' => 1, 'name' => 'First Org']);
    Tenant::create(['id' => 2, 'name' => 'Second Org']);

    config()->set('padmission-tickets.tenancy.enabled', true);
    config()->set('padmission-tickets.tenancy.tenancy_model', Tenant::class);
});

/*
 * Each organization keeps its own set, so the same display name is a different
 * row in each and the rows were indistinguishable on a panel serving several.
 */
dataset('lookup lists', [
    'statuses' => [ListStatuses::class, TicketStatus::class],
    'priorities' => [ListPriorities::class, TicketPriority::class],
    'dispositions' => [ListDispositions::class, TicketDisposition::class],
]);

it('names each organization on a panel serving several', function (string $page, string $model) {
    $model::factory()->create(['tenant_id' => 1, 'display_name' => 'Open']);
    $model::factory()->create(['tenant_id' => 2, 'display_name' => 'Open']);

    Livewire::test($page)
        ->assertTableColumnExists('tenant_id')
        ->assertSee('First Org')
        ->assertSee('Second Org');
})->with('lookup lists');

it('keeps one organization to the rows it owns', function (string $page, string $model) {
    $mine = $model::factory()->create(['tenant_id' => 1, 'display_name' => 'Open']);
    $theirs = $model::factory()->create(['tenant_id' => 2, 'display_name' => 'Open']);

    Livewire::test($page)
        ->filterTable('tenant_id', 1)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
})->with('lookup lists');

it('leaves a single organization panel as it was', function (string $page, string $model) {
    $model::factory()->create(['tenant_id' => 1, 'display_name' => 'Open']);
    $model::factory()->create(['tenant_id' => 1, 'display_name' => 'Closed']);

    Livewire::test($page)
        ->assertTableColumnDoesNotExist('tenant_id')
        ->assertDontSee('First Org');
})->with('lookup lists');
