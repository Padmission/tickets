<?php

use Livewire\Livewire;
use Padmission\Tickets\Filament\Resources\Dispositions\Pages\ListDispositions;
use Padmission\Tickets\Models\TicketDisposition;

it('lists dispositions', function () {
    TicketDisposition::factory()->create(['display_name' => 'Follow Up', 'color' => 'Blue', 'order' => 3, 'panel' => 'test']);
    TicketDisposition::factory()->create(['display_name' => 'Escalated', 'color' => 'Red', 'order' => 2,  'panel' => 'test']);
    TicketDisposition::factory()->create(['display_name' => 'Resolved', 'color' => 'Green', 'order' => 1, 'panel' => 'test']);

    Livewire::test(ListDispositions::class)
        ->assertSee(__('padmission-tickets::tickets.resources.dispositions.plural_model_label'))
        ->assertCountTableRecords(3);
});

it('only shows dispositions from current panel', function () {
    TicketDisposition::factory()->create(['display_name' => 'Panel 1', 'color' => 'Blue', 'panel' => 'test']);
    TicketDisposition::factory()->create(['display_name' => 'Panel 2', 'color' => 'Red', 'panel' => 'panel2']);

    Livewire::test(ListDispositions::class)->assertCountTableRecords(1);
});

it('can reorder dispositions', function () {
    TicketDisposition::factory()->create(['display_name' => 'Resolved', 'color' => 'Green', 'order' => 1]);
    TicketDisposition::factory()->create(['display_name' => 'Escalated', 'color' => 'Red', 'order' => 2]);
    TicketDisposition::factory()->create(['display_name' => 'Follow Up', 'color' => 'Blue', 'order' => 3]);

    Livewire::test(ListDispositions::class)
        ->call('reorderTable', [3, 2, 1])
        ->assertHasNoErrors();

    $this->assertDatabaseHas(TicketDisposition::class, [
        'id' => 1,
        'order' => 3,
    ]);
});

it('can create disposition', function () {
    Livewire::test(ListDispositions::class)
        ->callAction('create', [
            'display_name' => '',
            'color' => '',
        ])
        ->assertHasActionErrors(['display_name', 'color'])
        ->callAction('create', [
            'display_name' => 'New Disposition',
            'color' => 'Purple',
        ])
        ->assertHasNoActionErrors();

    $this->assertDatabaseHas(TicketDisposition::class, [
        'display_name' => 'New Disposition',
        'color' => 'Purple',
        'order' => 99,
    ]);
});
