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
            ->modifyQueryUsing(fn (Builder $query) => TicketResource::scopeListQueryToSupporterOrSubmitter(
                $query->whereHas('childTickets', fn (Builder $query) => $query->where('panel', Filament::getCurrentOrDefaultPanel()->getId()))
            ));

        $tabs['my_linked'] = Tab::make()
            ->label(__('padmission-tickets::tickets.resources.tickets.tabs.my_linked'))
            ->modifyQueryUsing(fn (Builder $query) => TicketResource::scopeListQueryToSupporterOrSubmitter(
                $query
                    ->whereHas('childTickets', fn (Builder $query) => $query->where('panel', Filament::getCurrentOrDefaultPanel()->getId()))
                    ->where('submitter_id', Filament::auth()->id())
            ));

        return $tabs;
    }
}
