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
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Resources\Concerns\HasResourceConfiguration;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\ReassignTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Filament\Widgets\OpenSupporterTickets;
use Padmission\Tickets\Filament\Widgets\OpenTicketsWidget;
use Padmission\Tickets\Filament\Widgets\TicketCloseTimeWidget;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
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
        $count = static::countOpenTicketsAssignedToCurrentUser();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('padmission-tickets::tickets.resources.tickets.badges.my');
    }

    public static function countOpenTicketsAssignedToCurrentUser(): int
    {
        /** @phpstan-ignore-next-line */
        return TicketPlugin::get()->getTicketQuery()
            ->open()
            ->tap(new CurrentPanelScope)
            ->whereIn('assignee_id', TicketPlugin::get()->getCurrentUserAssigneeIds() ?: [0])
            ->count();
    }

    public static function getModel(): string
    {
        return TicketPlugin::resolveModelClass(Ticket::class);
    }

    /**
     * @return Builder<Ticket>
     */
    public static function getEloquentQuery(): Builder
    {
        return TicketPlugin::get()->getTicketQuery();
    }

    /**
     * Scope a list query so a non-supporter only ever sees tickets they submitted,
     * while a genuine supporter (per the panel's allSupportersQuery) sees everything.
     * Record-level access is enforced separately by the ticket policy's view() method,
     * so this lives on the collection surface only.
     */
    public static function ticketNumberFromSearch(string $search): ?int
    {
        $number = ltrim(trim($search), '#');

        return ctype_digit($number) && strlen($number) <= 18 ? (int) $number : null;
    }

    public static function scopeListQueryToSupporterOrSubmitter(Builder $query): Builder
    {
        $userId = auth()->id();

        if ($userId === null) {
            return $query->whereRaw('1 = 0');
        }

        if (static::currentUserIsSupporter($userId)) {
            return $query;
        }

        return $query->where($query->getModel()->qualifyColumn('submitter_id'), $userId);
    }

    public static function currentUserIsSupporter(int|string|null $userId): bool
    {
        if ($userId === null) {
            return false;
        }

        $supportersQuery = TicketPlugin::get()->getAllSupportersQuery();

        if ($supportersQuery === null) {
            return false;
        }

        return app()->call($supportersQuery)
            ->whereKey($userId)
            ->exists();
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

                TextColumn::make('turn')
                    ->label(__('padmission-tickets::tickets.resources.tickets.turn'))
                    ->badge()
                    ->color(fn (?Turn $state): string => $state === Turn::Supporter ? 'warning' : 'gray')
                    ->tooltip(fn (?Turn $state): ?string => $state?->getDescription())
                    ->sortable(),

                TextColumn::make('subject')
                    ->label(__('padmission-tickets::tickets.resources.tickets.subject'))
                    ->html()
                    // Numbers are not shown in the list, but they are in every email subject,
                    // so "1842" or "#1842" still finds the ticket.
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereLike($query->qualifyColumn('subject'), "%{$search}%")
                        ->when(static::ticketNumberFromSearch($search), fn (Builder $query, int $id): Builder => $query->orWhere($query->getModel()->getQualifiedKeyName(), $id))),

                ...TicketPlugin::get()->getAdditionalTableColumns(),

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
                // Statuses belong to one panel and tenant, so a status default would
                // hide tickets from every other tenant a cross-tenant panel serves.
                Filter::make('open')
                    ->label(__('padmission-tickets::tickets.resources.tickets.filters.open_only'))
                    ->toggle()
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->whereNull($query->getModel()->qualifyColumn('closed_at')))
                    ->hidden(fn (ListTickets $livewire) => str_contains($livewire->activeTab, 'linked')),

                SelectFilter::make('status')
                    ->relationship('status', 'display_name')
                    ->hidden(fn (ListTickets $livewire) => str_contains($livewire->activeTab, 'linked'))
                    ->multiple()
                    ->preload(),

                SelectFilter::make('priority')
                    ->relationship('priority', 'display_name')
                    ->hidden(fn (ListTickets $livewire) => str_contains($livewire->activeTab, 'linked'))
                    ->multiple()
                    ->preload(),

                SelectFilter::make('assignee')
                    ->label(__('padmission-tickets::tickets.resources.tickets.assignee'))
                    ->relationship('assignee', 'name', function ($query) {
                        $allSupportersQuery = TicketPlugin::get()->getAllSupportersQuery();

                        if ($allSupportersQuery) {
                            $supporterIds = app()->call($allSupportersQuery)->pluck('id');

                            return $query->whereIn('id', $supporterIds);
                        }

                        return $query;
                    })
                    ->hidden(fn (ListTickets $livewire) => str_contains($livewire->activeTab, 'linked'))
                    ->searchable()
                    ->preload(),

                SelectFilter::make('submitter')
                    ->label(__('padmission-tickets::tickets.resources.tickets.submitter'))
                    ->relationship('submitter', 'name')
                    ->searchable()
                    ->multiple()
                    ->preload(),
            ])
            ->emptyStateHeading(fn (ListTickets $livewire): ?string => static::tabText($livewire, 'heading'))
            ->emptyStateDescription(fn (ListTickets $livewire): ?string => static::tabText($livewire, 'description'))
            ->recordActions([
                ViewAction::make(),
                ReassignTicketAction::make()
                    ->link()
                    ->authorize(fn (Ticket $record): bool => static::canEdit($record)),
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
                        // A closure, so the manage ability is evaluated as the
                        // action runs, where a host can observe it. Nothing is
                        // selected while the toolbar renders.
                        ->authorize(fn (Collection $records): bool => $records->isEmpty()
                            || $records->contains(fn (Model $record): bool => Gate::allows('manage', $record)))
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

                            $authorized = $records->filter(fn ($record) => Gate::allows('manage', $record));

                            if ($authorized->count() < $records->count()) {
                                Notification::make()
                                    ->title(__('padmission-tickets::tickets.resources.tickets.unauthorized_assignment'))
                                    ->danger()
                                    ->send();
                            }

                            $authorized->each->update([
                                'assignee_id' => $data['assignee_id'],
                            ]);
                        })
                        ->successNotificationTitle(__('padmission-tickets::tickets.resources.tickets.assigned_successfully'))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }

    protected static function tabText(ListTickets $livewire, string $part): ?string
    {
        $key = "padmission-tickets::tickets.resources.tickets.empty.{$livewire->activeTab}.{$part}";

        return Lang::has($key) ? __($key) : null;
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
