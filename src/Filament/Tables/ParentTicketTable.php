<?php

namespace Padmission\Tickets\Filament\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

class ParentTicketTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($livewire, Builder $query) => LinkedTicketCandidates::parents($query, $livewire->record))
            ->columns([
                TextColumn::make('panel')
                    ->label(__('padmission-tickets::tickets.resources.tickets.panel'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->visible(fn ($livewire) => count(TicketPlugin::get($livewire->record->panel)->getLinkedTicketParentPanels()) > 1),

                TextColumn::make('status.display_name')
                    ->label(__('padmission-tickets::tickets.resources.statuses.model_label'))
                    ->badge()
                    ->color(fn (Ticket $record) => $record->status->colorPalette),

                TextColumn::make('priority.display_name')
                    ->label(__('padmission-tickets::tickets.resources.priorities.model_label'))
                    ->badge()
                    ->color(fn (Ticket $record) => $record->priority->colorPalette),

                TextColumn::make('subject')
                    ->label(__('padmission-tickets::tickets.resources.tickets.subject'))
                    ->html()
                    ->searchable(),

                TextColumn::make('assignee.name')
                    ->label(__('padmission-tickets::tickets.resources.tickets.assignee'))
                    ->searchable()
                    ->sortable(),
            ]);
    }
}
