<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Resources\CopilotConversations\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Padmission\Tickets\Copilot\Models\CopilotConversation;
use Padmission\Tickets\Enums\ActivityType;

class CopilotConversationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('filament-copilot::filament-copilot.title'))
                    ->searchable()
                    ->limit(50),
                TextColumn::make('panel_id')
                    ->label(__('filament-copilot::filament-copilot.panel'))
                    ->badge(),
                TextColumn::make('participant_type')
                    ->label(__('filament-copilot::filament-copilot.participant_type'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('participant_id')
                    ->label(__('filament-copilot::filament-copilot.participant_id'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('messages_count')
                    ->label(__('filament-copilot::filament-copilot.messages'))
                    ->state(fn (CopilotConversation $record): int => $record->ticket?->ticketActivities()
                        ->where('type', ActivityType::Message)
                        ->count() ?? 0)
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('filament-copilot::filament-copilot.created_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label(__('filament-copilot::filament-copilot.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('panel_id')
                    ->label(__('filament-copilot::filament-copilot.panel'))
                    ->options(fn () => CopilotConversation::query()
                        ->distinct()
                        ->pluck('panel_id', 'panel_id')
                        ->toArray()),
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
