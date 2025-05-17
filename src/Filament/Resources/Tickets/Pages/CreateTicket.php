<?php

namespace Padmission\Tickets\Filament\Resources\Tickets\Pages;

use Filament\Resources\Pages\CreateRecord;
use Padmission\Tickets\Filament\Resources\Tickets\TicketResource;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
