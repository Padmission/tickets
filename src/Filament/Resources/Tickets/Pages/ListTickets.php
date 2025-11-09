<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Pages;

use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
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

    protected function getHeaderWidgets(): array
    {
        return [
            OpenTicketsWidget::class,
            OpenSupporterTickets::class,
            TicketCloseTimeWidget::class,
        ];
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
                ->modifyQueryUsing(fn (Builder $query) => $query->tap(new CurrentPanelScope)),

            'my' => Tab::make()
                ->label(__('padmission-tickets::tickets.resources.tickets.tabs.my'))
                ->modifyQueryUsing(fn (Builder $query) => $query
                    ->tap(new CurrentPanelScope)
                    ->where('assignee_id', Filament::auth()->id())
                ),
        ];

        if (! TicketPlugin::get()->hasLinkedTickets()) {
            return $tabs;
        }

        $tabs['linked'] = Tab::make()
            ->label(__('padmission-tickets::tickets.resources.tickets.tabs.linked'))
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->whereHas('childTickets', fn (Builder $query) => $query->where('panel', Filament::getCurrentOrDefaultPanel()->getId()))
            );

        $tabs['my_linked'] = Tab::make()
            ->label(__('padmission-tickets::tickets.resources.tickets.tabs.my_linked'))
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->whereHas('childTickets', fn (Builder $query) => $query->where('panel', Filament::getCurrentOrDefaultPanel()->getId()))
                ->where('submitter_id', Filament::auth()->id())
            );

        return $tabs;
    }
}
