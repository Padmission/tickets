<?php

use Filament\Forms\Components\ModalTableSelect;
use Padmission\Tickets\Filament\Forms\Components\LinkedTicketModalSelect;
use Padmission\Tickets\Filament\Tables\TicketsTable;

it('extends ModalTableSelect', function () {
    expect(LinkedTicketModalSelect::make('test'))
        ->toBeInstanceOf(ModalTableSelect::class);
});

it('uses TicketsTable configuration', function () {
    $tableConfiguration = LinkedTicketModalSelect::make('test')->getTableConfiguration();

    expect($tableConfiguration)->toBe(TicketsTable::class);
});

it('has correct placeholder for single selection', function () {
    $placeholder = LinkedTicketModalSelect::make('test')->getPlaceholder();

    expect($placeholder)->toBe('Not linked');
});

it('has correct placeholder for multiple selection', function () {
    $placeholder = LinkedTicketModalSelect::make('test')
        ->multiple()
        ->getPlaceholder();

    expect($placeholder)->toBe('No tickets linked');
});
