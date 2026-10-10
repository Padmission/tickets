<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Pages;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Lang;
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
        if ($this->activeTabIsInvalid()) {
            $this->activeTab = $this->getDefaultActiveTab();
        }

        // Refresh the page so that showing/hiding filters works properly.
        $this->dispatch('refresh-page');
    }

    protected static string $resource = TicketResource::class;

    protected static function resolveResourcePageName(): string
    {
        // Every list-page replacement owns the index route. Filament's
        // class lookup has no panel context when building a URL in a job.
        return 'index';
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function overdueUrlParameters(?string $tab, array $filters, ?string $search): array
    {
        return [
            'tab' => $tab ?? 'all',
            'filters' => [...$filters, 'overdue' => ['isActive' => true]],
            'search' => $search,
        ];
    }

    public function getDefaultActiveTab(): ?string
    {
        return ConversationViewer::current()->isSupporter ? 'my' : null;
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
     * @param  Builder<Ticket>|null  $query
     * @return Builder<Ticket>
     */
    public function ticketsInTab(string $tab, ?Builder $query = null): Builder
    {
        $tabs = $this->getCachedTabs();

        if ($tabs === []) {
            return TicketResource::allTicketsQuery($query);
        }

        return ($tabs[$tab] ?? $tabs[$this->getDefaultActiveTab()])->modifyQuery($query ?? TicketResource::getEloquentQuery());
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
        if (! ConversationViewer::current()->isSupporter) {
            return [];
        }

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

        // Only a panel that escalates has tickets of its own linked elsewhere, and
        // escalating is the organization's business, never its requesters'.
        if (count(TicketPlugin::get()->getLinkedTicketParentPanels()) === 0) {
            return $tabs;
        }

        $tabs['linked'] = Tab::make()
            ->label(__('padmission-tickets::tickets.resources.tickets.tabs.linked'))
            ->badge(fn (): int => $this->openTicketCount('linked'))
            ->badgeTooltip(__('padmission-tickets::tickets.resources.tickets.badges.tab'))
            ->modifyQueryUsing(fn (Builder $query) => TicketResource::scopeListQueryToSupporterOrSubmitter(
                static::withEscalationAssignees(static::escalationsFromThisPanel($query))
            ));

        return $tabs;
    }
}
