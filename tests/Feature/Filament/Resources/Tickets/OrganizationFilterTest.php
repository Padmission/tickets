<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\Fixtures\Models\NamedTenant;
use Padmission\Tickets\Tests\Fixtures\Models\Tenant;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

beforeEach(function () {
    $this->login();
    Schema::create('tenants', function (Blueprint $table) {
        $table->id();
        $table->string('name');
    });
    Schema::table('tickets', fn (Blueprint $table) => $table->unsignedBigInteger('tenant_id')->nullable());
    config()->set('padmission-tickets.tenancy', ['enabled' => true, 'tenancy_model' => Tenant::class]);

    $this->alpha = Tenant::create(['name' => 'Alpha Housing']);
    $this->beta = Tenant::create(['name' => 'Beta Services']);
    $this->unused = Tenant::create(['name' => 'Unused Organization']);
    $this->foreign = Tenant::create(['name' => 'Other Panel']);
    $this->a = Ticket::factory()->create(['tenant_id' => $this->alpha->id, 'subject' => 'Cannot upload a form']);
    $this->b = Ticket::factory()->create(['tenant_id' => $this->beta->id, 'subject' => 'Export failed']);
    $this->closed = Ticket::factory()->create(['tenant_id' => $this->alpha->id, 'closed_at' => now()]);
    $this->other = Ticket::factory()->create(['tenant_id' => $this->foreign->id, 'panel' => 'test2']);
});

it('offers only organizations with accessible tickets in this panel and uses Filament names', function (string $model, string $prefix) {
    config()->set('padmission-tickets.tenancy.tenancy_model', $model);
    $page = Livewire::test(ListTickets::class, ['activeTab' => 'all']);
    $filter = $page->instance()->getTable()->getFilter('organization');

    expect($filter->isVisible())->toBeTrue()
        ->and($filter->getLabel())->toBe('Organization')
        ->and($filter->isMultiple())->toBeTrue()
        ->and($filter->getFormField()->isSearchable())->toBeTrue()
        ->and($filter->getOptions())->toBe([
            $this->alpha->id => $prefix.'Alpha Housing',
            $this->beta->id => $prefix.'Beta Services',
        ]);
})->with([[Tenant::class, ''], [NamedTenant::class, 'Organization: ']]);

it('filters by several organizations alongside open-only and clears the selection', function () {
    $page = Livewire::test(ListTickets::class, ['activeTab' => 'all']);
    $page->filterTable('organization', [$this->alpha->id])
        ->assertCanSeeTableRecords([$this->a])
        ->assertCanNotSeeTableRecords([$this->b, $this->closed, $this->other]);
    $page->filterTable('organization', [$this->alpha->id, $this->beta->id])
        ->assertCanSeeTableRecords([$this->a, $this->b])
        ->assertCanNotSeeTableRecords([$this->closed, $this->other]);
    $page->removeTableFilter('open')->assertCanSeeTableRecords([$this->closed]);
    $page->removeTableFilter('organization')->assertCanSeeTableRecords([$this->a, $this->b]);
});

it('searches organization display names without losing subject and requester search', function (string $model, string $search) {
    config()->set('padmission-tickets.tenancy.tenancy_model', $model);
    $submitter = User::factory()->create(['name' => 'Distinct Requester']);
    $this->b->update(['submitter_id' => $submitter->id]);
    $page = Livewire::test(ListTickets::class, ['activeTab' => 'all']);

    $page->searchTable($search)->assertCanSeeTableRecords([$this->a])
        ->assertCanNotSeeTableRecords([$this->b, $this->closed, $this->other]);
    $page->searchTable('Export failed')->assertCanSeeTableRecords([$this->b])->assertCanNotSeeTableRecords([$this->a]);
    $page->searchTable('Distinct Requester')->assertCanSeeTableRecords([$this->b])->assertCanNotSeeTableRecords([$this->a]);
    $page->filterTable('organization', [$this->alpha->id])->assertCanNotSeeTableRecords([$this->b]);
})->with([[Tenant::class, 'aLpHa housing'], [NamedTenant::class, 'Organization: Alpha']]);

it('disables the organization filter and search for a single-organization panel', function () {
    TicketPlugin::get()->customizeTicketQuery(fn ($query) => $query->where('tickets.tenant_id', $this->alpha->id));
    $page = Livewire::test(ListTickets::class, ['activeTab' => 'all']);

    expect($page->instance()->getTable()->getFilter('organization', withHidden: true)->isHidden())->toBeTrue();
    $page->searchTable('Alpha Housing')->assertCanNotSeeTableRecords([$this->a]);
    $page->searchTable('upload')->assertCanSeeTableRecords([$this->a])->assertCanNotSeeTableRecords([$this->b]);
});

it('disables the organization filter and search when tenancy is off', function () {
    config()->set('padmission-tickets.tenancy.enabled', false);
    $page = Livewire::test(ListTickets::class, ['activeTab' => 'all']);

    expect($page->instance()->getTable()->getFilter('organization', withHidden: true)->isHidden())->toBeTrue();
    $page->searchTable('Alpha Housing')->assertCanNotSeeTableRecords([$this->a, $this->b]);
    $page->searchTable('upload')->assertCanSeeTableRecords([$this->a]);
});
