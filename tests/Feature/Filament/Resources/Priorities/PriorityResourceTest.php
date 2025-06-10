<?php

use Filament\Support\Colors\Color;
use Livewire\Livewire;
use Padmission\Tickets\Filament\Resources\Priorities\Pages\ListPriorities;
use Padmission\Tickets\Models\TicketPriority;

it('lists priorities', function () {
    TicketPriority::factory()->create(['display_name' => 'High', 'color' => 'Red', 'order' => 3]);
    TicketPriority::factory()->create(['display_name' => 'Medium', 'color' => 'Blue', 'order' => 2]);
    TicketPriority::factory()->create(['display_name' => 'Low', 'color' => 'Green', 'order' => 1]);

    Livewire::test(ListPriorities::class)
        ->assertSee(__('padmission-tickets::tickets.resources.priorities.plural_model_label'))
        ->assertSeeInOrder([
            'rgb('.Color::Green['600'].')', 'Low',
            'rgb('.Color::Blue['600'].')', 'Medium',
            'rgb('.Color::Red['600'].')', 'High',
        ]);
});

it('only shows priorities from current panel', function () {
    TicketPriority::factory()->create(['display_name' => 'Panel 1', 'panel' => 'test']);
    TicketPriority::factory()->create(['display_name' => 'Panel 2', 'panel' => 'panel2']);

    Livewire::test(ListPriorities::class)->assertCountTableRecords(1);
});

it('can reorder priorities', function () {
    TicketPriority::factory()->create(['display_name' => 'Low', 'color' => 'Green', 'order' => 1]);
    TicketPriority::factory()->create(['display_name' => 'Medium', 'color' => 'Blue', 'order' => 2]);
    TicketPriority::factory()->create(['display_name' => 'High', 'color' => 'Red', 'order' => 3]);

    Livewire::test(ListPriorities::class)
        ->call('reorderTable', [3, 2, 1])
        ->assertHasNoErrors();

    $this->assertDatabaseHas(TicketPriority::class, [
        'id' => 1,
        'order' => 3,
    ]);
});

it('can create priority', function () {
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

    $this->assertDatabaseHas(TicketPriority::class, [
        'display_name' => 'New Priority',
        'color' => 'Zinc',
        'order' => 99,
    ]);
});

it('can edit priority', function () {
    $priority = TicketPriority::factory()->create(['display_name' => 'Low', 'color' => 'Green', 'order' => 1]);

    Livewire::test(ListPriorities::class)
        ->callTableAction('edit', $priority, [
            'display_name' => 'New Name',
            'color' => 'Red',
        ])
        ->assertHasNoActionErrors();

    $this->assertDatabaseHas('ticket_priorities', [
        'id' => $priority->id,
        'display_name' => 'New Name',
        'color' => 'Red',
        'order' => 1,
    ]);
});

it('can delete status', function () {
    $priority = TicketPriority::factory()->create(['display_name' => 'Low', 'color' => 'Green', 'order' => 1]);

    Livewire::test(ListPriorities::class)
        ->callTableAction('delete', $priority->id)
        ->assertHasNoErrors();

    $this->assertSoftDeleted(TicketPriority::class, ['id' => $priority->id]);
});
