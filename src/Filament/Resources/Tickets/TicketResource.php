<?php

namespace Padmission\Tickets\Filament\Resources\Tickets;

use Carbon\CarbonImmutable;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\TicketPlugin;

class TicketResource extends Resource
{
    protected static ?string $slug = 'tickets';

    public static function getModel(): string
    {
        return TicketPlugin::resolveModelClass(Ticket::class);
    }

    public static function getModelLabel(): string
    {
        return __('padmission-tickets::tickets.resources.tickets.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('padmission-tickets::tickets.resources.tickets.plural_model_label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('padmission-tickets::tickets.resources.navigation_group');
    }

    public static function getNavigationIcon(): string|Htmlable|null
    {
        return new HtmlString(<<<'HTML'
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-headset-icon lucide-headset"><path d="M3 11h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-5Zm0 0a9 9 0 1 1 18 0m0 0v5a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3Z"/><path d="M21 16v2a4 4 0 0 1-4 4h-5"/></svg>
        HTML);
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
                    ->tooltip(fn (Ticket $record) => $record->turn->getLabel())
                    ->sortable(),

                TextColumn::make('subject')
                    ->label(__('padmission-tickets::tickets.resources.tickets.subject'))
                    ->searchable(),

                TextColumn::make('assignee.name')
                    ->label(__('padmission-tickets::tickets.resources.tickets.assignee'))
                    ->searchable()
                    ->sortable(),

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
                    ->relationship('assignee', 'name')
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
        $plugin = TicketPlugin::get();

        return parent::getEloquentQuery()->where('escalation_level', $plugin->getEscalationLevel());
    }
}
