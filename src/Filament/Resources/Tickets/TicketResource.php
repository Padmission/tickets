<?php

namespace Padmission\Tickets\Filament\Resources\Tickets;

use Carbon\CarbonImmutable;
use Exception;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Resources\Concerns\HasResourceConfiguration;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Filament\Widgets\OpenSupporterTickets;
use Padmission\Tickets\Filament\Widgets\OpenTicketsWidget;
use Padmission\Tickets\Filament\Widgets\TicketCloseTimeWidget;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Models\TicketStatus;
use Padmission\Tickets\TicketPlugin;

use function app;
use function auth;

class TicketResource extends Resource
{
    use HasResourceConfiguration;

    protected static ?string $slug = 'tickets';

    public static function getNavigationParentItem(): ?string
    {
        return null;
    }

    public static function getNavigationBadge(): ?string
    {
        /** @phpstan-ignore-next-line */
        return (string) TicketPlugin::get()->getTicketQuery()
            ->open()
            ->tap(new CurrentPanelScope)
            ->where('assignee_id', auth()->id())
            ->count();
    }

    public static function getModel(): string
    {
        return TicketPlugin::resolveModelClass(Ticket::class);
    }

    public static function getEloquentQuery(): Builder
    {
        return TicketPlugin::get()->getTicketQuery();
    }

    public static function getWidgets(): array
    {
        return [
            OpenTicketsWidget::class,
            OpenSupporterTickets::class,
            TicketCloseTimeWidget::class,
        ];
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
                TextColumn::make('panel')
                    ->label(__('padmission-tickets::tickets.resources.tickets.panel'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? ucfirst($state) : '-')
                    ->visible(fn (ListTickets $livewire) => str_contains($livewire->activeTab, 'linked'))
                    ->sortable(),

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

                TextColumn::make('submitter.name')
                    ->label(__('padmission-tickets::tickets.resources.tickets.submitter'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('assignee.name')
                    ->label(__('padmission-tickets::tickets.resources.tickets.assignee'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('source_panel')
                    ->label(__('padmission-tickets::tickets.resources.tickets.source_panel'))
                    ->formatStateUsing(fn ($state) => $state ? ucfirst($state) : '-')
                    ->sortable()
                    ->visible(fn (ListTickets $livewire) => static::shouldShowSourcePanel($livewire)),

                TextColumn::make('latestMessage.created_at')
                    ->label(__('padmission-tickets::tickets.resources.tickets.last_message'))
                    ->formatStateUsing(fn (?CarbonImmutable $state) => $state?->diffForHumans())
                    ->tooltip(fn (?CarbonImmutable $state) => $state?->format(TicketPlugin::get()->getDateTimeDisplayFormat()))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->relationship('status', 'display_name')
                    ->default(fn () => TicketPlugin::resolveModelClass(TicketStatus::class)::getOpenStatuses()->pluck('id')->toArray())
                    ->hidden(fn (ListTickets $livewire) => str_contains($livewire->activeTab, 'linked'))
                    ->multiple()
                    ->preload(),

                SelectFilter::make('priority')
                    ->relationship('priority', 'display_name')
                    ->hidden(fn (ListTickets $livewire) => str_contains($livewire->activeTab, 'linked'))
                    ->multiple()
                    ->preload(),

                SelectFilter::make('assignee')
                    ->relationship('assignee', 'name', function ($query) {
                        $allSupportersQuery = TicketPlugin::get()->getAllSupportersQuery();

                        if ($allSupportersQuery) {
                            $supporterIds = app()->call($allSupportersQuery)->pluck('id');

                            return $query->whereIn('id', $supporterIds);
                        }

                        return $query;
                    })
                    ->hidden(fn (ListTickets $livewire) => str_contains($livewire->activeTab, 'linked'))
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('assign')
                        ->label(__('padmission-tickets::tickets.resources.tickets.assign_to_supporter'))
                        ->icon('heroicon-o-user-plus')
                        ->form([
                            Select::make('assignee_id')
                                ->label(__('padmission-tickets::tickets.resources.tickets.assignee'))
                                ->options(function () {
                                    $allSupportersQuery = TicketPlugin::get()->getAllSupportersQuery();

                                    if ($allSupportersQuery) {
                                        return app()->call($allSupportersQuery)->pluck('name', 'id');
                                    }

                                    return [];
                                })
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $allSupportersQuery = TicketPlugin::get()->getAllSupportersQuery();

                            if ($allSupportersQuery) {
                                $validSupporterIds = app()->call($allSupportersQuery)->pluck('id')->toArray();

                                if (! in_array($data['assignee_id'], $validSupporterIds)) {
                                    Notification::make()
                                        ->title(__('padmission-tickets::tickets.resources.tickets.invalid_assignee'))
                                        ->danger()
                                        ->send();

                                    return;
                                }
                            }

                            $records->each->update([
                                'assignee_id' => $data['assignee_id'],
                            ]);
                        })
                        ->successNotificationTitle(__('padmission-tickets::tickets.resources.tickets.assigned_successfully'))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTickets::route('/'),
            'view' => ViewTicket::route('/{record}/view'),
        ];
    }

    public static function shouldShowSourcePanel(?ListTickets $livewire = null): bool
    {
        if (str_contains($livewire?->activeTab, 'linked')) {
            return false;
        }

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
            } catch (Exception $e) {
                // Panel might not have the plugin registered
                continue;
            }
        }

        return false;
    }
}
