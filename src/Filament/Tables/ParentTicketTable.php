<?php

namespace Padmission\Tickets\Filament\Tables;

use Filament\Panel;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Padmission\Tickets\TicketPlugin;

class ParentTicketTable
{
    public static function configure(Table $table): Table
    {
        $panels = TicketPlugin::get()->getLinkedTicketParentPanels();
        $panelIds = array_map(fn (Panel $panel) => $panel->getId(), $panels);

        return $table
            ->modifyQueryUsing(function ($livewire, $query) use ($panelIds) {
                return $query
                    ->whereKeyNot($livewire->record->getKey())
                    ->whereIn('panel', $panelIds);
            })
            ->columns([
                TextColumn::make('panel')
                    ->label(__('padmission-tickets::tickets.resources.tickets.panel'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => ucfirst($state))
                    ->visible(fn () => count($panelIds) > 1),

                TextColumn::make('status.display_name')
                    ->label(__('padmission-tickets::tickets.resources.statuses.model_label'))
                    ->badge()
                    ->color(fn ($record) => $record->status->colorPalette),

                TextColumn::make('priority.display_name')
                    ->label(__('padmission-tickets::tickets.resources.priorities.model_label'))
                    ->badge()
                    ->color(fn ($record) => $record->priority->colorPalette),

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
