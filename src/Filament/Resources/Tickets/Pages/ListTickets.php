<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Pages;

use Filament\Resources\Pages\ListRecords;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Filament\Widgets\OpenSupporterTickets;
use Padmission\Tickets\Filament\Widgets\OpenTicketsWidget;
use Padmission\Tickets\Filament\Widgets\TicketCloseTimeWidget;

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
}
