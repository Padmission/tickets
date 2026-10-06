<?php

namespace Padmission\Tickets\Filament\Resources\Tickets;

use Carbon\CarbonImmutable;
use Exception;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\HtmlString;
use Padmission\Tickets\Filament\Resources\Concerns\HasResourceConfiguration;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\DeleteTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\HandOverEscalationAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\ReassignTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\ReopenTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ViewTicket;
use Padmission\Tickets\Filament\Widgets\OpenSupporterTickets;
use Padmission\Tickets\Filament\Widgets\OpenTicketsWidget;
use Padmission\Tickets\Filament\Widgets\TicketCloseTimeWidget;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Services\EscalationSummary;
use Padmission\Tickets\Services\TicketCloser;
use Padmission\Tickets\Services\TicketReassignment;
use Padmission\Tickets\Support\ConversationState;
use Padmission\Tickets\Support\ConversationStateQuery;
use Padmission\Tickets\Support\ConversationViewer;
use Padmission\Tickets\TicketPlugin;

use function app;

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
        $count = match (true) {
            ! static::badgeCountsNeedsYou() => static::countOpenTicketsAssignedToCurrentUser(),
            ConversationViewer::current()->isSupporter => static::countNeedsYou(),
            default => 0,
        };

        return $count > 0 ? (string) $count : null;
    }

    /**
     * @return string|array<int|string, string|int>|null
     */
    public static function getNavigationBadgeColor(): string|array|null
    {
        return static::badgeCountsNeedsYou() ? 'warning' : parent::getNavigationBadgeColor();
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        if (! static::badgeCountsNeedsYou()) {
            return __('padmission-tickets::tickets.resources.tickets.badges.my');
        }

        return static::describesReceivedTickets()
            ? __('padmission-tickets::tickets.resources.tickets.badges.needs_you_received')
            : TicketPlugin::teamText('padmission-tickets::tickets.resources.tickets.badges.needs_you', TicketPlugin::get()->getEscalationTargetName());
    }

    protected static function badgeCountsNeedsYou(): bool
    {
        return TicketPlugin::get()->shouldNavigationBadgeCountNeedsYou();
    }

    /*
     * A panel that receives escalations, or has nowhere to escalate to, has no
     * other team's reply to pass on.
     */
    public static function describesReceivedTickets(): bool
    {
        $viewer = ConversationViewer::current();

        return $viewer->receivesEscalations || $viewer->parentPanelIds === [];
    }

    /**
     * The All tab's tickets.
     *
     * @param  Builder<Ticket>|null  $query
     * @return Builder<Ticket>
     */
    public static function allTicketsQuery(?Builder $query = null): Builder
    {
        return static::scopeListQueryToSupporterOrSubmitter(($query ?? static::getEloquentQuery())->tap(new CurrentPanelScope));
    }

    /*
     * What the Needs You card counts and the list ranks first, the orange
     * rows: shared by the card and the sidebar badge so they never disagree.
     *
     * @param  Builder<Ticket>|null  $tickets
     */
    public static function countNeedsYou(?Builder $tickets = null): int
    {
        [$rank, $bindings] = ConversationStateQuery::rankExpression(ConversationViewer::current());

        /** @phpstan-ignore method.notFound */
        return ($tickets ?? static::allTicketsQuery())->open()->whereRaw("{$rank} = 0", $bindings)->count();
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

    /*
     * The Assigned to cell shows an escalation's assignee from the panel it
     * was sent to, such as Padmission staff from another tenant, whom the
     * list's own scopes hide. Searching and sorting read the same name, and
     * only for the tickets the list already shows, so they match the cell.
     *
     * @return Builder<Model>
     */
    protected static function assigneeNames(): Builder
    {
        return TicketPlugin::resolveUserModelClass()::query()->withoutGlobalScopes();
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
    /*
     * An escalated ticket is assigned inside the team it went to. Its person is
     * loaded through that team's panel (TicketAssignee); the team is named only
     * when even that lookup finds nobody.
     */
    /*
     * The panel escalations are sent to names each ticket's organization here
     * rather than in a column of its own, so the list fits the screen.
     */
    protected static function subjectDescription(Ticket $record): ?string
    {
        $origin = count(TicketPlugin::get()->getLinkedTicketChildPanels()) > 0
            ? TicketPlugin::get()->describeTicketOrigin($record)
            : null;

        $about = ConversationState::fromRow($record)->isEscalation ? EscalationSummary::about($record) : null;

        return collect([$origin, $about])->filter()->implode(' · ') ?: null;
    }

    public static function assigneeLabel(Ticket $record): ?string
    {
        if ($record->assignee !== null) {
            return static::isAssignedToCurrentUser($record)
                ? __('padmission-tickets::tickets.side_you')
                : $record->assignee->getAttribute('name');
        }

        if (blank($record->assignee_id) || $record->isInCurrentPanel()) {
            return null;
        }

        return TicketPlugin::find($record->panel)?->getSupportTeamName()
            ?? __('padmission-tickets::tickets.resources.tickets.assigned_elsewhere');
    }

    public static function isAssignedToCurrentUser(Ticket $record): bool
    {
        return filled($record->assignee_id)
            && in_array((int) $record->assignee_id, static::currentUserAssigneeIds(Filament::getCurrentOrDefaultPanel()->getId(), Filament::auth()->id()), true);
    }

    /**
     * Keyed by panel and user so a table asks the host once, not once per row.
     *
     * @return array<int, int>
     */
    protected static function currentUserAssigneeIds(string $panelId, int|string|null $userId): array
    {
        return once(fn (): array => $userId === null ? [] : TicketPlugin::get($panelId)->getCurrentUserAssigneeIds());
    }

    public static function ticketNumberFromSearch(string $search): ?int
    {
        $number = ltrim(trim($search), '#');

        return ctype_digit($number) && strlen($number) <= 18 ? (int) $number : null;
    }

    public static function scopeListQueryToSupporterOrSubmitter(Builder $query): Builder
    {
        $viewer = ConversationViewer::current();

        if ($viewer->userId === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($viewer->isSupporter) {
            return $query;
        }

        return $query->where($query->getModel()->qualifyColumn('submitter_id'), $viewer->userId);
    }

    public static function currentUserIsSupporter(int|string|null $userId): bool
    {
        $viewer = ConversationViewer::current();

        return $userId !== null && $viewer->userId == $userId && $viewer->isSupporter;
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
                return static::orderByRank($query, 'asc')
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
                // Off by default: the number is a reference to quote, and the subject
                // search already finds a ticket by it.
                TextColumn::make('id')
                    ->label(__('padmission-tickets::tickets.resources.tickets.ticket_number'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // With a single team to escalate to, every row on the escalated tabs
                // would repeat the same name, so the column only appears when there
                // is a choice.
                TextColumn::make('panel')
                    ->label(__('padmission-tickets::tickets.resources.tickets.panel'))
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === null
                        ? '-'
                        : (TicketPlugin::find($state)?->getSupportTeamName() ?? ucfirst($state)))
                    ->visible(fn (ListTickets $livewire): bool => str_contains($livewire->activeTab, 'linked')
                        && count(TicketPlugin::get()->getLinkedTicketParentPanels()) > 1)
                    ->sortable(),

                TextColumn::make('status.display_name')
                    ->label(__('padmission-tickets::tickets.resources.statuses.model_label'))
                    ->badge()
                    ->color(fn ($record) => $record->status->colorPalette)
                    ->sortable(),

                // Off by default so the list fits a laptop screen; the Waiting on column
                // already puts what needs attention first.
                TextColumn::make('priority.display_name')
                    ->label(__('padmission-tickets::tickets.resources.priorities.model_label'))
                    ->badge()
                    ->color(fn ($record) => $record->priority->colorPalette)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('turn')
                    ->label(__('padmission-tickets::tickets.resources.tickets.turn'))
                    ->state(fn (Ticket $record): ?string => ConversationState::fromRow($record)->label())
                    ->placeholder('–')
                    ->badge()
                    ->color(fn (Ticket $record): string => ConversationState::fromRow($record)->color())
                    ->icon(fn (Ticket $record): ?string => ConversationState::fromRow($record)->icon())
                    ->tooltip(fn (Ticket $record): ?string => ConversationState::fromRow($record)->tooltip())
                    ->sortable(query: fn (Builder $query, string $direction): Builder => static::orderByRank($query, $direction)),

                TextColumn::make('subject')
                    ->label(__('padmission-tickets::tickets.resources.tickets.subject'))
                    ->suffix(fn (Ticket $record): ?HtmlString => filled($record->duplicate_of_ticket_id)
                        ? new HtmlString(static::escalationMarker($record).static::badge(__('padmission-tickets::tickets.duplicates.badge'), 'gray', null))
                        : static::escalationMarker($record))
                    ->wrap()
                    ->extraHeaderAttributes(['style' => 'min-width: 9rem'])
                    ->description(fn (Ticket $record): ?string => static::subjectDescription($record))
                    // Numbers are not shown in the list, but they are in every email subject,
                    // so "1842" or "#1842" still finds the ticket.
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->whereLike($query->qualifyColumn('subject'), "%{$search}%")
                        ->when(static::ticketNumberFromSearch($search), fn (Builder $query, int $id): Builder => $query->orWhere($query->getModel()->getQualifiedKeyName(), $id))),

                ...TicketPlugin::get()->getAdditionalTableColumns(),

                TextColumn::make('submitter.name')
                    ->label(fn (ListTickets $livewire): string => static::submitterLabel($livewire))
                    ->state(fn (Ticket $record): ?string => filled($record->submitter_id) && $record->submitter_id == Filament::auth()->id()
                        ? __('padmission-tickets::tickets.side_you')
                        : $record->submitter?->getAttribute('name'))
                    ->wrap()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('assignee.name')
                    ->label(__('padmission-tickets::tickets.resources.tickets.assignee'))
                    ->state(fn (Ticket $record): ?string => static::assigneeLabel($record))
                    ->wrap()
                    ->tooltip(fn (ListTickets $livewire): ?string => static::isEscalatedTab($livewire)
                        ? TicketPlugin::teamText('padmission-tickets::tickets.resources.tickets.hints.assignee_elsewhere', TicketPlugin::get()->getEscalationTargetName())
                        : null)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereIn(
                        $query->qualifyColumn('assignee_id'),
                        static::assigneeNames()->where('name', 'like', "%{$search}%")->select(static::assigneeNames()->getModel()->getQualifiedKeyName()),
                    ))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                        static::assigneeNames()
                            ->select('name')
                            ->whereColumn(static::assigneeNames()->getModel()->getQualifiedKeyName(), $query->qualifyColumn('assignee_id')),
                        $direction,
                    )),

                TextColumn::make('source_panel')
                    ->label(__('padmission-tickets::tickets.resources.tickets.source_panel'))
                    ->formatStateUsing(fn ($state) => $state ? ucfirst($state) : '-')
                    ->sortable()
                    ->visible(fn (ListTickets $livewire) => static::shouldShowSourcePanel($livewire)),

                TextColumn::make('latestMessage.created_at')
                    ->label(__('padmission-tickets::tickets.resources.tickets.last_message'))
                    ->formatStateUsing(fn (?CarbonImmutable $state) => $state?->diffForHumans())
                    ->suffix(fn (Ticket $record): ?HtmlString => ConversationState::fromRow($record)->isNew ? static::badge(
                        __('padmission-tickets::tickets.resources.tickets.new_message'),
                        'primary',
                        __('padmission-tickets::tickets.resources.tickets.new_message_help'),
                    ) : null)
                    ->tooltip(fn (?CarbonImmutable $state) => TicketPlugin::formatMessageTime($state))
                    ->sortable(),
            ])
            ->filters([
                // Statuses belong to one panel and tenant, so a status default would
                // hide tickets from every other tenant a cross-tenant panel serves.
                Filter::make('open')
                    ->label(__('padmission-tickets::tickets.resources.tickets.filters.open_only'))
                    ->toggle()
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->whereNull($query->getModel()->qualifyColumn('closed_at'))),

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
                    ->label(fn (ListTickets $livewire): string => static::submitterLabel($livewire))
                    ->relationship('submitter', 'name')
                    ->searchable()
                    ->multiple()
                    ->preload(),
            ])
            ->emptyStateHeading(fn (ListTickets $livewire): ?string => static::tabText($livewire, 'heading'))
            ->emptyStateDescription(fn (ListTickets $livewire): ?string => static::tabText($livewire, 'description'))
            ->recordActions([
                ViewAction::make(),
                HandOverEscalationAction::make()
                    ->link(),
                ActionGroup::make([
                    ReassignTicketAction::make()
                        ->authorize(fn (Ticket $record): bool => static::canEdit($record)),
                    ReopenTicketAction::make(),
                    DeleteTicketAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('assign')
                        ->label(__('padmission-tickets::tickets.resources.tickets.assign_to_supporter'))
                        ->icon('heroicon-o-user-plus')
                        ->slideOver(false)
                        ->modalWidth(Width::Medium)
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
                        // Each ticket asks who may be assigned it, as Reassign does, since a host scopes that pool by the ticket's tenant.
                        ->action(function (Collection $records, array $data, BulkAction $action): void {
                            $authorized = $records->filter(fn ($record) => Gate::allows('manage', $record));

                            if ($authorized->count() < $records->count()) {
                                Notification::make()
                                    ->title(__('padmission-tickets::tickets.resources.tickets.unauthorized_assignment'))
                                    ->danger()
                                    ->send();
                            }

                            $reassignment = resolve(TicketReassignment::class);
                            $eligible = $authorized->filter(fn (Model $record): bool => $record instanceof Ticket && $reassignment->isEligible($record, $data['assignee_id']));

                            if ($eligible->isEmpty()) {
                                Notification::make()
                                    ->title(__('padmission-tickets::tickets.resources.tickets.invalid_assignee'))
                                    ->danger()
                                    ->send();

                                $action->halt();
                            }

                            if ($eligible->count() < $authorized->count()) {
                                Notification::make()
                                    ->title(__('padmission-tickets::tickets.resources.tickets.ineligible_assignment'))
                                    ->warning()
                                    ->send();
                            }

                            $eligible->each->update([
                                'assignee_id' => $data['assignee_id'],
                            ]);
                        })
                        ->successNotificationTitle(__('padmission-tickets::tickets.resources.tickets.assigned_successfully'))
                        ->deselectRecordsAfterCompletion(),
                    static::bulkCloseAction(),
                    DeleteBulkAction::make()
                        ->authorizeIndividualRecords('delete'),
                ])
                    ->hidden(fn (ListTickets $livewire): bool => static::isEscalatedTab($livewire)),
            ]);
    }

    /*
     * Each selected ticket is closed exactly as its own Close dialog would,
     * skipping those already closed or that the viewer may not close. The
     * disposition is chosen by name, since an escalation panel's selection
     * spans organizations that each keep their own.
     */
    protected static function bulkCloseAction(): BulkAction
    {
        $key = 'padmission-tickets::tickets.actions.bulk_close.';
        $closer = fn (): TicketCloser => resolve(TicketCloser::class);
        $dispositionNames = fn (Collection $records): array => $records
            ->whereInstanceOf(Ticket::class)
            ->filter(fn (Ticket $ticket): bool => $closer()->canClose($ticket))
            ->flatMap(fn (Ticket $ticket): array => $closer()->dispositionsFor($ticket)->pluck('display_name')->all())
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $name): array => [$name => $name])
            ->all();

        return BulkAction::make('close-tickets')
            ->label(__($key.'label'))
            ->icon('heroicon-o-check-circle')
            ->requiresConfirmation()
            ->slideOver(false)
            ->modalHeading(__($key.'modal_heading'))
            ->modalDescription(fn (): string => __(count(TicketPlugin::get()->getLinkedTicketChildPanels()) > 0 ? $key.'modal_description_received' : $key.'modal_description'))
            ->modalSubmitActionLabel(__($key.'submit'))
            ->schema(fn (Collection $records): array => $dispositionNames($records) === [] ? [] : [
                Select::make('disposition')
                    ->label(__('padmission-tickets::tickets.actions.close.disposition.label'))
                    ->helperText(__($key.'disposition_help'))
                    ->options($dispositionNames($records))
                    ->required(),
            ])
            // As bulk Assign: evaluated as the action runs, where a host can observe it.
            ->authorize(fn (Collection $records): bool => $records->isEmpty()
                || $records->whereInstanceOf(Ticket::class)->contains(fn (Ticket $ticket): bool => $closer()->canClose($ticket)))
            ->action(function (Collection $records, array $data) use ($key, $closer): void {
                $closed = 0;

                foreach ($records->whereInstanceOf(Ticket::class) as $ticket) {
                    if (! $closer()->canClose($ticket)) {
                        continue;
                    }

                    $dispositions = $closer()->dispositionsFor($ticket);
                    $dispositionId = filled($data['disposition'] ?? null)
                        ? $dispositions->clone()->where('display_name', $data['disposition'])->value('id')
                        : null;

                    if ($dispositionId === null && $dispositions->exists()) {
                        continue;
                    }

                    $closer()->close($ticket, $dispositionId);
                    $closed++;
                }

                $skipped = $records->count() - $closed;

                Notification::make()
                    ->title(trim(trans_choice($key.'closed', $closed).' '.($skipped > 0 ? trans_choice($key.'skipped', $skipped) : '')))
                    ->status($skipped > 0 ? 'warning' : 'success')
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    public static function orderByRank(Builder $query, string $direction): Builder
    {
        return ConversationStateQuery::orderByRank($query, ConversationViewer::current(), $direction);
    }

    public static function isEscalatedTab(ListTickets $livewire): bool
    {
        return str_contains((string) $livewire->activeTab, 'linked');
    }

    public static function submitterLabel(ListTickets $livewire): string
    {
        return match (true) {
            static::isEscalatedTab($livewire) => __('padmission-tickets::tickets.resources.tickets.handled_by'),
            ConversationViewer::current()->receivesEscalations => __('padmission-tickets::tickets.resources.tickets.contact'),
            default => __('padmission-tickets::tickets.resources.tickets.submitter'),
        };
    }

    protected static function escalationMarker(Ticket $record): ?HtmlString
    {
        $state = ConversationState::fromRow($record);
        $label = $state->markerLabel();

        return $label === null ? null : static::badge($label, $state->markerColor(), $state->markerTooltip());
    }

    protected static function badge(string $label, string $color, ?string $tooltip): HtmlString
    {
        return new HtmlString(' '.Blade::render(
            '<x-filament::badge size="sm" :color="$color" :tooltip="$tooltip" class="pad-ti-marker">{{ $label }}</x-filament::badge>',
            ['label' => $label, 'color' => $color, 'tooltip' => $tooltip],
        ));
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
