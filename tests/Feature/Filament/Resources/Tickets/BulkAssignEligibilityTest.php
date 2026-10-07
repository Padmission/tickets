<?php

use Livewire\Livewire;
use Padmission\Tickets\Database\Seeders\TicketStatusSeeder;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Tests\User;
use Padmission\Tickets\TicketPlugin;

/*
 * A host scopes the pool by the ticket's tenant when given one, as the README
 * asks, so a supporter of one tenant is not someone a ticket of another can
 * be assigned to, though both show in a list that holds both tenants.
 */
beforeEach(function () {
    (new TicketStatusSeeder)->run();

    $this->me = $this->login();

    $this->maria = User::factory()->create(['name' => 'Maria Lopez']);
    $this->kevin = User::factory()->create(['name' => 'Kevin McKee']);

    TicketPlugin::get()->allSupportersQuery(fn (?Ticket $ticket = null) => match ($ticket?->subject) {
        'Tenant B' => User::query()->whereKey($this->kevin->id),
        'Tenant A' => User::query()->whereKey($this->maria->id),
        default => User::query()->whereKey([$this->me->id, $this->maria->id, $this->kevin->id]),
    });

    $this->tenantA = Ticket::factory()->open()->create(['subject' => 'Tenant A', 'assignee_id' => null]);
    $this->tenantB = Ticket::factory()->open()->create(['subject' => 'Tenant B', 'assignee_id' => null]);
});

it('assigns only the tickets the person may be assigned, as Reassign does', function () {
    Livewire::test(ListTickets::class, ['activeTab' => 'all'])
        ->callTableBulkAction('assign', [$this->tenantA, $this->tenantB], ['assignee_id' => $this->maria->id])
        ->assertNotified(__('padmission-tickets::tickets.resources.tickets.ineligible_assignment'));

    expect($this->tenantA->refresh()->assignee_id)->toBe($this->maria->id)
        ->and($this->tenantB->refresh()->assignee_id)->toBeNull();
});

it('assigns none when the person may be assigned none of them', function () {
    $alsoTenantB = Ticket::factory()->open()->create(['subject' => 'Tenant B', 'assignee_id' => null]);

    Livewire::test(ListTickets::class, ['activeTab' => 'all'])
        ->callTableBulkAction('assign', [$this->tenantB, $alsoTenantB], ['assignee_id' => $this->maria->id])
        ->assertNotified(__('padmission-tickets::tickets.resources.tickets.invalid_assignee'));

    expect($this->tenantB->refresh()->assignee_id)->toBeNull()
        ->and($alsoTenantB->refresh()->assignee_id)->toBeNull();
});
