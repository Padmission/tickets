<?php

namespace Padmission\Tickets\Models\Observers;

use Filament\Facades\Filament;
use Padmission\Tickets\Models\TicketStatus;

class TicketStatusObserver
{
    public function creating(TicketStatus $status): void
    {
        $status->panel ??= Filament::getCurrentOrDefaultPanel()?->getId();
    }
}
