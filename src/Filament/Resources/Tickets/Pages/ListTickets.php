<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Pages;

use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Lang;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Filament\Widgets\OpenSupporterTickets;
use Padmission\Tickets\Filament\Widgets\OpenTicketsWidget;
use Padmission\Tickets\Filament\Widgets\TicketCloseTimeWidget;
use Padmission\Tickets\Models\Scopes\CurrentPanelScope;
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

        return TicketResource::scopeListQueryToSupporterOrSubmitter($query);
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
        if (! TicketResource::currentUserIsSupporter(Filament::auth()->id())) {
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

    public function getSubheading(): ?string
    {
        $tab = $this->activeTabIsInvalid() ? 'all' : $this->activeTab;

        if ($tab === 'all' && ! TicketResource::currentUserIsSupporter(Filament::auth()->id())) {
            $tab = 'all_submitter';
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
                ->modifyQueryUsing(fn (Builder $query) => TicketResource::scopeListQueryToSupporterOrSubmitter(
                    $query->tap(new CurrentPanelScope)
                )),

            'my' => Tab::make()
                ->label(__('padmission-tickets::tickets.resources.tickets.tabs.my'))
                ->badge(fn (): ?int => TicketResource::countOpenTicketsAssignedToCurrentUser() ?: null)
                ->badgeTooltip(__('padmission-tickets::tickets.resources.tickets.badges.my'))
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
            ->badge(fn (): ?int => $this->openEscalatedCounts()['linked'] ?: null)
            ->badgeTooltip(__('padmission-tickets::tickets.resources.tickets.badges.linked'))
            ->modifyQueryUsing(fn (Builder $query) => TicketResource::scopeListQueryToSupporterOrSubmitter(
                $query->whereHas('childTickets', fn (Builder $query) => $query->where('panel', Filament::getCurrentOrDefaultPanel()->getId()))
            ));

        $tabs['my_linked'] = Tab::make()
            ->label(__('padmission-tickets::tickets.resources.tickets.tabs.my_linked'))
            ->badge(fn (): ?int => $this->openEscalatedCounts()['my_linked'] ?: null)
            ->badgeTooltip(__('padmission-tickets::tickets.resources.tickets.badges.my_linked'))
            ->modifyQueryUsing(fn (Builder $query) => TicketResource::scopeListQueryToSupporterOrSubmitter(
                $query
                    ->whereHas('childTickets', fn (Builder $query) => $query->where('panel', Filament::getCurrentOrDefaultPanel()->getId()))
                    ->where('submitter_id', Filament::auth()->id())
            ));

        return $tabs;
    }
}
