<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Pages;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Lang;
use Padmission\Tickets\Enums\Turn;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\OpenTicketForContactAction;
use Padmission\Tickets\Filament\Resources\Tickets\Actions\StartTicketAction;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\Concerns\ExplainsStaleEscalationActions;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Filament\Widgets\OpenSupporterTickets;
use Padmission\Tickets\Filament\Widgets\OpenTicketsWidget;
use Padmission\Tickets\Filament\Widgets\OverdueTicketsWidget;
use Padmission\Tickets\Filament\Widgets\TicketCloseTimeWidget;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Services\TicketAssignee;
use Padmission\Tickets\Support\ConversationStateQuery;
use Padmission\Tickets\Support\ConversationViewer;
use Padmission\Tickets\TicketPlugin;

class ListTickets extends ListRecords
{
    use ExplainsStaleEscalationActions;
    use ExposesTableToWidgets;

    public function updatedActiveTab(): void
    {
        // Refresh the page so that showing/hiding filters works properly.
        $this->dispatch('refresh-page');
    }

    protected static string $resource = TicketResource::class;

    public function getDefaultActiveTab(): string
    {
        return ConversationViewer::current()->isSupporter ? 'my' : 'all';
    }

    protected function loadDefaultActiveTab(): void
    {
        // Filament uses ?tab=; also accept the explicit ?activeTab= form.
        $tab = $this->activeTab ?? request()->query('activeTab');
        $this->activeTab = is_string($tab) ? $tab : null;

        if ($this->activeTabIsInvalid()) {
            $this->activeTab = $this->getDefaultActiveTab();
        }
    }

    /*
     * A row or bulk action can change who a ticket waits on, or remove it,
     * which moves it between the cards above the list and can change the
     * sidebar badge. Both are separate components, so the table redrawing
     * leaves them as they were.
     */
    protected function afterActionCalled(Action $action): void
    {
        parent::afterActionCalled($action);

        $this->dispatch('refresh-ticket-stats');
        $this->dispatch('refresh-sidebar');
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 12;
    }

    protected function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery();

        if ($this->activeTabIsInvalid()) {
            $query->tap(new CurrentPanelScope);
        }

        $viewer = ConversationViewer::current();

        return $this->withRowRelations(ConversationStateQuery::apply(TicketResource::scopeListQueryToSupporterOrSubmitter($query), $viewer), $viewer);
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    protected function withRowRelations(Builder $query, ConversationViewer $viewer): Builder
    {
        $tenant = config('padmission-tickets.tenancy.enabled') ? ',tenant_id' : '';

        if (str_contains((string) $this->activeTab, 'linked') || $viewer->receivesEscalations) {
            return $query->with([
                "childTickets:id,linked_ticket_id,submitter_id,submitter_data,closed_at,panel{$tenant}",
                'childTickets.submitter',
                'submitter',
            ]);
        }

        if (! $viewer->isSupporter) {
            return $query;
        }

        return $query->with([
            "parentTicket:id,panel,turn,closed_at,closed_by,submitter_id,assignee_id,deleted_at{$tenant}",
            'parentTicket.submitter',
        ]);
    }

    protected function activeTabIsInvalid(): bool
    {
        return blank($this->activeTab)
            || ! array_key_exists($this->activeTab, $this->getCachedTabs());
    }

    protected function getHeaderWidgets(): array
    {
        // The counts cover every ticket in the panel, while someone who only
        // submits tickets is listed just their own.
        if (! ConversationViewer::current()->isSupporter) {
            return [];
        }

        return [
            OpenTicketsWidget::class,
            OpenSupporterTickets::class,
            TicketCloseTimeWidget::class,
            OverdueTicketsWidget::class,
        ];
    }

    /**
     * @var array{linked: int, my_linked: int, overdue_linked: int}|null
     */
    protected ?array $openEscalatedCounts = null;

    /**
     * The escalated tabs are counted in one query from the linked tab's own
     * query, so the badges match the lists. The result lives on this request's
     * component instance, so it is never stale on the next one.
     *
     * @return array{linked: int, my_linked: int, overdue_linked: int}
     */
    protected function openEscalatedCounts(): array
    {
        if ($this->openEscalatedCounts !== null) {
            return $this->openEscalatedCounts;
        }

        $query = $this->getCachedTabs()['linked']
            ->modifyQuery(TicketResource::getEloquentQuery())
            ->open();

        $overdue = (clone $query)->overdue()->select($query->qualifyColumn('id'));
        $counts = $query
            ->leftJoinSub($overdue, 'overdue_escalations', 'overdue_escalations.id', '=', $query->qualifyColumn('id'))
            ->toBase()
            ->selectRaw('count(*) as linked')
            ->selectRaw('count(overdue_escalations.id) as overdue_linked')
            ->selectRaw('coalesce(sum(case when '.$query->qualifyColumn('submitter_id').' = ? then 1 else 0 end), 0) as my_linked', [Filament::auth()->id()])
            ->first();

        return $this->openEscalatedCounts = [
            'linked' => (int) ($counts->linked ?? 0),
            'my_linked' => (int) ($counts->my_linked ?? 0),
            'overdue_linked' => (int) ($counts->overdue_linked ?? 0),
        ];
    }

    /** @var array<string, int>|null */
    protected ?array $presetCounts = null;

    /**
     * The four local presets share one aggregate and an indexed overdue lookup.
     * No tickets or activity collections are hydrated to draw the badges.
     *
     * @return array<string, int>
     */
    protected function presetCounts(): array
    {
        if ($this->presetCounts !== null) {
            return $this->presetCounts;
        }

        $query = TicketResource::allTicketsQuery()->open();
        $overdue = (clone $query)->overdue()->select($query->qualifyColumn('id'));
        $turn = $query->qualifyColumn('turn');
        $assignee = $query->qualifyColumn('assignee_id');
        $counts = $query
            ->leftJoinSub($overdue, 'overdue_tickets', 'overdue_tickets.id', '=', $query->qualifyColumn('id'))
            ->toBase()
            ->selectRaw("coalesce(sum(case when {$turn} = ? then 1 else 0 end), 0) as needs_reply", [Turn::Supporter->value])
            ->selectRaw('count(overdue_tickets.id) as overdue')
            ->selectRaw("coalesce(sum(case when {$assignee} is null then 1 else 0 end), 0) as unassigned")
            ->selectRaw("coalesce(sum(case when {$turn} = ? then 1 else 0 end), 0) as waiting_on_requester", [Turn::User->value])
            ->first();

        return $this->presetCounts = [
            'needs_reply' => (int) ($counts->needs_reply ?? 0),
            'overdue' => (int) ($counts->overdue ?? 0),
            'unassigned' => (int) ($counts->unassigned ?? 0),
            'waiting_on_requester' => (int) ($counts->waiting_on_requester ?? 0),
        ];
    }

    /**
     * @param  Builder<Ticket>|null  $query
     * @return Builder<Ticket>
     */
    public function ticketsInTab(string $tab, ?Builder $query = null): Builder
    {
        $tabs = $this->getCachedTabs();

        return ($tabs[$tab] ?? $tabs['all'])->modifyQuery($query ?? TicketResource::getEloquentQuery());
    }

    public function openTicketCount(string $tab): int
    {
        return $this->ticketsInTab($tab)->open()->count();
    }

    /**
     * An escalation stays listed after all its originals were removed, through
     * the history note written when the first was added, so its owner does not
     * lose it.
     *
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    protected static function escalationsFromThisPanel(Builder $query): Builder
    {
        return $query->escalationsFrom(Filament::getCurrentOrDefaultPanel()->getId());
    }

    /**
     * @param  Builder<Ticket>  $query
     * @return Builder<Ticket>
     */
    protected static function withEscalationAssignees(Builder $query): Builder
    {
        return TicketAssignee::eagerLoadForForeignPanels($query, array_keys(TicketPlugin::get()->getLinkedTicketParentPanels()));
    }

    public function getSubheading(): ?string
    {
        $tab = $this->activeTabIsInvalid() ? 'all' : $this->activeTab;

        $viewer = ConversationViewer::current();

        if ($tab === 'all' && ! $viewer->isSupporter) {
            $tab = 'all_submitter';
        } elseif (in_array($tab, ['all', 'my'], true) && $viewer->receivesEscalations) {
            $tab = "{$tab}_received";
        }

        $key = "padmission-tickets::tickets.resources.tickets.tab_descriptions.{$tab}";

        if (! Lang::has($key)) {
            return null;
        }

        $team = TicketPlugin::get()->getEscalationTargetName();

        return Lang::has("{$key}_to") ? TicketPlugin::teamText($key, $team) : __($key);
    }

    protected function getHeaderActions(): array
    {
        return [
            StartTicketAction::make(),
            OpenTicketForContactAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make()
                ->label(__('padmission-tickets::tickets.resources.tickets.tabs.all'))
                ->badge(fn (): int => $this->openTicketCount('all'))
                ->badgeTooltip(__('padmission-tickets::tickets.resources.tickets.badges.tab'))
                ->modifyQueryUsing(fn (Builder $query) => TicketResource::allTicketsQuery($query)),

            'my' => Tab::make()
                ->label(__('padmission-tickets::tickets.resources.tickets.tabs.my'))
                ->badge(fn (): int => $this->openTicketCount('my'))
                ->badgeTooltip(__('padmission-tickets::tickets.resources.tickets.badges.tab'))
                ->modifyQueryUsing(fn (Builder $query) => TicketResource::scopeListQueryToSupporterOrSubmitter(
                    $query
                        ->tap(new CurrentPanelScope)
                        ->whereIn('assignee_id', TicketPlugin::get()->getCurrentUserAssigneeIds() ?: [0])
                )),
        ];

        $viewer = ConversationViewer::current();

        if ($viewer->isSupporter) {
            $presets = [
                'needs_reply' => fn (Builder $query): Builder => TicketResource::allTicketsQuery($query)->open()->where($query->qualifyColumn('turn'), Turn::Supporter),
                'overdue' => fn (Builder $query): Builder => TicketResource::allTicketsQuery($query)->overdue(),
                'unassigned' => fn (Builder $query): Builder => TicketResource::allTicketsQuery($query)->open()->whereNull($query->qualifyColumn('assignee_id')),
                'waiting_on_requester' => fn (Builder $query): Builder => TicketResource::allTicketsQuery($query)->open()->where($query->qualifyColumn('turn'), Turn::User),
            ];

            foreach ($presets as $name => $scope) {
                $tabs[$name] = Tab::make()
                    ->label(__("padmission-tickets::tickets.resources.tickets.tabs.{$name}"))
                    ->badge(fn (): int => $this->presetCounts()[$name])
                    ->badgeTooltip(__('padmission-tickets::tickets.resources.tickets.badges.tab'))
                    ->modifyQueryUsing($scope);
            }

            if ($viewer->receivesEscalations) {
                $tabs['open_escalations'] = Tab::make()
                    ->label(__('padmission-tickets::tickets.resources.tickets.tabs.open_escalations'))
                    ->badge(fn (): int => $this->openTicketCount('open_escalations'))
                    ->badgeTooltip(__('padmission-tickets::tickets.resources.tickets.badges.tab'))
                    ->modifyQueryUsing(fn (Builder $query): Builder => TicketResource::allTicketsQuery($query)->escalations()->open());
            }
        }

        // Only a panel that escalates has tickets of its own linked elsewhere, and
        // escalating is the organization's business, never its requesters'.
        if (count(TicketPlugin::get()->getLinkedTicketParentPanels()) === 0 || ! ConversationViewer::current()->isSupporter) {
            return $tabs;
        }

        $tabs['linked'] = Tab::make()
            ->label(__('padmission-tickets::tickets.resources.tickets.tabs.linked'))
            ->badge(fn (): int => $this->openEscalatedCounts()['linked'])
            ->badgeTooltip(__('padmission-tickets::tickets.resources.tickets.badges.tab'))
            ->modifyQueryUsing(fn (Builder $query) => TicketResource::scopeListQueryToSupporterOrSubmitter(
                static::withEscalationAssignees(static::escalationsFromThisPanel($query))
            ));

        $tabs['my_linked'] = Tab::make()
            ->label(__('padmission-tickets::tickets.resources.tickets.tabs.my_linked'))
            ->badge(fn (): int => $this->openEscalatedCounts()['my_linked'])
            ->badgeTooltip(__('padmission-tickets::tickets.resources.tickets.badges.tab'))
            ->modifyQueryUsing(fn (Builder $query) => TicketResource::scopeListQueryToSupporterOrSubmitter(
                static::withEscalationAssignees(static::escalationsFromThisPanel($query)
                    ->where($query->qualifyColumn('submitter_id'), Filament::auth()->id()))
            ));

        $tabs['overdue_linked'] = Tab::make()
            ->label(__('padmission-tickets::tickets.resources.tickets.tabs.overdue_linked'))
            ->badge(fn (): int => $this->openEscalatedCounts()['overdue_linked'])
            ->badgeTooltip(__('padmission-tickets::tickets.resources.tickets.badges.tab'))
            ->modifyQueryUsing(fn (Builder $query): Builder => $this->ticketsInTab('linked', $query)->overdue());

        return $tabs;
    }
}
