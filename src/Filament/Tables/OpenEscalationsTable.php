<?php

namespace Padmission\Tickets\Filament\Tables;

use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

class OpenEscalationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function ($livewire, Builder $query): Builder {
                $model = $query->getModel();
                $originals = $model->newQueryWithoutScopes()
                    ->from($model->getTable(), 'originals')
                    ->selectRaw('count(*)')
                    ->whereColumn('originals.linked_ticket_id', $model->qualifyColumn($model->getKeyName()));

                return LinkedTicketCandidates::openEscalations($query, $livewire->record)
                    ->select($model->qualifyColumn('*'))
                    ->selectSub($originals, 'originals_count');
            })
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->prefix('#')
                    ->searchable(),

                TextColumn::make('subject')
                    ->label(__('padmission-tickets::tickets.resources.tickets.subject'))
                    ->html()
                    ->searchable(),

                TextColumn::make('status.display_name')
                    ->label(__('padmission-tickets::tickets.resources.statuses.model_label'))
                    ->badge()
                    ->color(fn (Ticket $record) => $record->status?->colorPalette),

                TextColumn::make('submitter.name')
                    ->label(__('padmission-tickets::tickets.resources.tickets.handled_by'))
                    ->formatStateUsing(fn (?string $state, Ticket $record): ?string => $record->isSubmittedBy(Filament::auth()->id())
                        ? __('padmission-tickets::tickets.side_you')
                        : $state)
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label(__('padmission-tickets::tickets.actions.add_to_escalation.escalated_at'))
                    ->since()
                    ->tooltip(fn (Ticket $record): ?string => TicketPlugin::formatMessageTime($record->created_at))
                    ->sortable(),

                TextColumn::make('originals_count')
                    ->label(__('padmission-tickets::tickets.actions.add_to_escalation.attached'))
                    ->numeric(),
            ]);
    }
}
