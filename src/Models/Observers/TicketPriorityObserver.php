<?php

namespace Padmission\Tickets\Models\Observers;

use Filament\Facades\Filament;
use Padmission\Tickets\Models\TicketPriority;

class TicketPriorityObserver
{
    public function creating(TicketPriority $priority): void
    {
        $priority->panel ??= Filament::getCurrentOrDefaultPanel()?->getId();
    }
}
