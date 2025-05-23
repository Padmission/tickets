<?php

use Filament\Support\Colors\Color;
use Livewire\Livewire;
use Padmission\Tickets\Filament\Resources\Statuses\Pages\ListStatuses;
use Padmission\Tickets\Models\Status;

it('lists statuses', function () {
    Status::factory()->create(['display_name' => 'High', 'color' => 'Red', 'order' => 3, 'panel' => 'test']);
    Status::factory()->create(['display_name' => 'Medium', 'color' => 'Blue', 'order' => 2,  'panel' => 'test']);
    Status::factory()->create(['display_name' => 'Low', 'color' => 'Green', 'order' => 1, 'panel' => 'test']);

    Livewire::test(ListStatuses::class)
        ->assertSee(__('padmission-tickets::tickets.resources.statuses.plural_model_label'))
        ->assertCountTableRecords(3)
        ->assertSeeInOrder([
            'rgb('.Color::Green['600'].')', 'Low',
            'rgb('.Color::Blue['600'].')', 'Medium',
            'rgb('.Color::Red['600'].')', 'High',
        ]);
});

it('only shows statuses from current panel', function () {
    Status::factory()->create(['display_name' => 'Panel 1', 'panel' => 'test']);
    Status::factory()->create(['display_name' => 'Panel 2', 'panel' => 'panel2']);

    Livewire::test(ListStatuses::class)->assertCountTableRecords(1);
});

it('can reorder statuses', function () {
    Status::factory()->create(['display_name' => 'Low', 'color' => 'Green', 'order' => 1]);
    Status::factory()->create(['display_name' => 'Medium', 'color' => 'Blue', 'order' => 2]);
    Status::factory()->create(['display_name' => 'High', 'color' => 'Red', 'order' => 3]);

    Livewire::test(ListStatuses::class)
        ->call('reorderTable', [3, 2, 1])
        ->assertHasNoErrors();

    $this->assertDatabaseHas(Status::class, [
        'id' => 1,
        'order' => 3,
    ]);
});

it('can create status', function () {
    Livewire::test(ListStatuses::class)
        ->callAction('create', [
            'display_name' => '',
            'color' => '',
        ])
        ->assertHasActionErrors(['display_name', 'color'])
        ->callAction('create', [
            'display_name' => 'New Status',
            'color' => 'Zinc',
        ])
        ->assertHasNoActionErrors();

    $this->assertDatabaseHas(Status::class, [
        'display_name' => 'New Status',
        'color' => 'Zinc',
        'order' => 99,
    ]);
});

it('can edit status', function () {
    $status = Status::factory()->create(['display_name' => 'Low', 'color' => 'Green', 'order' => 1]);

    Livewire::test(ListStatuses::class)
        ->callTableAction('edit', $status, [
            'display_name' => 'New Name',
            'color' => 'Red',
        ])
        ->assertHasNoActionErrors();

    $this->assertDatabaseHas(Status::class, [
        'id' => $status->id,
        'display_name' => 'New Name',
        'color' => 'Red',
        'order' => 1,
    ]);
});

it('can delete status', function () {
    $status = Status::factory()->create(['display_name' => 'Low', 'color' => 'Green', 'order' => 1]);

    Livewire::test(ListStatuses::class)
        ->callTableAction('delete', $status->id)
        ->assertHasNoErrors();

    $this->assertSoftDeleted(Status::class, [
        'id' => $status->id,
    ]);
});
