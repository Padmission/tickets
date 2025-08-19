<?php

use Filament\Actions\Testing\TestAction;
use Filament\Support\Colors\Color;
use Livewire\Livewire;
use Padmission\Tickets\Filament\Resources\Statuses\Pages\ListStatuses;
use Padmission\Tickets\Filament\Resources\Statuses\StatusResource;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Tests\Fixtures\TestTicketPolicy;

it('lists statuses', function () {
    $this->login();

    TicketStatus::factory()->create(['display_name' => 'High', 'color' => 'Red', 'order' => 3, 'panel' => 'test']);
    TicketStatus::factory()->create(['display_name' => 'Medium', 'color' => 'Blue', 'order' => 2,  'panel' => 'test']);
    TicketStatus::factory()->create(['display_name' => 'Low', 'color' => 'Green', 'order' => 1, 'panel' => 'test']);

    Livewire::test(ListStatuses::class)
        ->assertSee(__('padmission-tickets::tickets.resources.statuses.plural_model_label'))
        ->assertCountTableRecords(3)
        ->assertSeeInOrder([
            Color::Green['600'], 'Low',
            Color::Blue['600'], 'Medium',
            Color::Red['600'], 'High',
        ]);
});

it('only shows statuses from current panel', function () {
    $this->login();

    TicketStatus::factory()->create(['display_name' => 'Panel 1', 'panel' => 'test']);
    TicketStatus::factory()->create(['display_name' => 'Panel 2', 'panel' => 'panel2']);

    Livewire::test(ListStatuses::class)->assertCountTableRecords(1);
});

it('can reorder statuses', function () {
    $this->login();

    TicketStatus::factory()->create(['display_name' => 'Low', 'color' => 'Green', 'order' => 1]);
    TicketStatus::factory()->create(['display_name' => 'Medium', 'color' => 'Blue', 'order' => 2]);
    TicketStatus::factory()->create(['display_name' => 'High', 'color' => 'Red', 'order' => 3]);

    Livewire::test(ListStatuses::class)
        ->call('reorderTable', [3, 2, 1])
        ->assertHasNoErrors();

    $this->assertDatabaseHas(TicketStatus::class, [
        'id' => 1,
        'order' => 3,
    ]);
});

it('can create status', function () {
    $this->login();

    Livewire::test(ListStatuses::class)
        ->callAction(
            'create',
            [
                'display_name' => '',
                'color' => '',
            ]
        )
        ->assertHasFormErrors(['display_name', 'color']);

    Livewire::test(ListStatuses::class)
        ->callAction(
            'create',
            [
                'display_name' => 'New Status',
                'color' => 'Red',
            ]
        )
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas(TicketStatus::class, [
        'display_name' => 'New Status',
        'color' => 'Red',
        'order' => 99,
    ]);
});

it('can edit status', function () {
    $this->login();

    $status = TicketStatus::factory()->create(['display_name' => 'Low', 'color' => 'Green', 'order' => 1]);

    Livewire::test(ListStatuses::class)
        ->callAction(
            TestAction::make('edit')->table($status),
            [
                'display_name' => 'New Name',
                'color' => 'Red',
            ]
        )
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas(TicketStatus::class, [
        'id' => $status->id,
        'display_name' => 'New Name',
        'color' => 'Red',
        'order' => 1,
    ]);
});

it('can delete status', function () {
    $this->login();

    $status = TicketStatus::factory()->create(['display_name' => 'Low', 'color' => 'Green', 'order' => 1]);

    Livewire::test(ListStatuses::class)
        ->callAction(TestAction::make('delete')->table($status))
        ->assertHasNoErrors();

    $this->assertSoftDeleted(TicketStatus::class, [
        'id' => $status->id,
    ]);
});

it('uses ticket viewAny as fallback', function () {
    $this->login();

    Gate::policy(TicketStatus::class, null);

    $this
        ->partialMock(TestTicketPolicy::class)
        ->shouldReceive('viewAny')
        ->andReturn(true);

    $this
        ->get(StatusResource::getUrl())
        ->assertOk();

    $this
        ->partialMock(TestTicketPolicy::class)
        ->shouldReceive('viewAny')
        ->andReturn(false);

    $this
        ->get(StatusResource::getUrl())
        ->assertForbidden();

    class StatusResourceTestPolicy
    {
        public function viewAny(): bool
        {
            return true;
        }
    }

    Gate::policy(TicketStatus::class, StatusResourceTestPolicy::class);

    $this
        ->get(StatusResource::getUrl())
        ->assertOk();
});
