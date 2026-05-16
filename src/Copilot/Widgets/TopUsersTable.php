<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Model;
use Padmission\Tickets\Copilot\Models\CopilotTokenUsage;

class TopUsersTable extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 1;

    public function getTableRecordKey(Model|array $record): string
    {
        if (is_array($record)) {
            return ($record['participant_type'] ?? '').':'.($record['participant_id'] ?? '');
        }

        return $record->participant_type.':'.$record->participant_id;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('filament-copilot::filament-copilot.top_users_this_month'))
            ->query(
                CopilotTokenUsage::query()
                    ->where('usage_date', '>=', now()->startOfMonth()->toDateString())
                    ->selectRaw('participant_type, participant_id, SUM(total_tokens) as total, SUM(input_tokens) as input_sum, SUM(output_tokens) as output_sum, COUNT(*) as request_count')
                    ->groupBy('participant_type', 'participant_id')
            )
            ->defaultSort('total', 'desc')
            ->defaultKeySort(false)
            ->columns([
                Tables\Columns\TextColumn::make('participant_type')
                    ->label(__('filament-copilot::filament-copilot.model'))
                    ->formatStateUsing(fn (string $state): string => class_basename($state))
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('participant_id')
                    ->label(__('filament-copilot::filament-copilot.id')),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('filament-copilot::filament-copilot.name'))
                    ->getStateUsing(function ($record): string {
                        $model = $record->participant_type;
                        if (! class_exists($model)) {
                            return '-';
                        }
                        $user = $model::find($record->participant_id);

                        return $user?->name ?? '-';
                    }),
                Tables\Columns\TextColumn::make('request_count')
                    ->label(__('filament-copilot::filament-copilot.requests'))
                    ->numeric(),
                Tables\Columns\TextColumn::make('input_sum')
                    ->label(__('filament-copilot::filament-copilot.input_tokens'))
                    ->numeric(),
                Tables\Columns\TextColumn::make('output_sum')
                    ->label(__('filament-copilot::filament-copilot.output_tokens'))
                    ->numeric(),
                Tables\Columns\TextColumn::make('total')
                    ->label(__('filament-copilot::filament-copilot.total_tokens'))
                    ->numeric()
                    ->sortable(),
            ])
            ->paginated();
    }
}
