<?php

use Filament\Support\Colors\Color;
use Livewire\Livewire;
use Padmission\Tickets\Filament\Resources\Priorities\Pages\ListPriorities;
use Padmission\Tickets\Models\Priority;

it('lists priorities', function () {
    Priority::factory()->create(['display_name' => 'High', 'color' => 'Red', 'order' => 3]);
    Priority::factory()->create(['display_name' => 'Medium', 'color' => 'Blue', 'order' => 2]);
    Priority::factory()->create(['display_name' => 'Low', 'color' => 'Green', 'order' => 1]);

    Livewire::test(ListPriorities::class)
        ->assertSee(__('padmission-tickets::tickets.resources.priorities.plural_model_label'))
        ->assertSeeInOrder([
            'rgb('.Color::Green['600'].')', 'Low',
            'rgb('.Color::Blue['600'].')', 'Medium',
            'rgb('.Color::Red['600'].')', 'High',
        ]);
});


it('only shows priorities from current panel', function () {
    Priority::factory()->create(['display_name' => 'Panel 1', 'panel' => 'test']);
    Priority::factory()->create(['display_name' => 'Panel 2', 'panel' => 'panel2']);

    Livewire::test(ListPriorities::class)->assertCountTableRecords(1);
});

it('can reorder priorities', function () {
    Priority::factory()->create(['display_name' => 'Low', 'color' => 'Green', 'order' => 1]);
    Priority::factory()->create(['display_name' => 'Medium', 'color' => 'Blue', 'order' => 2]);
    Priority::factory()->create(['display_name' => 'High', 'color' => 'Red', 'order' => 3]);

    Livewire::test(ListPriorities::class)
        ->call('reorderTable', [3, 2, 1])
        ->assertHasNoErrors();

    $this->assertDatabaseHas('ticket_priorities', [
        'id' => 1,
        'order' => 3,
    ]);
});

it('can create status', function () {
    Livewire::test(ListPriorities::class)
        ->callAction('create', [
            'display_name' => '',
            'color' => '',
        ])
        ->assertHasActionErrors(['display_name', 'color'])
        ->callAction('create', [
            'display_name' => 'New Priority',
            'color' => 'Zinc',
        ])
        ->assertHasNoActionErrors();

    $this->assertDatabaseHas('ticket_priorities', [
        'display_name' => 'New Priority',
        'color' => 'Zinc',
        'order' => 99,
    ]);
});

it('can edit status', function () {
    $status = Priority::factory()->create(['display_name' => 'Low', 'color' => 'Green', 'order' => 1]);

    Livewire::test(ListPriorities::class)
        ->callTableAction('edit', $status, [
            'display_name' => 'New Name',
            'color' => 'Red',
        ])
        ->assertHasNoActionErrors();

    $this->assertDatabaseHas('ticket_priorities', [
        'id' => $status->id,
        'display_name' => 'New Name',
        'color' => 'Red',
        'order' => 1,
    ]);
});

it('can delete status', function () {
    $status = Priority::factory()->create(['display_name' => 'Low', 'color' => 'Green', 'order' => 1]);

    Livewire::test(ListPriorities::class)
        ->callTableAction('delete', $status->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('ticket_priorities', [
        'id' => $status->id,
    ]);
});
