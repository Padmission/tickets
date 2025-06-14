<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Pages;

use Filament\Resources\Pages\ListRecords;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;
use Padmission\Tickets\Filament\Widgets;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    public function getHeaderWidgetsColumns(): int|string|array
    {
        return 12;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            Widgets\OpenTicketsWidget::class,
            Widgets\OpenSupporterTickets::class,
            Widgets\TicketCloseTimeWidget::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
