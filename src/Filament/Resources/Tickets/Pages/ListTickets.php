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
use Padmission\Tickets\TicketPlugin;

class ListTickets extends ListRecords
{
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
                ->label(__('padmission-tickets::tickets.resources.tickets.tabs.all')),

            'my' => Tab::make()
                ->label(__('padmission-tickets::tickets.resources.tickets.tabs.my'))
                ->modifyQueryUsing(fn (Builder $query) => $query->where('assignee_id', Filament::auth()->id())),
        ];

        if (! TicketPlugin::get()->hasLinkedTickets()) {
            return $tabs;
        }

        $tabs['linked'] = Tab::make()
            ->label(__('padmission-tickets::tickets.resources.tickets.tabs.linked'))
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->whereNotNull('linked_ticket_id')
                ->where('source_panel', Filament::getCurrentPanel()->getId())
            );

        $tabs['my_linked'] = Tab::make()
            ->label(__('padmission-tickets::tickets.resources.tickets.tabs.my_linked'))
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->whereNotNull('linked_ticket_id')
                ->where('source_panel', Filament::getCurrentPanel()->getId())
                ->where('submitter_id', Filament::auth()->id())
            );

        return $tabs;
    }
}
