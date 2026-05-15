<?php

declare(strict_types=1);

// Originally by Eslam Reda (eslam-reda-div/filament-copilot, MIT). See LICENSE.md.

namespace Padmission\Tickets\Copilot\Resources\CopilotAuditLogs\Tables;

use Padmission\Tickets\Copilot\Enums\AuditAction;
use Padmission\Tickets\Copilot\Models\CopilotAuditLog;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\KeyValue;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CopilotAuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('action')
                    ->label(__('filament-copilot::filament-copilot.action'))
                    ->badge()
                    ->color(fn (AuditAction $state): string => match (true) {
                        in_array($state, [AuditAction::RecordCreated, AuditAction::RecordUpdated, AuditAction::RecordDeleted]) => 'danger',
                        in_array($state, [AuditAction::PlanApproved, AuditAction::ApprovalGranted]) => 'success',
                        in_array($state, [AuditAction::PlanRejected, AuditAction::ApprovalDenied, AuditAction::ToolRejected]) => 'warning',
                        in_array($state, [AuditAction::RateLimitHit]) => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('panel_id')
                    ->label(__('filament-copilot::filament-copilot.panel'))
                    ->badge(),
                TextColumn::make('participant_type')
                    ->label(__('filament-copilot::filament-copilot.participant_type'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('participant_id')
                    ->label(__('filament-copilot::filament-copilot.participant_id'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('resource_type')
                    ->label(__('filament-copilot::filament-copilot.resource'))
                    ->toggleable(),
                TextColumn::make('record_key')
                    ->label(__('filament-copilot::filament-copilot.record'))
                    ->toggleable(),
                TextColumn::make('ip_address')
                    ->label(__('filament-copilot::filament-copilot.ip_address'))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('filament-copilot::filament-copilot.time'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('action')
                    ->label(__('filament-copilot::filament-copilot.action'))
                    ->options(AuditAction::class),
                SelectFilter::make('panel_id')
                    ->label(__('filament-copilot::filament-copilot.panel'))
                    ->options(fn () => CopilotAuditLog::query()
                        ->distinct()
                        ->pluck('panel_id', 'panel_id')
                        ->toArray()),
            ])
            ->recordActions([
                ViewAction::make()
                    ->form([
                        KeyValue::make('payload')
                            ->label(__('filament-copilot::filament-copilot.payload'))
                            ->disabled(),
                    ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
