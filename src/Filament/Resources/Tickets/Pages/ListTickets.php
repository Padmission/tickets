<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Pages;

use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Lang;
use Padmission\Tickets\Enums\ActivityType;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Filament\Widgets\OpenSupporterTickets;
use Padmission\Tickets\Filament\Widgets\OpenTicketsWidget;
use Padmission\Tickets\Filament\Widgets\TicketCloseTimeWidget;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;
use Padmission\Tickets\Models\Ticket;
use Padmission\Tickets\Models\TicketActivity;
use Padmission\Tickets\Services\TicketAssignee;
use Padmission\Tickets\Support\ConversationStateQuery;
use Padmission\Tickets\Support\ConversationViewer;
use Padmission\Tickets\TicketPlugin;

class ListTickets extends ListRecords
{
    public function updatedActiveTab(): void
    {
        // Refresh the page so that showing/hiding filters works properly.
        $this->dispatch('refresh-page');
    }

    protected static string $resource = TicketResource::class;

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
        ];
    }

    /**
     * @var array{linked: int, my_linked: int}|null
     */
    protected ?array $openEscalatedCounts = null;

    /**
     * Both escalated tabs are counted in one query from the linked tab's own
     * query, so the badges match the lists. The result lives on this request's
     * component instance, so it is never stale on the next one.
     *
     * @return array{linked: int, my_linked: int}
     */
    protected function openEscalatedCounts(): array
    {
        if ($this->openEscalatedCounts !== null) {
            return $this->openEscalatedCounts;
        }

        $query = $this->getCachedTabs()['linked']
            ->modifyQuery(TicketResource::getEloquentQuery())
            ->open();

        $counts = $query
            ->toBase()
            ->selectRaw('count(*) as linked')
            ->selectRaw('coalesce(sum(case when '.$query->qualifyColumn('submitter_id').' = ? then 1 else 0 end), 0) as my_linked', [Filament::auth()->id()])
            ->first();

        return $this->openEscalatedCounts = [
            'linked' => (int) ($counts->linked ?? 0),
            'my_linked' => (int) ($counts->my_linked ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getWidgetData(): array
    {
        return ['activeTab' => $this->activeTabIsInvalid() ? 'all' : $this->activeTab];
    }

    /**
     * @return Builder<Ticket>
     */
    public function ticketsInTab(string $tab): Builder
    {
        $tabs = $this->getCachedTabs();

        return ($tabs[$tab] ?? $tabs['all'])->modifyQuery(TicketResource::getEloquentQuery());
    }

    public function openTicketCount(string $tab): int
    {
        return $this->ticketsInTab($tab)->open()->count();
    }

    /*
     * An escalation stays listed after all its originals were removed, through
     * the history note written when the first was added, so its owner does not
     * lose it.
     */
    protected static function escalationsFromThisPanel(Builder $query): Builder
    {
        $panelId = Filament::getCurrentOrDefaultPanel()->getId();
        $activities = (new (TicketPlugin::resolveModelClass(TicketActivity::class)))->getTable();

        return $query->where(fn (Builder $query): Builder => $query
            ->whereHas('childTickets', static::originalsFromThisPanel(...))
            ->orWhere(fn (Builder $query): Builder => $query
                ->where($query->qualifyColumn('source_panel'), $panelId)
                ->whereIn($query->qualifyColumn('panel'), array_keys(TicketPlugin::get()->getLinkedTicketParentPanels()))
                ->whereExists(fn (QueryBuilder $sub): QueryBuilder => $sub
                    ->selectRaw('1')
                    ->from($activities, 'escalation_activities')
                    ->whereColumn('escalation_activities.ticket_id', $query->qualifyColumn('id'))
                    ->where('escalation_activities.type', ActivityType::OriginalAdded->value))));
    }

    /*
     * whereHas never runs the panel's relationship scope hook, so the host's
     * tenant scope would otherwise stay on the originals and, in a cross-tenant
     * panel, hide escalations whose originals belong to another tenant.
     */
    protected static function originalsFromThisPanel(Builder $query): Builder
    {
        $modifier = TicketPlugin::get()->getRelationshipScopeModifier();

        if ($modifier) {
            app()->call($modifier, ['relation' => $query, 'model' => 'childTickets']);
        }

        return $query->where($query->qualifyColumn('panel'), Filament::getCurrentOrDefaultPanel()->getId());
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
        return [];
    }

    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make()
                ->label(__('padmission-tickets::tickets.resources.tickets.tabs.all'))
                ->badge(fn (): int => $this->openTicketCount('all'))
                ->badgeTooltip(__('padmission-tickets::tickets.resources.tickets.badges.tab'))
                ->modifyQueryUsing(fn (Builder $query) => TicketResource::scopeListQueryToSupporterOrSubmitter(
                    $query->tap(new CurrentPanelScope)
                )),

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

        // Only a panel that escalates has tickets of its own linked elsewhere.
        if (count(TicketPlugin::get()->getLinkedTicketParentPanels()) === 0) {
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

        return $tabs;
    }
}
