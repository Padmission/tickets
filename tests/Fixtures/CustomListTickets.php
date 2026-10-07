<?php

namespace Padmission\Tickets\Tests\Fixtures;

use Illuminate\Database\Eloquent\Builder;
use Padmission\Tickets\Filament\Resources\Tickets\Pages\ListTickets;

class CustomListTickets extends ListTickets
{
    protected function getTableQuery(): ?Builder
    {
        return parent::getTableQuery()->where('subject', 'Custom list');
    }
}
