<?php

namespace Padmission\Tickets\Filament\Resources\Tickets;

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Resources\Concerns\HasResourceConfiguration;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\TicketPlugin;
use function app;

class TicketResource extends Resource
{
    use HasResourceConfiguration;

    protected static ?string $slug = 'tickets';

    public static function getModel(): string
    {
        return TicketPlugin::resolveModelClass(Ticket::class);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(function (Builder $query): Builder {
                return $query
                    ->orderByRaw(
                        'CASE
                            WHEN turn = ? THEN 0
                            WHEN turn = ? THEN 1
                        END',
                        [Turn::Supporter->value, Turn::User->value]
                    )
                    ->orderBy(
                        fn ($query) => $query
                            ->select('created_at')
                            ->from((new (TicketPlugin::resolveModelClass(TicketActivity::class)))->getTable())
                            ->whereColumn('ticket_id', 'tickets.id')
                            ->latest()
                            ->limit(1),
                        'desc'
                    );
            })
            ->columns([
                TextColumn::make('status.display_name')
                    ->label(__('padmission-tickets::tickets.resources.statuses.model_label'))
                    ->badge()
                    ->color(fn ($record) => $record->status->colorPalette)
                    ->sortable(),

                TextColumn::make('priority.display_name')
                    ->label(__('padmission-tickets::tickets.resources.priorities.model_label'))
                    ->badge()
                    ->color(fn ($record) => $record->priority->colorPalette)
                    ->sortable(),

                IconColumn::make('turn')
                    ->label(__('padmission-tickets::tickets.resources.tickets.turn'))
                    ->tooltip(fn ($record) => $record->turn->getLabel())
                    ->sortable(),

                TextColumn::make('subject')
                    ->label(__('padmission-tickets::tickets.resources.tickets.subject'))
                    ->html()
                    ->searchable(),

                TextColumn::make('assignee.name')
                    ->label(__('padmission-tickets::tickets.resources.tickets.assignee'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('source_panel')
                    ->label(__('padmission-tickets::tickets.resources.tickets.source_panel'))
                    ->formatStateUsing(fn ($state) => $state ? ucfirst($state) : '-')
                    ->sortable()
                    ->visible(fn () => static::shouldShowSourcePanel()),

                TextColumn::make('latestMessage.created_at')
                    ->label(__('padmission-tickets::tickets.resources.tickets.last_message'))
                    ->formatStateUsing(fn (?CarbonImmutable $state) => $state?->diffForHumans())
                    ->tooltip(fn (?CarbonImmutable $state) => $state?->format(Table::$defaultDateTimeDisplayFormat))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->relationship('status', 'display_name')
                    ->default(fn () => TicketPlugin::resolveModelClass(TicketStatus::class)::getOpenStatuses()->pluck('id')->toArray())
                    ->multiple()
                    ->preload(),

                SelectFilter::make('priority')
                    ->relationship('priority', 'display_name')
                    ->multiple()
                    ->preload(),

                SelectFilter::make('assignee')
                    ->relationship('assignee', 'name', function ($query) {
                        $allSupportersQuery = \Padmission\Tickets\TicketPlugin::get()->getAllSupportersQuery();

                        if ($allSupportersQuery) {
                            $supporterIds = app()->call($allSupportersQuery)->pluck('id');

                            return $query->whereIn('id', $supporterIds);
                        }

                        return $query;
                    })
                    ->preload(),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTickets::route('/'),
            'view' => Pages\ViewTicket::route('/{record}/view'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('panel', Filament::getCurrentPanel()->getId());
    }

    public static function shouldShowSourcePanel(): bool
    {
        // Count panels that have the chat widget enabled
        $panelsWithChatWidget = 0;

        foreach (Filament::getPanels() as $panel) {
            try {
                $plugin = TicketPlugin::get($panel->getId());
                if ($plugin->shouldShowChatWidget()) {
                    $panelsWithChatWidget++;
                    if ($panelsWithChatWidget > 1) {
                        return true;
                    }
                }
            } catch (\Exception $e) {
                // Panel might not have the plugin registered
                continue;
            }
        }

        return false;
    }
}
