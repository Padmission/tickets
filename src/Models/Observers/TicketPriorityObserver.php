<?php

namespace Padmission\Tickets\Models\Observers;

use Filament\Facades\Filament;
use Padmission\Tickets\Models\Contracts\TicketPriorityInterface;

class TicketPriorityObserver
{
    public function creating(TicketPriorityInterface $priority): void
    {
        $priority->panel ??= Filament::getCurrentPanel()?->getId();
    }
}
